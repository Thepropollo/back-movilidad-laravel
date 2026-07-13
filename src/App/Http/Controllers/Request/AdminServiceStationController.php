<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\ServiceStation;
use Domain\Auth\Models\SystemLog;

class AdminServiceStationController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return response()->json(ServiceStation::orderBy('commercial_name')->get());
    }

    public function store(Request $request)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $request->validate([
            'commercial_name' => 'required|string|max:150',
            'ruc' => 'required|string|size:13|unique:service_stations,ruc',
            'address' => 'required|string|max:255',
        ], [
            'ruc.required' => 'El RUC es obligatorio.',
            'ruc.size' => 'El RUC debe tener exactamente 13 dígitos.',
            'ruc.unique' => 'El RUC ingresado ya está registrado por otra estación.',
        ]);

        $station = ServiceStation::create([
            'commercial_name' => $request->commercial_name,
            'ruc' => $request->ruc,
            'address' => $request->address,
            'active_agreement' => true
        ]);

        $actionMsg = "REGISTRÓ_ESTACIÓN: {$station->commercial_name} (RUC: {$station->ruc})";
        if (strlen($actionMsg) > 100) {
            $actionMsg = substr($actionMsg, 0, 97) . '...';
        }

        SystemLog::create([
            'user_id' => $admin->id,
            'action' => $actionMsg,
            'affected_table' => 'service_stations',
            'record_id' => $station->id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Estación de servicio registrada con éxito.',
            'station' => $station
        ], 210);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $station = ServiceStation::findOrFail($id);

        $request->validate([
            'commercial_name' => 'required|string|max:150',
            'ruc' => "required|string|size:13|unique:service_stations,ruc,{$id}",
            'address' => 'required|string|max:255',
            'active_agreement' => 'required|boolean'
        ], [
            'ruc.required' => 'El RUC es obligatorio.',
            'ruc.size' => 'El RUC debe tener exactamente 13 dígitos.',
            'ruc.unique' => 'El RUC ingresado ya está registrado por otra estación.',
        ]);

        $station->update([
            'commercial_name' => $request->commercial_name,
            'ruc' => $request->ruc,
            'address' => $request->address,
            'active_agreement' => $request->active_agreement
        ]);

        $actionMsg = "ACTUALIZÓ_ESTACIÓN: {$station->commercial_name} (RUC: {$station->ruc})";
        if (strlen($actionMsg) > 100) {
            $actionMsg = substr($actionMsg, 0, 97) . '...';
        }

        SystemLog::create([
            'user_id' => $admin->id,
            'action' => $actionMsg,
            'affected_table' => 'service_stations',
            'record_id' => $station->id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Estación de servicio actualizada con éxito.',
            'station' => $station
        ]);
    }

    public function toggleConvenio(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $station = ServiceStation::findOrFail($id);
        $newStatus = !$station->active_agreement;

        $station->update([
            'active_agreement' => $newStatus
        ]);

        $prefix = $newStatus ? "ACTIVÓ_CONVENIO: " : "DESACTIVÓ_CONVENIO: ";
        $actionMsg = "{$prefix}{$station->commercial_name} (RUC: {$station->ruc})";
        if (strlen($actionMsg) > 100) {
            $actionMsg = substr($actionMsg, 0, 97) . '...';
        }

        SystemLog::create([
            'user_id' => $admin->id,
            'action' => $actionMsg,
            'affected_table' => 'service_stations',
            'record_id' => $station->id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Convenio de estación de servicio actualizado con éxito.',
            'station' => $station
        ]);
    }
}
