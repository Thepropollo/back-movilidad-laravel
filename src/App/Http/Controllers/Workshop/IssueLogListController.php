<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Workshop\Models\IssueLog;
use Domain\Workshop\Models\WorkshopWorkOrder;

class IssueLogListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        // Obtener novedades pendientes o en revisión
        $issues = IssueLog::with(['vehicle', 'routeSheet.driver.user', 'reportingDriver'])
            ->whereIn('status', ['pendiente', 'en_revision'])
            ->orderBy('id', 'desc')
            ->get();

        // Obtener órdenes de trabajo activas
        $workOrders = WorkshopWorkOrder::with(['vehicle', 'issueLog', 'responsibleMechanic', 'supervisor'])
            ->whereNull('exit_date')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'issues' => $issues,
            'work_orders' => $workOrders
        ]);
    }
}
