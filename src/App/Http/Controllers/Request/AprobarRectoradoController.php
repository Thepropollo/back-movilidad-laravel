<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\MobilizationRequest;

class AprobarRectoradoController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || !$user->role || $user->role->name !== 'rector') {
            return response()->json(['message' => 'Acceso denegado: Se requiere rol de Rector para autorizar esta comisión.'], 403);
        }

        $mobilizationRequest = MobilizationRequest::findOrFail($id);

        if ($mobilizationRequest->status !== 'pendiente_rectorado') {
            return response()->json(['message' => 'La solicitud no se encuentra en estado pendiente de aprobación del Rectorado.'], 400);
        }

        $action = $request->input('action', 'approve');

        if ($action === 'reject') {
            $request->validate([
                'justification' => 'required|string|max:500'
            ], [
                'justification.required' => 'Debe ingresar una justificación para rechazar la solicitud.'
            ]);

            $mobilizationRequest->update([
                'status' => 'rechazada',
                'rectorate_approver_id' => $user->id,
                'travel_reason' => $mobilizationRequest->travel_reason . "\n\n[RECHAZADO POR RECTORADO: " . $request->input('justification') . "]"
            ]);

            return response()->json([
                'message' => 'Solicitud rechazada exitosamente.',
                'request' => $mobilizationRequest
            ]);
        }

        // Aprobación
        $mobilizationRequest->update([
            'status' => 'aprobado_rectorado',
            'rectorate_approver_id' => $user->id
        ]);

        return response()->json([
            'message' => 'Solicitud aprobada por el Rectorado exitosamente.',
            'request' => $mobilizationRequest
        ]);
    }
}
