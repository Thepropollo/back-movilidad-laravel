<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Domain\Requests\Models\FuelOrder;
use Domain\Requests\Models\RouteSheet;
use Domain\Vehicles\Models\Vehicle;
use Carbon\Carbon;

class AdminKpiController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // 1. Total de Galones Consumidos en el Mes Actual
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $totalGallons = FuelOrder::where('order_status', 'despachada')
            ->whereBetween('dispatch_date', [$startOfMonth, $endOfMonth])
            ->sum('actual_dispatched_gallons');

        // 2. Kilómetros Totales Recorridos por la Flota
        $totalKm = RouteSheet::where('trip_status', 'finalizado')
            ->select(DB::raw('SUM(COALESCE(final_mileage, 0) - COALESCE(initial_mileage, 0)) as total_km'))
            ->first()
            ->total_km ?? 0;

        // 3. Número de Viajes Finalizados
        $totalTrips = RouteSheet::where('trip_status', 'finalizado')->count();

        // 4. Carros actualmente en_taller
        $vehiclesInWorkshop = Vehicle::where('operational_status', 'en_taller')->count();

        return response()->json([
            'total_gallons' => round((float) $totalGallons, 2),
            'total_km' => (int) $totalKm,
            'total_trips' => $totalTrips,
            'vehicles_in_workshop' => $vehiclesInWorkshop
        ]);
    }
}
