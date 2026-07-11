<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Actions\CalculateDriverCompensationAction;
use Domain\Requests\Models\DriverCompensation;

class DriverCompensationLiquidarController extends Controller
{
    public function __construct(
        protected CalculateDriverCompensationAction $calculateAction
    ) {}

    public function __invoke(Request $request, $hoja_ruta_id)
    {
        $request->validate([
            'comprobante_pago_url' => 'required|string',
        ], [
            'comprobante_pago_url.required' => 'El comprobante de pago es obligatorio.',
        ]);

        $hojaRutaId = (int) $hoja_ruta_id;
        $receiptUrl = $request->input('comprobante_pago_url');

        // 1. Calcular haberes
        $calc = $this->calculateAction->execute($hojaRutaId);

        // 2. Registrar en la tabla driver_compensations
        $compensation = DriverCompensation::updateOrCreate(
            ['route_sheet_id' => $hojaRutaId],
            [
                'applied_rate_id' => $calc['applied_rate_id'],
                'allowances_amount' => $calc['allowances_amount'],
                'overtime_50_amount' => $calc['overtime_50_amount'],
                'overtime_100_amount' => $calc['overtime_100_amount'],
                'total_payout' => $calc['total_payout'],
                'payment_receipt_url' => $receiptUrl,
                'payment_status' => 'pendiente_comprobante'
            ]
        );

        return response()->json([
            'message' => 'Comprobante cargado. Liquidación registrada en estado pendiente de auditoría.',
            'compensation' => $compensation
        ]);
    }
}
