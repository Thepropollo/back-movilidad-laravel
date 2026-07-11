<?php

namespace Domain\Auth\Actions;

use Domain\Auth\Models\User;
use Domain\Auth\Models\Role;
use Domain\Auth\DataTransferObjects\UserData;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    /**
     * Ejecuta el registro de un nuevo usuario en el sistema.
     */
    public function execute(UserData $data): User
    {
        $roleId = $data->role_id;

        if (!$roleId && $data->role_name) {
            $role = Role::where('name', $data->role_name)->first();
            if ($role) {
                $roleId = $role->id;
            }
        }

        if (!$roleId) {
            $defaultRole = Role::where('name', 'solicitante')->first();
            $roleId = $defaultRole ? $defaultRole->id : null;
        }

        return User::create([
            'national_id' => $data->national_id,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'faculty_institution' => $data->faculty_institution,
            'role_id' => $roleId
        ]);
    }
}
