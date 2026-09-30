<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Domain\Workshop\Actions\CreateWorkOrderAction;
use Illuminate\Http\Request;

class CreateWorkOrderController extends Controller
{
    public function __invoke(Request $request, CreateWorkOrderAction $createWorkOrder)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $data = $request->validate([
            'issue_log_id' => 'nullable|integer|exists:issue_logs,id',
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'responsible_mechanic_id' => 'required|integer|exists:users,id',
            'maintenance_type' => 'required|string|in:preventivo,correctivo,cambio_aceite',
            'work_details' => 'required|string|max:2000',
        ], [
            'vehicle_id.required' => 'El vehículo es obligatorio.',
            'responsible_mechanic_id.required' => 'El mecánico responsable es obligatorio.',
            'maintenance_type.required' => 'El tipo de mantenimiento es obligatorio.',
            'work_details.required' => 'Los detalles del trabajo son obligatorios.',
        ]);

        $workOrder = $createWorkOrder->execute(
            issueLogId: isset($data['issue_log_id']) ? (int) $data['issue_log_id'] : null,
            vehicleId: (int) $data['vehicle_id'],
            mechanicId: (int) $data['responsible_mechanic_id'],
            supervisorId: (int) $user->id,
            maintenanceType: $data['maintenance_type'],
            workDetails: $data['work_details']
        );

        return response()->json([
            'message' => 'Orden de trabajo de taller generada con éxito.',
            'work_order' => $workOrder,
        ], 201);
    }
}
