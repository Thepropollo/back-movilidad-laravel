<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureUserHasRole;
use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class EnsureUserHasRoleTest extends TestCase
{
    public function test_each_non_secretariat_role_is_denied_secretariat_access(): void
    {
        foreach (['docente', 'responsable_facultad', 'vicerrector', 'conductor', 'mecanico', 'estudiante'] as $roleName) {
            $response = $this->authorizeAs([$roleName], ['secretaria']);

            $this->assertSame(403, $response->getStatusCode(), "{$roleName} must be denied");
        }
    }

    public function test_secretariat_role_and_multi_role_union_are_allowed(): void
    {
        $response = $this->authorizeAs(['secretaria'], ['jefe_transporte']);
        $this->assertSame(204, $response->getStatusCode());

        $response = $this->authorizeAs(['docente', 'secretaria'], ['secretaria']);
        $this->assertSame(204, $response->getStatusCode());
    }

    /** @param list<string> $ownedRoles
     *  @param list<string> $requiredRoles
     */
    private function authorizeAs(array $ownedRoles, array $requiredRoles): \Symfony\Component\HttpFoundation\Response
    {
        $user = new User;
        $roles = collect($ownedRoles)->map(fn (string $name) => new Role(['name' => $name]));
        $user->setRelation('role', $roles->first());
        $user->setRelation('roles', $roles);
        $request = Request::create('/api/admin/usuarios');
        $request->setUserResolver(fn () => $user);

        return app(EnsureUserHasRole::class)->handle(
            $request,
            fn () => response()->noContent(204),
            ...$requiredRoles
        );
    }
}
