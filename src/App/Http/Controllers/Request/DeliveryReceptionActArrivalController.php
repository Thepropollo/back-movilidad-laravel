<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Actions\RegisterDeliveryArrivalAction;
use Illuminate\Http\Request;

class DeliveryReceptionActArrivalController extends Controller
{
    public function __invoke(Request $request, RegisterDeliveryArrivalAction $action)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $request->validate([
            'hoja_ruta_id' => 'required|integer|exists:route_sheets,id',
            'kilometraje_garita' => 'required|integer|min:0',
            'nivel_combustible' => 'required|string|in:1/4,1/2,3/4,full',
        ], [
            'hoja_ruta_id.required' => 'La hoja de ruta es obligatoria.',
            'kilometraje_garita.required' => 'El kilometraje en garita es obligatorio.',
            'nivel_combustible.required' => 'El nivel de combustible es obligatorio.',
        ]);

        $routeSheet = $action->execute(
            routeSheetId: (int) $request->input('hoja_ruta_id'),
            actorId: (int) $user->id,
            checkpointMileage: (int) $request->input('kilometraje_garita'),
            fuelLevel: (string) $request->input('nivel_combustible')
        );

        return response()->json([
            'message' => 'Llegada registrada exitosamente. Pendiente de co-evaluación de los pasajeros.',
            'route_sheet' => $routeSheet->fresh(),
        ]);
    }
}
