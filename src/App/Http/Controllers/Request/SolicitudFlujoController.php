<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Support\RequestWorkflow;
use Illuminate\Http\Request;

class SolicitudFlujoController extends Controller
{
    public function __invoke(Request $request, int $id)
    {
        $user = $request->user();
        $mobilization = MobilizationRequest::with([
            'requester.role',
            'secretariaApprover',
            'rectorateApprover',
            'routeSheet.vehicle',
            'routeSheet.driver.user',
            'statusHistories.user',
            'passengers.user',
        ])->findOrFail($id);

        $allowed = $user->hasRole(['secretaria', 'jefe_transporte', 'vicerrector', 'rector'])
            || $mobilization->requester_id === $user->id
            || $mobilization->passengers()->where('user_id', $user->id)->exists()
            || optional($mobilization->routeSheet?->driver)->user_id === $user->id
            || (
                $user->hasRole(['responsable_facultad'])
                && optional($mobilization->requester)->faculty_institution === $user->faculty_institution
            );

        if (! $allowed) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        return response()->json([
            'request' => $mobilization,
            'timeline' => $mobilization->statusHistories,
            'phases' => RequestWorkflow::phases($mobilization),
        ]);
    }
}
