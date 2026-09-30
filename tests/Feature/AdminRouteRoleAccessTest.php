<?php

namespace Tests\Feature;

use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRouteRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_route_checks_each_role_and_multi_role_union_over_http(): void
    {
        $this->getJson('/api/admin/usuarios')->assertUnauthorized();

        foreach ([
            'docente' => 403,
            'responsable_facultad' => 403,
            'vicerrector' => 403,
            'conductor' => 403,
            'mecanico' => 403,
            'estudiante' => 403,
            'secretaria' => 200,
        ] as $role => $expectedStatus) {
            Sanctum::actingAs($this->makeUser([$role]));

            $this->getJson('/api/admin/usuarios')->assertStatus($expectedStatus);
        }

        Sanctum::actingAs($this->makeUser(['docente', 'responsable_facultad']));
        $this->getJson('/api/admin/usuarios')->assertForbidden();

        Sanctum::actingAs($this->makeUser(['docente', 'secretaria']));
        $this->getJson('/api/admin/usuarios')->assertOk();
    }

    /** @param list<string> $roleNames */
    private function makeUser(array $roleNames): User
    {
        $roles = collect($roleNames)->map(fn (string $name) => Role::query()->firstOrCreate(
            ['name' => $name],
            ['description' => 'Test role'],
        ));
        $primaryRole = $roles->first();

        static $sequence = 0;
        $sequence++;

        $user = User::create([
            'national_id' => str_pad((string) $sequence, 10, '0', STR_PAD_LEFT),
            'first_name' => 'Role',
            'last_name' => 'Test',
            'email' => "role-test-{$sequence}@example.invalid",
            'password' => 'Strong-Test-Password-123',
            'faculty_institution' => 'Test',
            'role_id' => $primaryRole->id,
            'is_active' => true,
        ]);

        $user->syncRoleNames($roleNames, $primaryRole->name);

        return $user;
    }
}
