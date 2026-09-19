<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Domain\Requests\Actions\CreateMobilizationRequestAction;
use Domain\Requests\DataTransferObjects\MobilizationRequestData;
use Domain\Requests\Models\RateConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
            'destination_address' => 'nullable|string|max:255',
            'destination_latitude' => 'nullable|numeric|between:-90,90',
            'destination_longitude' => 'nullable|numeric|between:-180,180',
            'travel_reason' => 'required|string|max:500',
            'departure_date' => 'required|date|after_or_equal:today',
            'departure_time' => 'required|date_format:H:i',
            'return_date' => 'required|date|after_or_equal:departure_date',
            'return_time' => 'required|date_format:H:i',
            'declaracion_fondos_aceptada' => 'nullable|boolean',
            'occupant_count' => 'nullable|integer|min:1|max:80',
            'communication_number' => 'nullable|string|max:80',
            'activity_type' => 'nullable|string|max:50',
            'academic_program' => 'nullable|string|max:150',
            'public_servants_count' => 'nullable|integer|min:0|max:80',
        ], [
            'departure_date.after_or_equal' => 'La fecha de salida no puede ser anterior a la fecha de hoy.',
            'return_date.after_or_equal' => 'La fecha de retorno debe ser igual o posterior a la fecha de salida.',
            'departure_time.required' => 'La hora de salida es requerida.',
            'departure_time.date_format' => 'La hora de salida debe tener un formato válido (HH:mm).',
            'return_time.required' => 'La hora de retorno es requerida.',
            'return_time.date_format' => 'La hora de retorno debe tener un formato válido (HH:mm).',
            'destination.required' => 'El destino de la movilización es requerido.',
            'travel_reason.required' => 'El motivo de viaje es requerido.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Pre-cálculo financiero
        $departure = Carbon::parse($request->input('departure_date'))->startOfDay();
        $return = Carbon::parse($request->input('return_date'))->startOfDay();
        $estimatedDays = (int) $departure->diffInDays($return) + 1;

        $legacyDailyRate = RateConfiguration::where('rate_key', 'viatico_diario')->value('rate_value');
        $lodgingRate = RateConfiguration::where('rate_key', 'alojamiento_diario')->value('rate_value');
        $foodRate = RateConfiguration::where('rate_key', 'alimentacion_diaria')->value('rate_value');
        $dailyAllowanceRate = $lodgingRate !== null || $foodRate !== null
            ? (float) ($lodgingRate ?? 0) + (float) ($foodRate ?? 0)
            : (float) ($legacyDailyRate ?? 80.00);
        $overtimeRate50 = RateConfiguration::where('rate_key', 'extra_50')->value('rate_value') ?? 5.00;

        // Estimar 4 horas extras diarias a la tasa del 50%
        $overtimeEstimate = $estimatedDays * 4 * $overtimeRate50;
        $projectedCost = ($estimatedDays * $dailyAllowanceRate) + $overtimeEstimate;

        // 3. Si no ha aceptado la declaración legal, responder con la simulación
        if (! $request->input('declaracion_fondos_aceptada')) {
            $formattedCost = number_format($projectedCost, 2);
            $formattedDaily = number_format($dailyAllowanceRate, 2);
            $formattedOvertime = number_format($overtimeEstimate, 2);

            $breakdown = $lodgingRate !== null || $foodRate !== null
                ? "USD ".number_format((float) ($lodgingRate ?? 0), 2)." de alojamiento + USD ".number_format((float) ($foodRate ?? 0), 2)." de alimentación por día"
                : "USD {$formattedDaily} por día";
            $warningMessage = "DECLARACIÓN DE FONDOS REQUERIDA:\n\nDe conformidad con la normativa de la ULEAM, el costo proyectado de viáticos para esta comisión es de USD {$formattedCost} ({$estimatedDays} día(s), {$breakdown}, más un estimado de USD {$formattedOvertime} en horas extras).\n\nAl confirmar esta solicitud, usted declara y certifica que existen los fondos respectivos en la partida presupuestaria de su facultad o unidad académica para cubrir este traslado.";

            return response()->json([
                'requires_confirmation' => true,
                'projected_cost' => $projectedCost,
                'estimated_days' => $estimatedDays,
                'daily_rate' => (float) $dailyAllowanceRate,
                'lodging_rate' => $lodgingRate !== null ? (float) $lodgingRate : null,
                'food_rate' => $foodRate !== null ? (float) $foodRate : null,
                'overtime_estimate' => $overtimeEstimate,
                'message' => $warningMessage,
            ]);
        }

        // 4. Si ya está confirmada, proceder a guardar utilizando la acción
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $dto = MobilizationRequestData::fromRequest($request, $user->id, $estimatedDays, $projectedCost);
        $mobilizationRequest = $this->createAction->execute($dto);

        return response()->json([
            'message' => 'Solicitud de movilización registrada exitosamente.',
            'request' => $mobilizationRequest,
        ], 201);
    }
}
