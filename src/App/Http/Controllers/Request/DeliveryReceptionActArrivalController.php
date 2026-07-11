<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\DeliveryReceptionAct;

class DeliveryReceptionActArrivalController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $request->validate([
            'hoja_ruta_id' => 'required|integer|exists:route_sheets,id',
            'mecanico_o_guardia_id' => 'required|integer|exists:users,id',
            'kilometraje_garita' => 'required|integer',
            'nivel_combustible' => 'required|string|in:1/4,1/2,3/4,full',
        ], [
            'hoja_ruta_id.required' => 'La hoja de ruta es obligatoria.',
            'kilometraje_garita.required' => 'El kilometraje en garita es obligatorio.',
            'nivel_combustible.required' => 'El nivel de combustible es obligatorio.',
        ]);

        $hojaRutaId = (int) $request->input('hoja_ruta_id');
        $kilometraje = (int) $request->input('kilometraje_garita');
        $fuelLevel = $request->input('nivel_combustible');
        $mecanicoId = (int) $request->input('mecanico_o_guardia_id');

        $routeSheet = RouteSheet::findOrFail($hojaRutaId);
        $vehicle = $routeSheet->vehicle;

        if ($routeSheet->initial_mileage && $kilometraje < $routeSheet->initial_mileage) {
            return response()->json([
                'message' => "El kilometraje de llegada ({$kilometraje}) no puede ser menor al de salida ({$routeSheet->initial_mileage})."
            ], 422);
        }

        DB::transaction(function () use ($routeSheet, $vehicle, $mecanicoId, $kilometraje, $fuelLevel) {
            // Guardar acta en delivery_reception_acts
            DeliveryReceptionAct::create([
                'route_sheet_id' => $routeSheet->id,
                'mechanic_or_guard_id' => $mecanicoId,
                'registration_type' => 'llegada',
                'fuel_level' => $fuelLevel,
                'checkpoint_mileage' => $kilometraje,
                'general_observations' => 'Retorno de comisión registrado en garita.'
            ]);

            // Actualizar la hoja de ruta
            $routeSheet->update([
                'final_mileage' => $kilometraje,
                'trip_status' => 'pendiente_feedback'
            ]);

            // Liberar temporalmente el vehículo a disponible
            $vehicle->update([
                'operational_status' => 'disponible',
                'current_mileage' => $kilometraje
            ]);
        });

        return response()->json([
            'message' => 'Llegada registrada exitosamente. Pendiente de co-evaluación de los pasajeros.',
            'route_sheet' => $routeSheet->fresh()
        ]);
    }
}
