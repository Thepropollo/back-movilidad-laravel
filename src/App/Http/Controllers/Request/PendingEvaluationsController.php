<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\RouteSheet;

class PendingEvaluationsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $pending = RouteSheet::with(['vehicle', 'driver.user', 'request'])
            ->where('trip_status', 'pendiente_feedback')
            ->whereHas('request.passengers', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->whereDoesntHave('evaluations', function ($query) use ($user) {
                $query->where('passenger_id', $user->id);
            })
            ->get();

        return response()->json($pending);
    }
}
