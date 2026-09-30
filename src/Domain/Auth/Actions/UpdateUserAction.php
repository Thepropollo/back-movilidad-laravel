<?php

namespace Domain\Auth\Actions;

use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Domain\Auth\Support\RoleCatalog;
use Illuminate\Support\Facades\DB;

class UpdateUserAction
{
    /**
     * Update profile and access fields atomically, retaining secondary roles
     * while replacing the primary role.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function execute(User $user, array $attributes, int $primaryRoleId, bool $isActive): User
    {
        $primaryRole = Role::query()->findOrFail($primaryRoleId);
        $primaryName = RoleCatalog::canonicalize($primaryRole->name);
        $user->load(['role', 'roles']);

        $currentNames = $user->roleNames();
        $currentPrimary = RoleCatalog::canonicalize($user->role?->name);
        if ($primaryName === null) {
            throw new \LogicException('El rol primario debe tener un nombre canónico.');
        }
        $nextNames = RoleCatalog::replacePrimaryRole($currentNames, $currentPrimary, $primaryName);

        $currentSet = $currentNames;
        $nextSet = $nextNames;
        sort($currentSet);
        sort($nextSet);
        $rolesChanged = $currentPrimary !== $primaryName || $currentSet !== $nextSet;
        $accessChanged = $rolesChanged
            || (bool) $user->is_active !== $isActive
            || array_key_exists('password', $attributes);

        unset($attributes['role_id'], $attributes['is_active']);

        return DB::transaction(function () use ($user, $attributes, $nextNames, $primaryName, $isActive, $accessChanged) {
            $user->fill($attributes);
            $user->forceFill(['is_active' => $isActive])->save();
            $user->syncRoleNames($nextNames, $primaryName);

            if ($accessChanged) {
                $user->tokens()->delete();
            }

            return $user->fresh(['role', 'roles']);
        });
    }
}
