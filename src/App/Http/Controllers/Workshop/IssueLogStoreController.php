<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Domain\Workshop\Actions\ReportVehicleIssueAction;
use Illuminate\Http\Request;

class IssueLogStoreController extends Controller
{
    public function __invoke(Request $request, ReportVehicleIssueAction $action)
    {
        $user = $request->user();
        $data = $request->validate([
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'route_sheet_id' => 'nullable|integer|exists:route_sheets,id',
            'description' => 'required|string|max:1000',
            'city' => 'nullable|string|max:100',
            'issue_type' => 'nullable|string|max:50',
            'breakdown_date' => 'nullable|date',
        ]);

        $issue = $action->execute(
            userId: (int) $user->id,
            vehicleId: (int) $data['vehicle_id'],
            routeSheetId: isset($data['route_sheet_id']) ? (int) $data['route_sheet_id'] : null,
            description: $data['description'],
            city: $data['city'] ?? null,
            issueType: $data['issue_type'] ?? null,
            breakdownDate: $data['breakdown_date'] ?? null
        );

        return response()->json(['message' => 'Novedad registrada.', 'issue' => $issue], 201);
    }
}
