<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Domain\Workshop\Actions\CloseWorkOrderAction;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Illuminate\Http\Request;

class CloseWorkOrderController extends Controller
{
    public function __construct(
        protected CloseWorkOrderAction $action
    ) {}

    public function __invoke(Request $request, $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        if ($user->hasRole('mecanico') && ! $user->hasRole(['secretaria', 'jefe_transporte'])
            && ! WorkshopWorkOrder::query()
                ->whereKey($id)
                ->where('responsible_mechanic_id', $user->id)
                ->exists()) {
            return response()->json(['message' => 'No tiene acceso a esta orden de taller.'], 403);
        }

        $request->validate([
            'insumos_utilizados' => 'present|array',
            'insumos_utilizados.*.id' => 'required|integer|exists:supply_inventories,id',
            'insumos_utilizados.*.quantity' => 'required|integer|min:1',
        ], [
            'insumos_utilizados.array' => 'Los insumos deben ser un arreglo.',
        ]);

        $workOrder = $this->action->execute(
            workOrderId: (int) $id,
            suppliesUsed: $request->input('insumos_utilizados')
        );

        return response()->json([
            'message' => 'Unidad reparada con éxito. Vehículo liberado para circulación.',
            'work_order' => $workOrder,
        ]);
    }
}
