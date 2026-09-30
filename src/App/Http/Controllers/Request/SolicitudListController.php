<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Support\RequestWorkflow;
use Illuminate\Http\Request;

class SolicitudListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $query = MobilizationRequest::with([
            'requester',
            'rectorateApprover',
            'secretariaApprover',
            'routeSheet.driver.user',
            'routeSheet.vehicle',
            'passengers',
        ]);

        if ($user->hasRole(['vicerrector', 'rector'])) {
            $query->whereIn('status', ['pendiente_rectorado', 'aprobado_rectorado', 'rechazada', 'aprobada']);
        } elseif ($user->hasRole(['secretaria', 'jefe_transporte'])) {
            // ve todo el flujo operativo
        } elseif ($user->hasRole(['responsable_facultad'])) {
            $query->whereHas('requester', function ($q) use ($user) {
                $q->where('faculty_institution', $user->faculty_institution);
            });
        } else {
            $query->where('requester_id', $user->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('from')) {
            $query->whereDate('departure_date', '>=', $request->query('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('departure_date', '<=', $request->query('to'));
        }

        $requests = $query->orderBy('created_at', 'desc')->get();

        return response()->json(
            $requests->map(function (MobilizationRequest $row) {
                $payload = $row->toArray();
                $payload['phases'] = RequestWorkflow::phases($row);

                return $payload;
            })->values()
        );
    }
}
