<?php

namespace Domain\Requests\Actions;

use Domain\Requests\Models\DeliveryReceptionAct;
use Domain\Requests\Models\RouteSheet;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterDeliveryArrivalAction
{
    public function execute(
        int $routeSheetId,
        int $actorId,
        int $checkpointMileage,
        string $fuelLevel
    ): RouteSheet {
        return DB::transaction(function () use ($routeSheetId, $actorId, $checkpointMileage, $fuelLevel) {
            $routeSheet = RouteSheet::query()->lockForUpdate()->findOrFail($routeSheetId);

            if ($routeSheet->trip_status !== 'en_ruta' || $routeSheet->driver_response !== 'aceptado') {
                throw ValidationException::withMessages([
                    'hoja_ruta_id' => ['Solo se puede registrar llegada de un viaje aceptado y en curso.'],
                ]);
            }

            if ($routeSheet->deliveryActs()->where('registration_type', 'llegada')->exists()) {
                throw ValidationException::withMessages([
                    'hoja_ruta_id' => ['La inspección de llegada ya fue registrada.'],
                ]);
            }

            if ($routeSheet->initial_mileage !== null && $checkpointMileage < $routeSheet->initial_mileage) {
                throw ValidationException::withMessages([
                    'kilometraje_garita' => ['El kilometraje de llegada no puede ser menor al de salida.'],
                ]);
            }

            $vehicle = Vehicle::query()->lockForUpdate()->findOrFail($routeSheet->vehicle_id);
            if ($checkpointMileage < $vehicle->current_mileage) {
                throw ValidationException::withMessages([
                    'kilometraje_garita' => ['El kilometraje de llegada no puede ser menor al kilometraje actual del vehículo.'],
                ]);
            }

            DeliveryReceptionAct::create([
                'route_sheet_id' => $routeSheet->id,
                'mechanic_or_guard_id' => $actorId,
                'registration_type' => 'llegada',
                'fuel_level' => $fuelLevel,
                'checkpoint_mileage' => $checkpointMileage,
                'general_observations' => 'Retorno de comisión registrado en garita.',
            ]);

            $routeSheet->update([
                'final_mileage' => $checkpointMileage,
                'trip_status' => 'pendiente_feedback',
            ]);
            $vehicle->update([
                'operational_status' => 'disponible',
                'current_mileage' => $checkpointMileage,
            ]);

            return $routeSheet->fresh();
        });
    }
}
