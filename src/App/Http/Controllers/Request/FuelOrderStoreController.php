<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Actions\CreateFuelOrderAction;

class FuelOrderStoreController extends Controller
{
    public function __construct(
        protected CreateFuelOrderAction $action
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $request->validate([
            'route_sheet_id' => 'required|integer|exists:route_sheets,id',
            'station_id' => 'required|integer|exists:service_stations,id',
        ], [
            'route_sheet_id.required' => 'La hoja de ruta es obligatoria.',
            'station_id.required' => 'La estación de servicio es obligatoria.',
        ]);

        $fuelOrder = $this->action->execute(
            routeSheetId: (int) $request->input('route_sheet_id'),
            stationId: (int) $request->input('station_id'),
            transportChiefId: (int) $user->id
        );

        return response()->json([
            'message' => 'Vale de combustible emitido con éxito.',
            'fuel_order' => $fuelOrder
        ], 201);
    }
}
