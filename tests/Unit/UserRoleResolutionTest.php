<?php

namespace Tests\Unit;

use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Domain\Auth\Support\RoleCatalog;
use Tests\TestCase;

class UserRoleResolutionTest extends TestCase
{
    public function test_canonical_secretaria_role_satisfies_legacy_alias(): void
    {
        $user = new User;
        $secretaria = new Role(['name' => 'secretaria']);
        $user->setRelation('role', $secretaria);
        $user->setRelation('roles', collect([$secretaria]));

        $this->assertTrue($user->hasRole('jefe_transporte'));
        $this->assertFalse($user->hasRole('vicerrector'));
    }

    public function test_role_permissions_are_the_union_of_loaded_roles(): void
    {
        $user = new User;
        $docente = new Role(['name' => 'docente']);
        $facultad = new Role(['name' => 'responsable_facultad']);
        $user->setRelation('role', $docente);
        $user->setRelation('roles', collect([$docente, $facultad]));

        $this->assertTrue($user->hasRole(['docente', 'responsable_facultad']));
        $this->assertTrue($user->hasRole('responsable_facultad'));
        $this->assertFalse($user->hasRole('secretaria'));
    }

    public function test_replacing_primary_role_preserves_secondary_roles_and_drops_revoked_primary(): void
    {
        $next = RoleCatalog::replacePrimaryRole(
            ['docente', 'responsable_facultad'],
            'solicitante',
            'vicerrector'
        );

        $this->assertSame(['responsable_facultad', 'vicerrector'], $next);
    }

    public function test_public_registration_has_only_the_least_privileged_role(): void
    {
        $this->assertSame('estudiante', RoleCatalog::publicRegistrationRole());
    }
}
