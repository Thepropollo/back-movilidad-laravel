<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Domain\Workshop\Models\IssueLog;
use Carbon\Carbon;

class CreateWorkOrderController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $request->validate([
            'issue_log_id' => 'nullable|integer|exists:issue_logs,id',
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'responsible_mechanic_id' => 'required|integer|exists:users,id',
            'maintenance_type' => 'required|string|in:preventivo,correctivo,cambio_aceite',
            'work_details' => 'required|string',
        ], [
            'vehicle_id.required' => 'El vehículo es obligatorio.',
            'responsible_mechanic_id.required' => 'El mecánico responsable es obligatorio.',
            'maintenance_type.required' => 'El tipo de mantenimiento es obligatorio.',
            'work_details.required' => 'Los detalles del trabajo son obligatorios.',
        ]);

        $issueLogId = $request->input('issue_log_id');

        // Crear la orden de trabajo
        $workOrder = WorkshopWorkOrder::create([
            'issue_log_id' => $issueLogId,
            'vehicle_id' => $request->input('vehicle_id'),
            'responsible_mechanic_id' => $request->input('responsible_mechanic_id'),
            'supervisor_id' => $user->id,
            'maintenance_type' => $request->input('maintenance_type'),
            'work_details' => $request->input('work_details'),
            'entry_date' => Carbon::now(),
            'exit_date' => null
        ]);

        // Si está amarrada a una novedad, cambiar estado a 'en_revision'
        if ($issueLogId) {
            IssueLog::where('id', $issueLogId)->update([
                'status' => 'en_revision'
            ]);
        }

        return response()->json([
            'message' => 'Orden de trabajo de taller generada con éxito.',
            'work_order' => $workOrder
        ], 201);
    }
}
