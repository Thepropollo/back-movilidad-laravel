<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\ServiceStation;
use Domain\Auth\Models\SystemLog;

class ServiceStationToggleController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || $user->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $station = ServiceStation::findOrFail($id);
        $oldStatus = $station->active_agreement;
        $newStatus = !$oldStatus;

        $station->update([
            'active_agreement' => $newStatus
        ]);

        // Registrar en system_logs
        $prefix = $newStatus ? "ACTIVÓ_CONVENIO: " : "DESACTIVÓ_CONVENIO: ";
        $actionMsg = "{$prefix}{$station->commercial_name} (RUC: {$station->ruc})";
        if (strlen($actionMsg) > 100) {
            $actionMsg = substr($actionMsg, 0, 97) . '...';
        }

        SystemLog::create([
            'user_id' => $user->id,
            'action' => $actionMsg,
            'affected_table' => 'service_stations',
            'record_id' => $station->id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => "Convenio de la estación de servicio actualizado con éxito.",
            'station' => $station
        ]);
    }
}
