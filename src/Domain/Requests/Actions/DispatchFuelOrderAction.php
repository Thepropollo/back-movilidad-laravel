<?php

namespace Domain\Requests\Actions;

use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Domain\Requests\Models\FuelOrder;

class DispatchFuelOrderAction
{
    /**
     * Procesa el despacho en bomba por parte de la gasolinera.
     *
     * @throws ValidationException
     */
    public function execute(string $orderCode, float $actualDispatchedGallons, float $totalAmountPaid): FuelOrder
    {
        // 1. Obtener la orden de combustible
        $fuelOrder = FuelOrder::where('order_code', $orderCode)->first();

        if (!$fuelOrder) {
            throw ValidationException::withMessages([
                'order_code' => ['El código de vale de combustible ingresado no existe en los registros.']
            ]);
        }

        // 2. Verificar estado de la orden
        if ($fuelOrder->order_status !== 'emitida') {
            throw ValidationException::withMessages([
                'order_code' => ["Este vale de combustible no se encuentra activo. Estado actual: '{$fuelOrder->order_status}'."]
            ]);
        }

        // 3. Validar cupo de galones reales frente a autorizados
        if ($actualDispatchedGallons > $fuelOrder->authorized_gallons) {
            throw ValidationException::withMessages([
                'actual_dispatched_gallons' => ['El despacho excede el límite de galones autorizados para este vale institucional.']
            ]);
        }

        // 4. Registrar despacho
        $fuelOrder->update([
            'actual_dispatched_gallons' => $actualDispatchedGallons,
            'total_amount_paid' => $totalAmountPaid,
            'dispatch_date' => Carbon::now(),
            'order_status' => 'despachada'
        ]);

        return $fuelOrder;
    }
}
