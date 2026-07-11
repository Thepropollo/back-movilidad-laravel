<?php

namespace Domain\Requests\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\DeliveryReceptionAct;
use Domain\Requests\Models\ActChecklistDetail;
use Domain\Requests\Models\ChecklistInventoryComponent;
use Domain\Workshop\Models\IssueLog;

class CreateDeliveryReceptionActAction
{
    /**
     * Ejecuta el registro de la inspección y aplica las reglas de despacho o derivación al taller.
     *
     * @throws ValidationException
     */
    public function execute(
        int $routeSheetId,
        int $mechanicOrGuardId,
        string $registrationType,
        string $fuelLevel,
        int $checkpointMileage,
        array $components
    ): DeliveryReceptionAct {
        return DB::transaction(function () use (
            $routeSheetId,
            $mechanicOrGuardId,
            $registrationType,
            $fuelLevel,
            $checkpointMileage,
            $components
        ) {
            // 1. Obtener la hoja de ruta
            $routeSheet = RouteSheet::with(['vehicle', 'driver'])->findOrFail($routeSheetId);
            $vehicle = $routeSheet->vehicle;

            // Validar kilometraje coherente
            if ($registrationType === 'salida' && $checkpointMileage < $vehicle->current_mileage) {
                throw ValidationException::withMessages([
                    'checkpoint_mileage' => ["El kilometraje de salida ({$checkpointMileage}) no puede ser menor al kilometraje actual del vehículo ({$vehicle->current_mileage})."]
                ]);
            }

            if ($registrationType === 'llegada' && $routeSheet->initial_mileage && $checkpointMileage < $routeSheet->initial_mileage) {
                throw ValidationException::withMessages([
                    'checkpoint_mileage' => ["El kilometraje de llegada ({$checkpointMileage}) no puede ser menor al kilometraje inicial registrado ({$routeSheet->initial_mileage})."]
                ]);
            }

            // 2. Crear acta de entrega/recepción
            $act = DeliveryReceptionAct::create([
                'route_sheet_id' => $routeSheetId,
                'mechanic_or_guard_id' => $mechanicOrGuardId,
                'registration_type' => $registrationType,
                'fuel_level' => $fuelLevel,
                'checkpoint_mileage' => $checkpointMileage,
                'general_observations' => null
            ]);

            // 3. Registrar detalles y verificar estado "MALO"
            $hasMalo = false;
            $failedComponents = [];

            foreach ($components as $item) {
                ActChecklistDetail::create([
                    'act_id' => $act->id,
                    'component_id' => $item['id'],
                    'physical_condition' => $item['physical_condition']
                ]);

                if ($item['physical_condition'] === 'MALO') {
                    $hasMalo = true;
                    $comp = ChecklistInventoryComponent::find($item['id']);
                    $failedComponents[] = $comp ? $comp->component_name : "Componente #{$item['id']}";
                }
            }

            // 4. Aplicar flujo según resultado del checklist
            if ($hasMalo) {
                // FLUJO DE EXCEPCIÓN DEL TALLER
                // Cambia el estado del vehículo a 'en_taller'
                $vehicle->update([
                    'operational_status' => 'en_taller',
                    'current_mileage' => $checkpointMileage // actualiza el kilometraje reportado
                ]);

                // Inserta fila en libro_novedades (issue_logs)
                IssueLog::create([
                    'vehicle_id' => $vehicle->id,
                    'route_sheet_id' => $routeSheet->id,
                    'reporting_driver_id' => $routeSheet->driver->user_id,
                    'breakdown_date' => Carbon::today(),
                    'description' => "Novedad en inspección de {$registrationType}. Componentes defectuosos: " . implode(', ', $failedComponents),
                    'status' => 'pendiente'
                ]);

                // Bloquea el viaje manteniendo estado 'programado'
                if ($registrationType === 'salida') {
                    $routeSheet->update([
                        'trip_status' => 'programado'
                    ]);
                }
            } else {
                // CAMINO FELIZ
                if ($registrationType === 'salida') {
                    $routeSheet->update([
                        'initial_mileage' => $checkpointMileage,
                        'trip_status' => 'en_ruta'
                    ]);

                    $vehicle->update([
                        'operational_status' => 'en_viaje',
                        'current_mileage' => $checkpointMileage
                    ]);
                } else {
                    // llegada sin novedades
                    $routeSheet->update([
                        'final_mileage' => $checkpointMileage,
                        'trip_status' => 'finalizado'
                    ]);

                    $vehicle->update([
                        'operational_status' => 'disponible',
                        'current_mileage' => $checkpointMileage
                    ]);

                    // Liberar chofer
                    $routeSheet->driver->update([
                        'is_available' => true
                    ]);
                }
            }

            return $act;
        });
    }
}
