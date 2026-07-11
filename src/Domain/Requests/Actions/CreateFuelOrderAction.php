<?php

namespace Domain\Requests\Actions;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\FuelOrder;
use Domain\Requests\Models\ServiceStation;

class CreateFuelOrderAction
{
    /**
     * Calcula la estimación de consumo de combustible y genera una orden digital (vale).
     *
     * @throws ValidationException
     */
    public function execute(int $routeSheetId, int $stationId, int $transportChiefId): FuelOrder
    {
        // 1. Validar la hoja de ruta y estación
        $routeSheet = RouteSheet::with(['vehicle', 'request'])->findOrFail($routeSheetId);
        $station = ServiceStation::findOrFail($stationId);

        if (!$station->active_agreement) {
            throw ValidationException::withMessages([
                'station_id' => ['La estación de servicio seleccionada no tiene un convenio activo con la universidad.']
            ]);
        }

        // Evitar duplicar vales para la misma hoja de ruta
        $existingOrder = FuelOrder::where('route_sheet_id', $routeSheetId)->first();
        if ($existingOrder) {
            throw ValidationException::withMessages([
                'route_sheet_id' => ["Ya existe un vale de combustible emitido para esta hoja de ruta ({$existingOrder->order_code})."]
            ]);
        }

        // 2. Determinar distancia estimada al destino
        $destination = strtoupper($routeSheet->request->destination);
        $distance = 150; // Fallback por defecto

        if (str_contains($destination, 'QUITO')) {
            $distance = 380;
        } elseif (str_contains($destination, 'GUAYAQUIL')) {
            $distance = 200;
        } elseif (str_contains($destination, 'PORTOVIEJO')) {
            $distance = 40;
        } elseif (str_contains($destination, 'CHONE')) {
            $distance = 90;
        } elseif (str_contains($destination, 'MANTA')) {
            $distance = 20;
        }

        // 3. Determinar rendimiento de combustible del vehículo (km por galón)
        $fuelType = strtolower($routeSheet->vehicle->fuel_type);
        $performance = 30.0; // Gasolina (Extra / Súper) por defecto

        if ($fuelType === 'diesel') {
            $performance = 35.0; // Diésel es más eficiente
        }

        // 4. Fórmula de negocio para galones autorizados
        // galones_autorizados = (Distancia / Rendimiento) + Margen de Seguridad (2 galones)
        $authorizedGallons = round(($distance / $performance) + 2.0, 2);

        // 5. Generar código de vale único
        $orderCode = 'ULEAM-' . strtoupper(Str::random(6));

        // 6. Registrar en base de datos
        return FuelOrder::create([
            'order_code' => $orderCode,
            'route_sheet_id' => $routeSheetId,
            'station_id' => $stationId,
            'transport_chief_id' => $transportChiefId,
            'dispatched_fuel_type' => $routeSheet->vehicle->fuel_type,
            'authorized_gallons' => $authorizedGallons,
            'actual_dispatched_gallons' => null,
            'total_amount_paid' => null,
            'order_status' => 'emitida',
            'dispatch_date' => null
        ]);
    }
}
