<?php

namespace Domain\Requests\Actions;

use Carbon\Carbon;
use Domain\Requests\Models\FuelOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DispatchFuelOrderAction
{
    /**
     * Procesa el despacho en bomba por parte de la gasolinera.
     *
     * @throws ValidationException
     */
    public function execute(string $orderCode, float $actualDispatchedGallons, float $totalAmountPaid): FuelOrder
    {
        return DB::transaction(function () use ($orderCode, $actualDispatchedGallons, $totalAmountPaid) {
            // 1. Obtener y bloquear la orden de combustible
            $fuelOrder = FuelOrder::query()->where('order_code', $orderCode)->lockForUpdate()->first();

            if (! $fuelOrder) {
                throw ValidationException::withMessages([
                    'order_code' => ['El código de vale de combustible ingresado no existe en los registros.'],
                ]);
            }

            // 2. Verificar estado de la orden
            if ($fuelOrder->order_status !== 'emitida') {
                throw ValidationException::withMessages([
                    'order_code' => ["Este vale de combustible no se encuentra activo. Estado actual: '{$fuelOrder->order_status}'."],
                ]);
            }

            if ($actualDispatchedGallons <= 0 || $totalAmountPaid <= 0) {
                throw ValidationException::withMessages([
                    'actual_dispatched_gallons' => ['Los galones y el monto pagado deben ser mayores que cero.'],
                ]);
            }

            // 3. Validar cupo de galones reales frente a autorizados
            if ($actualDispatchedGallons > $fuelOrder->authorized_gallons) {
                throw ValidationException::withMessages([
                    'actual_dispatched_gallons' => ['El despacho excede el límite de galones autorizados para este vale institucional.'],
                ]);
            }

            // 4. Registrar despacho
            $fuelOrder->update([
                'actual_dispatched_gallons' => $actualDispatchedGallons,
                'total_amount_paid' => $totalAmountPaid,
                'dispatch_date' => Carbon::now(),
                'order_status' => 'despachada',
            ]);

            return $fuelOrder;
        });
    }
}
