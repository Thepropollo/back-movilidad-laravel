<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\RateConfiguration;

class RateConfigurationListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $rates = RateConfiguration::orderBy('rate_key')->get();
        return response()->json($rates);
    }
}
