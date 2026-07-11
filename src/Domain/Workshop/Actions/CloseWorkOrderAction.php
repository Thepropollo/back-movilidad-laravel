<?php

namespace Domain\Workshop\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Domain\Workshop\Models\SupplyInventory;
use Domain\Workshop\Models\WorkOrderSupplyProvision;

class CloseWorkOrderAction
{
    /**
     * Cierra una orden de trabajo de taller, descuenta insumos y libera el vehículo.
     *
     * @throws ValidationException
     */
    public function execute(int $workOrderId, array $suppliesUsed): WorkshopWorkOrder
    {
        return DB::transaction(function () use ($workOrderId, $suppliesUsed) {
            // 1. Obtener la orden de trabajo
            $workOrder = WorkshopWorkOrder::with(['vehicle', 'issueLog'])->findOrFail($workOrderId);
            $vehicle = $workOrder->vehicle;

            if ($workOrder->exit_date) {
                throw ValidationException::withMessages([
                    'work_order_id' => ['Esta orden de trabajo ya ha sido cerrada anteriormente.']
                ]);
            }

            // 2. Procesar los insumos utilizados
            foreach ($suppliesUsed as $item) {
                $supply = SupplyInventory::findOrFail($item['id']);

                if ($supply->current_stock < $item['quantity']) {
                    throw ValidationException::withMessages([
                        'insumos' => ["Stock insuficiente para el insumo '{$supply->supply_name}'. Stock actual: {$supply->current_stock}, Requerido: {$item['quantity']}."]
                    ]);
                }

                // Descontar stock
                $supply->decrement('current_stock', $item['quantity']);

                // Registrar en la provisión
                WorkOrderSupplyProvision::create([
                    'work_order_id' => $workOrder->id,
                    'supply_id' => $item['id'],
                    'quantity_used' => $item['quantity']
                ]);
            }

            // 3. Registrar fecha de egreso (exit_date)
            $workOrder->update([
                'exit_date' => Carbon::now()
            ]);

            // 4. Reglas del Vehículo al salir del taller
            $vehicleData = [
                'operational_status' => 'disponible'
            ];

            if ($workOrder->maintenance_type === 'cambio_aceite') {
                $vehicleData['next_oil_change_mileage'] = $vehicle->current_mileage + 5000;
            }

            $vehicle->update($vehicleData);

            // 5. Solventar la novedad asociada (si existe)
            if ($workOrder->issueLog) {
                $workOrder->issueLog->update([
                    'status' => 'solventado'
                ]);
            }

            return $workOrder;
        });
    }
}
