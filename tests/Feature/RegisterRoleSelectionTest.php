<?php

namespace Tests\Feature;

use Tests\TestCase;

class RegisterRoleSelectionTest extends TestCase
{
    public function test_public_registration_rejects_client_controlled_access_fields_without_database_access(): void
    {
        foreach (['id', 'role_id', 'role_name', 'roles', 'is_active'] as $field) {
            $response = $this->postJson('/api/register', [$field => $field === 'roles' ? ['secretaria'] : 'secretaria']);

            $response->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }
}
