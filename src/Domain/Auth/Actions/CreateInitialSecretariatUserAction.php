<?php

namespace Domain\Auth\Actions;

use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class CreateInitialSecretariatUserAction
{
    /** @param array{national_id: string, first_name: string, last_name: string, email: string, password: string, faculty_institution: string} $data */
    public function execute(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $secretariaRole = Role::query()->where('name', 'secretaria')->lockForUpdate()->first();
            if (! $secretariaRole) {
                throw new RuntimeException('El rol secretaria no existe; ejecute primero las migraciones.');
            }

            $secretariaRoleIds = Role::query()
                ->whereIn('name', ['secretaria', 'jefe_transporte'])
                ->pluck('id');

            $alreadyProvisioned = User::query()
                ->whereIn('role_id', $secretariaRoleIds)
                ->orWhereHas('roles', fn ($query) => $query->whereIn('roles.id', $secretariaRoleIds))
                ->exists();

            if ($alreadyProvisioned) {
                throw new RuntimeException('Ya existe una cuenta con funciones de Secretaría; no se creó otra.');
            }

            $user = User::create([
                'national_id' => $data['national_id'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => strtolower($data['email']),
                'password' => Hash::make($data['password']),
                'faculty_institution' => $data['faculty_institution'],
                'role_id' => $secretariaRole->id,
                'is_active' => true,
            ]);

            $user->syncRoleNames(['secretaria'], 'secretaria');

            return $user;
        });
    }
}
