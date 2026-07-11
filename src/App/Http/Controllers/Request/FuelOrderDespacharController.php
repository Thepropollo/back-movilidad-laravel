<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Actions\DispatchFuelOrderAction;

class FuelOrderDespacharController extends Controller
{
    public function __construct(
        protected DispatchFuelOrderAction $action
    ) {}

    public function __invoke(Request $request, $codigo_orden)
    {
        $request->validate([
            'galones_reales_despachados' => 'required|numeric|min:0.01',
            'valor_total_pagado' => 'required|numeric|min:0.01',
        ], [
            'galones_reales_despachados.required' => 'La cantidad de galones reales despachados es obligatoria.',
            'valor_total_pagado.required' => 'El valor total pagado es obligatorio.',
        ]);

        $fuelOrder = $this->action->execute(
            orderCode: (string) $codigo_orden,
            actualDispatchedGallons: (float) $request->input('galones_reales_despachados'),
            totalAmountPaid: (float) $request->input('valor_total_pagado')
        );

        return response()->json([
            'message' => 'Transacción Registrada - Liquidación enviada a la Sección de Transporte ULEAM.',
            'fuel_order' => $fuelOrder
        ]);
    }
}
