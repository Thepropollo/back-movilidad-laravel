<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Domain\Requests\Models\RateConfiguration;
use Domain\Requests\DataTransferObjects\MobilizationRequestData;
use Domain\Requests\Actions\CreateMobilizationRequestAction;

class SolicitudStoreController extends Controller
{
    public function __construct(
        protected CreateMobilizationRequestAction $createAction
    ) {}

    public function __invoke(Request $request)
    {
        // 1. Validar la entrada de la solicitud
        $validator = Validator::make($request->all(), [
            'mobilization_type' => 'required|in:interna,externa',
            'origin' => 'nullable|string|max:100',
            'destination' => 'required|string|max:150',
            'travel_reason' => 'required|string|max:500',
            'departure_date' => 'required|date|after_or_equal:today',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'declaracion_fondos_aceptada' => 'nullable|boolean'
        ], [
            'departure_date.after_or_equal' => 'La fecha de salida no puede ser anterior a la fecha de hoy.',
            'return_date.after_or_equal' => 'La fecha de retorno debe ser igual o posterior a la fecha de salida.',
            'destination.required' => 'El destino de la movilización es requerido.',
            'travel_reason.required' => 'El motivo de viaje es requerido.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Pre-cálculo financiero
        $departure = Carbon::parse($request->input('departure_date'));
        $return = Carbon::parse($request->input('return_date'));
        $estimatedDays = $departure->diffInDays($return) + 1;

        $dailyAllowanceRate = RateConfiguration::where('rate_key', 'viatico_diario')->value('rate_value') ?? 80.00;
        $overtimeRate50 = RateConfiguration::where('rate_key', 'extra_50')->value('rate_value') ?? 5.00;

        // Estimar 4 horas extras diarias a la tasa del 50%
        $overtimeEstimate = $estimatedDays * 4 * $overtimeRate50;
        $projectedCost = ($estimatedDays * $dailyAllowanceRate) + $overtimeEstimate;

        // 3. Si no ha aceptado la declaración legal, responder con la simulación
        if (!$request->input('declaracion_fondos_aceptada')) {
            $formattedCost = number_format($projectedCost, 2);
            $formattedDaily = number_format($dailyAllowanceRate, 2);
            $formattedOvertime = number_format($overtimeEstimate, 2);

            $warningMessage = "DECLARACIÓN DE FONDOS REQUERIDA:\n\nDe conformidad con la normativa de la ULEAM, el costo proyectado de viáticos para esta comisión es de USD {$formattedCost} ({$estimatedDays} día(s) a razón de USD {$formattedDaily}/día más un estimado de USD {$formattedOvertime} en horas extras).\n\nAl confirmar esta solicitud, usted declara y certifica que existen los fondos respectivos en la partida presupuestaria de su facultad o unidad académica para cubrir este traslado.";

            return response()->json([
                'requires_confirmation' => true,
                'projected_cost' => $projectedCost,
                'estimated_days' => $estimatedDays,
                'daily_rate' => (float)$dailyAllowanceRate,
                'overtime_estimate' => $overtimeEstimate,
                'message' => $warningMessage
            ]);
        }

        // 4. Si ya está confirmada, proceder a guardar utilizando la acción
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $dto = MobilizationRequestData::fromRequest($request, $user->id, $estimatedDays, $projectedCost);
        $mobilizationRequest = $this->createAction->execute($dto);

        return response()->json([
            'message' => 'Solicitud de movilización registrada exitosamente.',
            'request' => $mobilizationRequest
        ], 201);
    }
}
