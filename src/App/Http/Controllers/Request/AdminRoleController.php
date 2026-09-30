<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\Role;
use Illuminate\Http\Request;

class AdminRoleController extends Controller
{
    public function __invoke(Request $request)
    {
        $admin = $request->user();
        if (! $admin || ! $admin->hasRole('secretaria')) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return response()->json(Role::orderBy('name')->get());
    }
}
