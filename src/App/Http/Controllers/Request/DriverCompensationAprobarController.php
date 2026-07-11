<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\DriverCompensation;

class DriverCompensationAprobarController extends Controller
{
    public function __invoke($hoja_ruta_id)
    {
        $hojaRutaId = (int) $hoja_ruta_id;

        $compensation = DriverCompensation::where('route_sheet_id', $hojaRutaId)->firstOrFail();
        $routeSheet = RouteSheet::with('driver')->findOrFail($hojaRutaId);

        DB::transaction(function () use ($compensation, $routeSheet) {
            // 1. Cambiar estado_pago = verificado_movilidad
            $compensation->update([
                'payment_status' => 'verificado_movilidad'
            ]);

            // 2. Mudar hojas_ruta.estado_viaje = finalizado
            $routeSheet->update([
                'trip_status' => 'finalizado'
            ]);

            // 3. Liberar al chofer
            $routeSheet->driver->update([
                'is_available' => true
            ]);
        });

        return response()->json([
            'message' => 'Comisión liquidada y cerrada financieramente con éxito. El chofer está liberado.',
            'compensation' => $compensation->fresh(),
            'route_sheet' => $routeSheet->fresh()
        ]);
    }
}
