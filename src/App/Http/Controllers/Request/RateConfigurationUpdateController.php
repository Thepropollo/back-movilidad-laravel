<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\RateConfiguration;
use Domain\Auth\Models\SystemLog;

class RateConfigurationUpdateController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $request->validate([
            'rate_value' => 'required|numeric|min:0.01',
        ], [
            'rate_value.required' => 'El valor de la tarifa es obligatorio.',
            'rate_value.numeric' => 'La tarifa debe ser un número decimal válido.',
        ]);

        $rate = RateConfiguration::findOrFail($id);
        $oldValue = $rate->rate_value;
        $newValue = (float) $request->input('rate_value');

        $rate->update([
            'rate_value' => $newValue
        ]);

        // Registrar en system_logs
        $actionMsg = "Modificó tarifa {$rate->rate_key} de {$oldValue} a {$newValue}";
        if (strlen($actionMsg) > 100) {
            $actionMsg = substr($actionMsg, 0, 97) . '...';
        }

        SystemLog::create([
            'user_id' => $user->id,
            'action' => $actionMsg,
            'affected_table' => 'rate_configurations',
            'record_id' => $rate->id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => "Tarifa '{$rate->rate_key}' actualizada con éxito.",
            'rate' => $rate
        ]);
    }
}
