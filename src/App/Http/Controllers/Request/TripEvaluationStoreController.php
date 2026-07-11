<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\TripEvaluation;
use Domain\Workshop\Models\IssueLog;
use Carbon\Carbon;

class TripEvaluationStoreController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $request->validate([
            'hoja_ruta_id' => 'required|integer|exists:route_sheets,id',
            'pasajero_id' => 'required|integer|exists:users,id',
            'calificacion_conductor' => 'required|integer|min:1|max:5',
            'calificacion_vehiculo' => 'required|integer|min:1|max:5',
            'comments' => 'nullable|string',
        ]);

        $routeSheetId = (int) $request->input('hoja_ruta_id');
        $passengerId = (int) $request->input('pasajero_id');
        $driverRating = (int) $request->input('calificacion_conductor');
        $vehicleRating = (int) $request->input('calificacion_vehiculo');
        $comments = $request->input('comments');

        $routeSheet = RouteSheet::findOrFail($routeSheetId);

        // Check if this passenger already evaluated this route sheet
        $existing = TripEvaluation::where('route_sheet_id', $routeSheetId)
            ->where('passenger_id', $passengerId)
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Ya has enviado tu evaluación para este viaje.'], 422);
        }

        $evaluation = DB::transaction(function () use ($routeSheet, $passengerId, $driverRating, $vehicleRating, $comments) {
            // 1. Guardar evaluación
            $eval = TripEvaluation::create([
                'route_sheet_id' => $routeSheet->id,
                'passenger_id' => $passengerId,
                'driver_rating' => $driverRating,
                'vehicle_rating' => $vehicleRating,
                'comments' => $comments
            ]);

            // 2. Calcular promedio de calificación del vehículo
            $averageVehicleRating = TripEvaluation::where('route_sheet_id', $routeSheet->id)->avg('vehicle_rating');

            // 3. Si cae debajo de 3.0, trigger de alerta en libro de novedades
            if ($averageVehicleRating < 3.0) {
                $description = "Alerta automática: Pasajeros reportan bajo confort o desperfectos técnicos en el viaje ID {$routeSheet->id}";
                
                // Evitar alertas duplicadas
                $duplicate = IssueLog::where('route_sheet_id', $routeSheet->id)
                    ->where('description', 'like', 'Alerta automática%')
                    ->exists();

                if (!$duplicate) {
                    IssueLog::create([
                        'vehicle_id' => $routeSheet->vehicle_id,
                        'route_sheet_id' => $routeSheet->id,
                        'reporting_driver_id' => $passengerId, // usuario pasajero que gatilla la alerta
                        'breakdown_date' => Carbon::today(),
                        'description' => $description,
                        'status' => 'pendiente'
                    ]);
                }
            }

            return $eval;
        });

        return response()->json([
            'message' => 'Muchas gracias por tu feedback. Evaluación registrada exitosamente.',
            'evaluation' => $evaluation
        ], 201);
    }
}
