<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\FuelOrder;
use Domain\Vehicles\Models\Vehicle;
use Domain\Auth\Models\Driver;

class DashboardMetricsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        // Requests counts
        $pendingRequests = MobilizationRequest::where('status', 'pendiente')->count();
        $approvedRequests = MobilizationRequest::where('status', 'aprobada')->count();
        $totalRequests = MobilizationRequest::count();

        // Vehicles counts
        $totalVehicles = Vehicle::count();
        $operationalVehicles = Vehicle::where('operational_status', 'disponible')->count();
        $workshopVehicles = Vehicle::where('operational_status', 'en_taller')->count();

        // Drivers counts
        $totalDrivers = Driver::count();
        $availableDrivers = Driver::where('is_available', true)->count();

        // Fuel Orders counts
        $activeFuelOrders = FuelOrder::where('order_status', 'emitida')->count();
        $dispatchedFuelOrders = FuelOrder::where('order_status', 'despachada')->count();
        $totalGallons = FuelOrder::where('order_status', 'despachada')->sum('actual_dispatched_gallons');
        $totalSpent = FuelOrder::where('order_status', 'despachada')->sum('total_amount_paid');

        return response()->json([
            'pending_requests' => $pendingRequests,
            'approved_requests' => $approvedRequests,
            'total_requests' => $totalRequests,
            'total_vehicles' => $totalVehicles,
            'operational_vehicles' => $operationalVehicles,
            'workshop_vehicles' => $workshopVehicles,
            'total_drivers' => $totalDrivers,
            'available_drivers' => $availableDrivers,
            'active_fuel_orders' => $activeFuelOrders,
            'dispatched_fuel_orders' => $dispatchedFuelOrders,
            'total_gallons' => round((float) $totalGallons, 2),
            'total_spent' => round((float) $totalSpent, 2)
        ]);
    }
}
