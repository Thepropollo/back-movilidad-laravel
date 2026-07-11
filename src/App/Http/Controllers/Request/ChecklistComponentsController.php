<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Models\ChecklistInventoryComponent;

class ChecklistComponentsController extends Controller
{
    public function __invoke(Request $request)
    {
        $components = ChecklistInventoryComponent::all();
        return response()->json($components);
    }
}
