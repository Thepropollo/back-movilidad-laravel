<?php

namespace Domain\Requests\Actions;

use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\TripEvaluation;
use Domain\Workshop\Models\IssueLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordTripEvaluationAction
{
    public function execute(
        int $routeSheetId,
        int $passengerId,
        int $driverRating,
        int $vehicleRating,
        ?string $comments
    ): TripEvaluation {
        return DB::transaction(function () use ($routeSheetId, $passengerId, $driverRating, $vehicleRating, $comments) {
            $routeSheet = RouteSheet::query()
                ->with('request')
                ->lockForUpdate()
                ->findOrFail($routeSheetId);

            if ($routeSheet->trip_status !== 'pendiente_feedback') {
                throw ValidationException::withMessages([
                    'hoja_ruta_id' => ['Solo se puede evaluar un viaje que ya registró la llegada.'],
                ]);
            }

            $isAcceptedPassenger = $routeSheet->request?->passengers()
                ->where('user_id', $passengerId)
                ->where('invitation_status', 'aceptado')
                ->exists() ?? false;

            if (! $isAcceptedPassenger) {
                throw new AuthorizationException('Solo un participante que aceptó la invitación puede evaluar.');
            }

            $alreadyEvaluated = TripEvaluation::query()
                ->where('route_sheet_id', $routeSheet->id)
                ->where('passenger_id', $passengerId)
                ->exists();

            if ($alreadyEvaluated) {
                throw ValidationException::withMessages([
                    'hoja_ruta_id' => ['Ya has enviado tu evaluación para este viaje.'],
                ]);
            }

            $evaluation = TripEvaluation::create([
                'route_sheet_id' => $routeSheet->id,
                'passenger_id' => $passengerId,
                'driver_rating' => $driverRating,
                'vehicle_rating' => $vehicleRating,
                'comments' => $comments,
            ]);

            $averageVehicleRating = TripEvaluation::query()
                ->where('route_sheet_id', $routeSheet->id)
                ->avg('vehicle_rating');

            if ($averageVehicleRating < 3.0) {
                $hasAutomaticIssue = IssueLog::query()
                    ->where('route_sheet_id', $routeSheet->id)
                    ->where('description', 'like', 'Alerta automática%')
                    ->exists();

                if (! $hasAutomaticIssue) {
                    IssueLog::create([
                        'vehicle_id' => $routeSheet->vehicle_id,
                        'route_sheet_id' => $routeSheet->id,
                        'reporting_driver_id' => $passengerId,
                        'breakdown_date' => now()->toDateString(),
                        'description' => "Alerta automática: Pasajeros reportan bajo confort o desperfectos técnicos en el viaje ID {$routeSheet->id}",
                        'status' => 'pendiente',
                    ]);
                }
            }

            return $evaluation;
        });
    }
}
