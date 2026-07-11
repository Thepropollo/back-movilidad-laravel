<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\MobilizationRequest;

class SolicitudListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $roleName = $user->role ? $user->role->name : '';

        $query = MobilizationRequest::with(['requester', 'rectorateApprover']);

        if ($roleName === 'rector') {
            // Rector ve las pendientes de rectorado y las que ya aprobó/rechazó
            $query->whereIn('status', ['pendiente_rectorado', 'aprobado_rectorado', 'rechazada']);
        } elseif ($roleName === 'jefe_transporte') {
            // Jefe de Transporte ve todas las solicitudes que están listas para asignar recursos, o que ya fueron aprobadas
            $query->whereIn('status', ['pendiente', 'aprobado_rectorado', 'aprobada', 'rechazada']);
        } else {
            // Solicitante común o chofer ve sus propias solicitudes creadas
            $query->where('requester_id', $user->id);
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json($requests);
    }
}
