<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\RouteSheet;

class PendingRouteSheetsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // Obtener las hojas de ruta en estado programado (pendiente de salida) o en_ruta (pendiente de llegada)
        $routeSheets = RouteSheet::with(['vehicle', 'driver.user', 'request.requester'])
            ->whereIn('trip_status', ['programado', 'en_ruta'])
            ->orderBy('id', 'desc')
            ->get();

        return response()->json($routeSheets);
    }
}
