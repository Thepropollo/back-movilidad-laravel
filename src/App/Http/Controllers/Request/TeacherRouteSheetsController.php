<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\RouteSheet;

class TeacherRouteSheetsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // Obtener hojas de ruta en estado 'pendiente_feedback'
        // donde el docente solicitante es el usuario autenticado
        // y el estado del pago no está aún verificado
        $sheets = RouteSheet::with(['vehicle', 'driver.user', 'request', 'compensation'])
            ->where('trip_status', 'pendiente_feedback')
            ->whereHas('request', function ($query) use ($user) {
                $query->where('requester_id', $user->id);
            })
            ->where(function ($query) {
                $query->whereDoesntHave('compensation')
                      ->orWhereHas('compensation', function ($q) {
                          $q->where('payment_status', 'pendiente_comprobante');
                      });
            })
            ->get();

        return response()->json($sheets);
    }
}
