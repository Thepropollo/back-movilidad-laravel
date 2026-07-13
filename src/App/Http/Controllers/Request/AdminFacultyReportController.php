<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Domain\Requests\Models\MobilizationRequest;
use Carbon\Carbon;

class AdminFacultyReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $query = MobilizationRequest::join('users', 'mobilization_requests.requester_id', '=', 'users.id')
            ->select(
                'users.faculty_institution as faculty',
                DB::raw('COUNT(mobilization_requests.id) as total_trips'),
                DB::raw('SUM(mobilization_requests.projected_cost) as total_cost')
            )
            ->groupBy('users.faculty_institution')
            ->orderBy('total_cost', 'desc');

        if ($startDate && $endDate) {
            $query->whereBetween('mobilization_requests.departure_date', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }

        $report = $query->get();

        return response()->json($report);
    }
}
