<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Workshop\Actions\CloseWorkOrderAction;

class CloseWorkOrderController extends Controller
{
    public function __construct(
        protected CloseWorkOrderAction $action
    ) {}

    public function __invoke(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
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
            'work_order' => $workOrder
        ]);
    }
}
