<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Actions\RecordTripEvaluationAction;
use Illuminate\Http\Request;

class TripEvaluationStoreController extends Controller
{
    public function __invoke(Request $request, RecordTripEvaluationAction $action)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $request->validate([
            'hoja_ruta_id' => 'required|integer|exists:route_sheets,id',
            'calificacion_conductor' => 'required|integer|min:1|max:5',
            'calificacion_vehiculo' => 'required|integer|min:1|max:5',
            'comments' => 'nullable|string|max:1000',
        ]);

        $evaluation = $action->execute(
            routeSheetId: (int) $request->input('hoja_ruta_id'),
            passengerId: (int) $user->id,
            driverRating: (int) $request->input('calificacion_conductor'),
            vehicleRating: (int) $request->input('calificacion_vehiculo'),
            comments: $request->input('comments')
        );

        return response()->json([
            'message' => 'Muchas gracias por tu feedback. Evaluación registrada exitosamente.',
            'evaluation' => $evaluation,
        ], 201);
    }
}
