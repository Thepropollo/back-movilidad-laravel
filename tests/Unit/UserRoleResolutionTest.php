<?php

namespace Tests\Unit;

use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
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
}
