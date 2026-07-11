<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Workshop\Models\SupplyInventory;

class SupplyInventoryListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $search = $request->query('q');

        $query = SupplyInventory::query();

        if ($search) {
            $query->where('supply_name', 'ilike', "%{$search}%")
                  ->orWhere('supply_name', 'like', "%{$search}%");
        }

        $supplies = $query->orderBy('supply_name')->limit(20)->get();

        return response()->json($supplies);
    }
}
