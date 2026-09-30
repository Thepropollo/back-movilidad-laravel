<?php

namespace Domain\Auth\Actions;

use Domain\Auth\DataTransferObjects\UserData;
use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Domain\Auth\Support\RoleCatalog;
use Illuminate\Support\Facades\Hash;

class RegisterUserAction
{
    public function execute(UserData $data): User
    {
        $roleName = RoleCatalog::publicRegistrationRole();

        // Ignorar role_id del cliente para evitar escalada de privilegios.
        $role = Role::query()->where('name', $roleName)->first()
            ?? Role::query()->where('name', RoleCatalog::DOCENTE)->first();

        if (! $role) {
            throw ValidationException::withMessages([
                'role_name' => ['No hay roles configurados en el sistema.'],
            ]);
        }

        $user = User::create([
            'national_id' => $data->national_id,
            'first_name' => $data->first_name,
            'last_name' => $data->last_name,
            'email' => $data->email,
            'password' => Hash::make($data->password),
            'faculty_institution' => $data->faculty_institution,
            'role_id' => $role->id,
        ]);

        $user->syncRoleNames([$roleName], $roleName);

        return $user->load(['role', 'roles']);
    }
}
