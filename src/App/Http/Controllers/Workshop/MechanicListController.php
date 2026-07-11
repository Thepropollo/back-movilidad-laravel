<?php

namespace App\Http\Controllers\Workshop;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\User;
use Domain\Auth\Models\Role;

class MechanicListController extends Controller
{
    public function __invoke()
    {
        $mechanicRole = Role::where('name', 'mecanico')->first();
        if ($mechanicRole) {
            $mechanics = User::where('role_id', $mechanicRole->id)->get();
        } else {
            $mechanics = User::all();
        }
        return response()->json($mechanics);
    }
}
