<?php

namespace Tests\Feature;

use Domain\Auth\Models\Driver;
use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserDeletionSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_driver_with_route_history_is_deactivated_instead_of_deleted(): void
    {
        $secretariatRole = Role::where('name', 'secretaria')->firstOrFail();
        $driverRole = Role::where('name', 'conductor')->firstOrFail();
        $secretariat = $this->makeUser('secretaria', $secretariatRole->id);
        $driverUser = $this->makeUser('conductor', $driverRole->id);

        $driver = Driver::create([
            'user_id' => $driverUser->id,
            'contract_type' => 'LOSEP',
            'is_available' => true,
        ]);
        $vehicle = Vehicle::create([
            'plate' => 'TEST-01',
            'brand' => 'Test',
            'model' => 'Unit',
            'year' => 2025,
            'color' => 'White',
            'fuel_type' => 'diesel',
        ]);
        $request = MobilizationRequest::create([
            'requester_id' => $secretariat->id,
            'mobilization_type' => 'interna',
            'destination' => 'Test destination',
            'travel_reason' => 'Test history retention',
            'departure_date' => '2026-10-01',
            'return_date' => '2026-10-01',
            'estimated_days' => 1,
            'projected_cost' => 0,
        ]);
        RouteSheet::create([
            'request_id' => $request->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'transport_chief_id' => $secretariat->id,
            'trip_status' => 'finalizado',
        ]);

        $this->actingAs($secretariat)
            ->deleteJson('/api/admin/usuarios/'.$driverUser->id)
            ->assertOk()
            ->assertJsonPath('soft_deleted', true);

        $this->assertDatabaseHas('users', [
            'id' => $driverUser->id,
            'is_active' => false,
        ]);
    }

    private function makeUser(string $suffix, int $roleId): User
    {
        return User::create([
            'national_id' => 'ID'.str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
            'first_name' => ucfirst($suffix),
            'last_name' => 'Test',
            'email' => $suffix.'+'.uniqid().'@example.invalid',
            'password' => 'A-Strong-Test-Password-1',
            'faculty_institution' => 'Test',
            'role_id' => $roleId,
        ]);
    }
}
