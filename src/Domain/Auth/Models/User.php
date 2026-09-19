<?php

namespace Domain\Auth\Models;

use Domain\Auth\Support\RoleCatalog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use InvalidArgumentException;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['national_id', 'first_name', 'last_name', 'email', 'password', 'faculty_institution', 'job_title', 'role_id', 'is_active'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'users';

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        $this->loadMissing(['role', 'roles']);

        $names = $this->roles->pluck('name')->all();

        if ($names === [] && $this->role) {
            $names = [$this->role->name];
        }

        return RoleCatalog::canonicalizeMany($names);
    }

    public function hasRole(string|array $roles): bool
    {
        $wanted = RoleCatalog::canonicalizeMany((array) $roles);
        $owned = $this->roleNames();

        foreach ($wanted as $role) {
            if (in_array($role, $owned, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $roleNames
     */
    public function syncRoleNames(array $roleNames, ?string $primary = null): void
    {
        $next = RoleCatalog::canonicalizeMany($roleNames);

        if (! RoleCatalog::canAssign($this->roleNames(), $next)) {
            throw new InvalidArgumentException('Un mecánico puro no puede recibir el rol de conductor.');
        }

        $roleIds = Role::query()->whereIn('name', $next)->pluck('id');
        $this->roles()->sync($roleIds);

        $primaryName = RoleCatalog::canonicalize($primary) ?? ($next[0] ?? null);
        if ($primaryName) {
            $primaryRole = Role::query()->where('name', $primaryName)->first();
            if ($primaryRole) {
                $this->forceFill(['role_id' => $primaryRole->id])->save();
            }
        }

        $this->unsetRelation('role');
        $this->unsetRelation('roles');
        $this->load(['role', 'roles']);
    }

    public function toAuthArray(): array
    {
        $this->loadMissing(['role', 'roles']);

        $rolesPayload = $this->roles->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => RoleCatalog::canonicalize($role->name),
            'description' => $role->description,
        ])->values()->all();

        if ($rolesPayload === [] && $this->role) {
            $rolesPayload = [[
                'id' => $this->role->id,
                'name' => RoleCatalog::canonicalize($this->role->name),
                'description' => $this->role->description,
            ]];
        }

        return [
            'id' => $this->id,
            'national_id' => $this->national_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'faculty_institution' => $this->faculty_institution,
            'role_id' => $this->role_id,
            'role' => $this->role ? [
                'id' => $this->role->id,
                'name' => RoleCatalog::canonicalize($this->role->name),
                'description' => $this->role->description,
            ] : null,
            'roles' => $rolesPayload,
        ];
    }
}
