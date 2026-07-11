<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\FuelOrder;
use Domain\Auth\Models\Driver;

class DriverFuelOrdersController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // Si es el Jefe de Transporte, devolvemos todos los vales
        if ($user->role && $user->role->name === 'jefe_transporte') {
            $fuelOrders = FuelOrder::with([
                'routeSheet.vehicle',
                'routeSheet.driver.user',
                'routeSheet.request',
                'station'
            ])->orderBy('id', 'desc')->get();
            return response()->json($fuelOrders);
        }

        // Si es un conductor, filtramos por su ID de conductor
        $driver = Driver::where('user_id', $user->id)->first();
        if (!$driver) {
            return response()->json([]);
        }

        $fuelOrders = FuelOrder::with([
            'routeSheet.vehicle',
            'routeSheet.driver.user',
            'routeSheet.request',
            'station'
        ])
        ->whereHas('routeSheet', function ($query) use ($driver) {
            $query->where('driver_id', $driver->id);
        })
        ->orderBy('id', 'desc')
        ->get();

        return response()->json($fuelOrders);
    }
}
