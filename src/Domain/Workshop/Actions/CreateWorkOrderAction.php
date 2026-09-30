<?php

namespace Domain\Workshop\Actions;

use Domain\Auth\Models\User;
use Domain\Requests\Models\RouteSheet;
use Domain\Vehicles\Models\Vehicle;
use Domain\Workshop\Models\IssueLog;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateWorkOrderAction
{
    public function execute(
        ?int $issueLogId,
        int $vehicleId,
        int $mechanicId,
        int $supervisorId,
        string $maintenanceType,
        string $workDetails
    ): WorkshopWorkOrder {
        return DB::transaction(function () use ($issueLogId, $vehicleId, $mechanicId, $supervisorId, $maintenanceType, $workDetails) {
            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($vehicleId);
            if ($vehicle->operational_status === 'en_viaje') {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['No se puede abrir mantenimiento mientras el vehículo está en viaje.'],
                ]);
            }

            $issue = null;
            if ($issueLogId !== null) {
                $issue = IssueLog::query()->lockForUpdate()->findOrFail($issueLogId);
                if ((int) $issue->vehicle_id !== $vehicleId) {
                    throw ValidationException::withMessages([
                        'issue_log_id' => ['La novedad no corresponde al vehículo seleccionado.'],
                    ]);
                }
                if ($issue->status !== 'pendiente') {
                    throw ValidationException::withMessages([
                        'issue_log_id' => ['La novedad ya está en revisión o fue solventada.'],
                    ]);
                }
            }

            $failedPreTripAssignment = $issue !== null
                && $issue->route_sheet_id !== null
                && $vehicle->operational_status === 'en_taller'
                && RouteSheet::query()
                    ->whereKey($issue->route_sheet_id)
                    ->where('vehicle_id', $vehicle->id)
                    ->where('trip_status', 'programado')
                    ->where('driver_response', 'aceptado')
                    ->exists();

            $activeAssignments = RouteSheet::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereIn('trip_status', ['programado', 'en_ruta', 'pendiente_feedback'])
                ->where('driver_response', '!=', 'rechazado')
                ->when($failedPreTripAssignment, fn ($query) => $query->where('id', '!=', $issue->route_sheet_id));
            $activeAssignment = $activeAssignments->exists();
            if ($activeAssignment) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['El vehículo tiene una hoja de ruta activa y no puede entrar a taller.'],
                ]);
            }

            $mechanic = User::query()->findOrFail($mechanicId);
            if (! $mechanic->hasRole('mecanico')) {
                throw ValidationException::withMessages([
                    'responsible_mechanic_id' => ['El responsable debe tener el rol de mecánico.'],
                ]);
            }

            $openOrder = WorkshopWorkOrder::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereNull('exit_date')
                ->exists();
            if ($openOrder) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['El vehículo ya tiene una orden de taller abierta.'],
                ]);
            }

            $workOrder = WorkshopWorkOrder::create([
                'issue_log_id' => $issue?->id,
                'vehicle_id' => $vehicle->id,
                'responsible_mechanic_id' => $mechanic->id,
                'supervisor_id' => $supervisorId,
                'maintenance_type' => $maintenanceType,
                'work_details' => $workDetails,
                'entry_date' => now(),
                'exit_date' => null,
            ]);

            $vehicle->update(['operational_status' => 'en_taller']);
            $issue?->update(['status' => 'en_revision']);

            return $workOrder;
        });
    }
}
