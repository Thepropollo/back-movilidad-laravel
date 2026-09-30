<?php

namespace Tests\Feature;

use Domain\Auth\Models\Driver;
use Domain\Auth\Models\User;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\PassengerManifest;
use Domain\Requests\Models\RouteSheet;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParticipantDataMinimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_participant_search_and_trip_map_omit_contact_and_identity_fields(): void
    {
        $teacher = User::where('email', 'docente@uleam.edu.ec')->firstOrFail();
        $student = User::where('email', 'e1314433382@live.uleam.edu.ec')->firstOrFail();
        $driverUser = User::where('email', 'conductor1@uleam.edu.ec')->firstOrFail();
        $secretariat = User::where('email', 'secretaria@uleam.edu.ec')->firstOrFail();
        $vehicle = Vehicle::query()->firstOrFail();
        $driver = Driver::where('user_id', $driverUser->id)->firstOrFail();
        $request = MobilizationRequest::create([
            'requester_id' => $teacher->id,
            'mobilization_type' => 'interna',
            'origin' => 'MANTA',
            'destination' => 'PORTOVIEJO',
            'travel_reason' => 'Prueba de minimización',
            'departure_date' => today()->toDateString(),
            'departure_time' => '09:00',
            'return_date' => today()->toDateString(),
            'return_time' => '12:00',
            'estimated_days' => 1,
            'projected_cost' => 0,
            'status' => 'aprobada',
        ]);
        PassengerManifest::create([
            'request_id' => $request->id,
            'user_id' => $student->id,
            'attended' => false,
            'invitation_status' => 'invitado',
        ]);
        $sheet = RouteSheet::create([
            'request_id' => $request->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'transport_chief_id' => $secretariat->id,
            'trip_status' => 'programado',
            'driver_response' => 'pendiente',
        ]);

        $participants = $this->actingAs($teacher)
            ->getJson("/api/solicitudes/{$request->id}/participantes")
            ->assertOk()
            ->assertJsonPath('participants.0.first_name', $student->first_name)
            ->assertJsonMissingPath('participants.0.user')
            ->assertJsonMissingPath('participants.0.email')
            ->assertJsonMissingPath('participants.0.national_id');
        $this->assertStringNotContainsString($student->email, $participants->getContent());
        $this->assertStringNotContainsString($student->national_id, $participants->getContent());

        $search = $this->actingAs($teacher)
            ->getJson('/api/estudiantes?q='.urlencode($student->first_name))
            ->assertOk()
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.national_id');
        $this->assertStringNotContainsString($student->email, $search->getContent());
        $this->assertStringNotContainsString($student->national_id, $search->getContent());

        $map = $this->actingAs($teacher)
            ->getJson("/api/mapas/viajes/{$sheet->id}")
            ->assertOk()
            ->assertJsonPath('driver.first_name', $driverUser->first_name)
            ->assertJsonMissingPath('driver.email')
            ->assertJsonMissingPath('driver.national_id');
        $this->assertStringNotContainsString($driverUser->email, $map->getContent());
        $this->assertStringNotContainsString($driverUser->national_id, $map->getContent());
    }
}
