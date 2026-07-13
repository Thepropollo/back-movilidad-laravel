<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Auth\Models\Role;

class AdminRoleController extends Controller
{
    public function __invoke(Request $request)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return response()->json(Role::orderBy('name')->get());
    }
}
