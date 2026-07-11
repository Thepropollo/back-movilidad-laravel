<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Actions\CreateRouteSheetAction;

class RouteSheetStoreController extends Controller
{
    public function __construct(
        protected CreateRouteSheetAction $createRouteSheetAction
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->role || $user->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'Acceso denegado: Solo el Jefe de Transporte puede asignar recursos.'], 403);
        }

        $request->validate([
            'request_id' => 'required|integer',
            'vehicle_id' => 'required|integer',
            'driver_id' => 'required|integer',
        ], [
            'request_id.required' => 'La solicitud de movilización es requerida.',
            'vehicle_id.required' => 'Debe seleccionar un vehículo para el viaje.',
            'driver_id.required' => 'Debe seleccionar un conductor para el viaje.',
        ]);

        $routeSheet = $this->createRouteSheetAction->execute(
            requestId: (int) $request->input('request_id'),
            vehicleId: (int) $request->input('vehicle_id'),
            driverId: (int) $request->input('driver_id'),
            transportChiefId: $user->id,
            ipAddress: $request->ip() ?? '127.0.0.1'
        );

        return response()->json([
            'message' => 'Recursos asignados y hoja de ruta emitida exitosamente.',
            'route_sheet' => $routeSheet
        ], 201);
    }
}
