<?php

namespace Domain\Requests\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Vehicles\Models\Vehicle;
use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Domain\Auth\Models\SystemLog;

class CreateRouteSheetAction
{
    /**
     * Ejecuta la asignación de recursos y apertura de hoja de ruta dentro de una transacción.
     *
     * @throws ValidationException
     */
    public function execute(int $requestId, int $vehicleId, int $driverId, int $transportChiefId, string $ipAddress = '127.0.0.1'): RouteSheet
    {
        return DB::transaction(function () use ($requestId, $vehicleId, $driverId, $transportChiefId, $ipAddress) {
            // 1. Obtener y validar la solicitud
            $request = MobilizationRequest::findOrFail($requestId);
            
            $validStatus = $request->mobilization_type === 'interna' 
                ? ($request->status === 'pendiente') 
                : ($request->status === 'aprobado_rectorado');
                
            if (!$validStatus) {
                throw ValidationException::withMessages([
                    'request_id' => ['La solicitud no cuenta con las aprobaciones jerárquicas requeridas.']
                ]);
            }

            // 2. Obtener y validar el vehículo
            $vehicle = Vehicle::findOrFail($vehicleId);
            
            // Regla 2: Filtro de Cambio de Aceite Preventivo
            if ($vehicle->current_mileage >= $vehicle->next_oil_change_mileage) {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['Unidad bloqueada por sistema: El vehículo seleccionado ha superado el límite permitido para el cambio de aceite y requiere mantenimiento preventivo en el taller.']
                ]);
            }

            // Regla 3: Filtro de Estado Operativo
            if ($vehicle->operational_status !== 'disponible') {
                throw ValidationException::withMessages([
                    'vehicle_id' => ['El vehículo seleccionado no se encuentra disponible en los patios de la institución.']
                ]);
            }

            // 3. Obtener y validar el chofer
            $driver = Driver::findOrFail($driverId);
            
            // Regla 4: Filtro Legal del Chofer
            if (!$driver->is_available) {
                throw ValidationException::withMessages([
                    'driver_id' => ['El conductor seleccionado no está habilitado, tiene la licencia caducada o no cuenta con puntos disponibles.']
                ]);
            }

            $activeLicense = $driver->licenses()
                ->where('current_points', '>', 0)
                ->where('expiration_date', '>=', Carbon::today())
                ->first();

            if (!$activeLicense) {
                throw ValidationException::withMessages([
                    'driver_id' => ['El conductor seleccionado no está habilitado, tiene la licencia caducada o no cuenta con puntos disponibles.']
                ]);
            }

            // 4. Actualizaciones de estado
            $request->update(['status' => 'aprobada']);
            $vehicle->update(['operational_status' => 'en_viaje']);
            $driver->update(['is_available' => false]);

            // 5. Crear la hoja de ruta
            $routeSheet = RouteSheet::create([
                'request_id' => $requestId,
                'vehicle_id' => $vehicleId,
                'driver_id' => $driverId,
                'transport_chief_id' => $transportChiefId,
                'initial_mileage' => $vehicle->current_mileage,
                'trip_status' => 'programado'
            ]);

            // 6. Registrar en el log de auditoría
            SystemLog::create([
                'user_id' => $transportChiefId,
                'action' => 'ROUTE_SHEET_CREATED',
                'affected_table' => 'route_sheets',
                'record_id' => $routeSheet->id,
                'ip_address' => $ipAddress
            ]);

            return $routeSheet;
        });
    }
}
