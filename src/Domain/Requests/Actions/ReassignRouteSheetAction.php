<?php

namespace Domain\Requests\Actions;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Support\RequestWorkflow;
use Domain\Requests\Support\ScheduleInterval;
use Domain\Vehicles\Models\Vehicle;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReassignRouteSheetAction
{
    public function execute(int $sheetId, int $driverId, ?int $vehicleId, int $actorId): RouteSheet
    {
        return DB::transaction(function () use ($sheetId, $driverId, $vehicleId, $actorId) {
            $sheet = RouteSheet::query()->with('request')->lockForUpdate()->findOrFail($sheetId);
            if (! in_array($sheet->driver_response, ['rechazado', 'pendiente'], true)) {
                throw ValidationException::withMessages([
                    'route_sheet_id' => ['Solo se reasignan viajes pendientes o rechazados.'],
                ]);
            }

            $nextVehicleId = $vehicleId ?? (int) $sheet->vehicle_id;
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($nextVehicleId);
            if ($vehicle->operational_status !== 'disponible'
                || $vehicle->current_mileage >= $vehicle->next_oil_change_mileage
                || WorkshopWorkOrder::query()->where('vehicle_id', $vehicle->id)->whereNull('exit_date')->exists()) {
                throw ValidationException::withMessages(['vehicle_id' => ['El vehículo no está disponible para reasignación.']]);
            }

            $driver = Driver::query()->lockForUpdate()->findOrFail($driverId);
            if (! $driver->is_available) {
                throw ValidationException::withMessages(['driver_id' => ['El conductor no está disponible.']]);
            }

            $license = $driver->licenses()
                ->where('current_points', '>', 0)
                ->where('expiration_date', '>=', Carbon::today())
                ->first();
            if (! $license) {
                throw ValidationException::withMessages(['driver_id' => ['Licencia no vigente.']]);
            }

            $request = $sheet->request;
            if (! $request?->departure_date || ! $request?->return_date) {
                throw ValidationException::withMessages(['request_id' => ['La solicitud no tiene un horario válido.']]);
            }
            $start = CarbonImmutable::parse($request->departure_date->toDateString().' '.$request->departure_time);
            $end = CarbonImmutable::parse($request->return_date->toDateString().' '.$request->return_time);
            if ($end->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages(['return_date' => ['El fin del viaje debe ser posterior a la salida.']]);
            }

            $candidates = RouteSheet::query()
                ->where('id', '!=', $sheet->id)
                ->where(function ($query) use ($nextVehicleId, $driverId) {
                    $query->where('vehicle_id', $nextVehicleId)->orWhere('driver_id', $driverId);
                })
                ->whereIn('trip_status', ['programado', 'en_ruta', 'pendiente_feedback'])
                ->where(function ($query) {
                    $query->whereNull('driver_response')->orWhere('driver_response', '!=', 'rechazado');
                })
                ->whereHas('request', fn ($query) => $query
                    ->whereDate('departure_date', '<=', $end->toDateString())
                    ->whereDate('return_date', '>=', $start->toDateString()))
                ->with('request')
                ->get();

            $conflict = $candidates->contains(function (RouteSheet $candidate) use ($start, $end) {
                if (! $candidate->request?->departure_date || ! $candidate->request?->return_date) {
                    return false;
                }
                $candidateStart = CarbonImmutable::parse(
                    $candidate->request->departure_date->toDateString().' '.$candidate->request->departure_time
                );
                $candidateEnd = CarbonImmutable::parse(
                    $candidate->request->return_date->toDateString().' '.$candidate->request->return_time
                );

                return ScheduleInterval::overlaps($start, $end, $candidateStart, $candidateEnd);
            });
            if ($conflict) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['El vehículo o conductor tiene otra asignación que se cruza con este horario.'],
                ]);
            }

            $sheet->update([
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'driver_response' => 'pendiente',
                'driver_reject_reason' => null,
                'driver_responded_at' => null,
                'trip_status' => 'programado',
            ]);

            RequestWorkflow::record(
                $request,
                $request->status,
                'REASIGNACION',
                $actorId,
                'Se reasignó conductor/vehículo.'
            );

            return $sheet->fresh(['driver.user', 'vehicle', 'request']);
        });
    }
}
