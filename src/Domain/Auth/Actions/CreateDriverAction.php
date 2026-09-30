<?php

namespace Domain\Auth\Actions;

use Domain\Auth\Models\Driver;
use Domain\Auth\Models\DriverLicense;
use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Domain\Auth\Support\RoleCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateDriverAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Driver
    {
        return DB::transaction(function () use ($data): Driver {
            $role = Role::query()->where('name', RoleCatalog::CONDUCTOR)->firstOrFail();
            $user = User::create([
                'national_id' => $data['national_id'],
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'faculty_institution' => 'DIRECCIÓN DE TRANSPORTE Y MOVILIDAD',
                'role_id' => $role->id,
                'is_active' => true,
            ]);
            $user->syncRoleNames([RoleCatalog::CONDUCTOR], RoleCatalog::CONDUCTOR);

            $driver = Driver::create([
                'user_id' => $user->id,
                'contract_type' => $data['contract_type'],
                'is_available' => true,
            ]);

            DriverLicense::create([
                'driver_id' => $driver->id,
                'license_type' => $data['license_type'],
                'current_points' => $data['current_points'],
                'expiration_date' => $data['expiration_date'],
            ]);

            return $driver->load(['user', 'licenses']);
        });
    }
}
