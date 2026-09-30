<?php

namespace Domain\Requests\Actions;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Domain\Auth\Models\Driver;
use Domain\Auth\Models\SystemLog;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Support\ScheduleInterval;
use Domain\Requests\Support\RequestWorkflow;
use Domain\Vehicles\Models\Vehicle;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRouteSheetAction
{
    public function execute(int $requestId, int $vehicleId, int $driverId, int $transportChiefId, string $ipAddress = '127.0.0.1'): RouteSheet
    {
        return DB::transaction(function () use ($requestId, $vehicleId, $driverId, $transportChiefId, $ipAddress) {
            $request = MobilizationRequest::query()->lockForUpdate()->findOrFail($requestId);

            $assignable = $request->mobilization_type === 'interna'
                ? in_array($request->status, ['autorizada_secretaria', 'pendiente'], true)
                : $request->status === 'aprobado_rectorado';

            if (! $assignable) {
                throw ValidationException::withMessages([
                    'request_id' => ['La solicitud no cuenta con las aprobaciones jerárquicas requeridas.'],
                ]);
            }

            if ($request->routeSheet) {
                throw ValidationException::withMessages([
                    'request_id' => ['La solicitud ya tiene una hoja de ruta asignada.'],
                ]);
            }

            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicleId);

            if ($vehicle->current_mileage >= $vehicle->next_oil_change_mileage) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['Unidad bloqueada: requiere cambio de aceite preventivo.'],
                ]);
            }

            if ($vehicle->operational_status !== 'disponible') {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['El vehículo no está disponible.'],
                ]);
            }

            if (WorkshopWorkOrder::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('exit_date')
                ->exists()) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['El vehículo tiene una orden de taller abierta.'],
                ]);
            }

            $driver = Driver::query()->lockForUpdate()->findOrFail($driverId);

            if (! $driver->is_available) {
                throw ValidationException::withMessages([
                    'driver_id' => ['El conductor no está disponible.'],
                ]);
            }

            $activeLicense = $driver->licenses()
                ->where('current_points', '>', 0)
                ->where('expiration_date', '>=', Carbon::today())
                ->first();

            if (! $activeLicense) {
                throw ValidationException::withMessages([
                    'driver_id' => ['El conductor no tiene licencia vigente con puntos.'],
                ]);
            }

            $start = CarbonImmutable::parse($request->departure_date->toDateString().' '.$request->departure_time);
            $end = CarbonImmutable::parse($request->return_date->toDateString().' '.$request->return_time);
            if ($end->lessThanOrEqualTo($start)) {
                throw ValidationException::withMessages([
                    'return_date' => ['El fin del viaje debe ser posterior a la salida.'],
                ]);
            }

            $candidateSheets = RouteSheet::query()
                ->where(function ($query) use ($vehicleId, $driverId) {
                    $query->where('vehicle_id', $vehicleId)->orWhere('driver_id', $driverId);
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

            $hasScheduleConflict = $candidateSheets->contains(function (RouteSheet $candidate) use ($start, $end) {
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

            if ($hasScheduleConflict) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['El vehículo o conductor tiene otra asignación que se cruza con este horario.'],
                ]);
            }

            $from = $request->status;
            $request->update(['status' => 'aprobada']);

            $routeSheet = RouteSheet::create([
                'request_id' => $requestId,
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'transport_chief_id' => $transportChiefId,
                'initial_mileage' => $vehicle->current_mileage,
                'trip_status' => 'programado',
                'driver_response' => 'pendiente',
            ]);

            RequestWorkflow::record(
                $request,
                'aprobada',
                'ASIGNACION_RECURSOS',
                $transportChiefId,
                'Se asignó conductor y vehículo. Pendiente aceptación del conductor.',
                $from
            );

            SystemLog::create([
                'user_id' => $transportChiefId,
                'action' => 'ROUTE_SHEET_CREATED',
                'affected_table' => 'route_sheets',
                'record_id' => $routeSheet->id,
                'ip_address' => $ipAddress,
            ]);

            return $routeSheet->load(['vehicle', 'driver.user', 'request']);
        });
    }
}
