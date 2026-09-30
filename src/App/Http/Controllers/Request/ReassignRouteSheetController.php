<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Actions\ReassignRouteSheetAction;
use Illuminate\Http\Request;

class ReassignRouteSheetController extends Controller
{
    public function __invoke(Request $request, int $id, ReassignRouteSheetAction $action)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $data = $request->validate([
            'driver_id' => 'required|integer|exists:drivers,id',
            'vehicle_id' => 'nullable|integer|exists:vehicles,id',
        ]);

        $sheet = $action->execute(
            sheetId: $id,
            driverId: (int) $data['driver_id'],
            vehicleId: isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null,
            actorId: (int) $user->id
        );

        return response()->json([
            'message' => 'Reasignación realizada.',
            'route_sheet' => $sheet,
        ]);
    }
}
