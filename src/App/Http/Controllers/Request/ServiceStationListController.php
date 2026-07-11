<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\ServiceStation;

class ServiceStationListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $stations = ServiceStation::where('active_agreement', true)
            ->orderBy('commercial_name')
            ->get();

        return response()->json($stations);
    }
}
