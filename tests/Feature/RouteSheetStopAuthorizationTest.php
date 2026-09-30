<?php

namespace Tests\Feature;

use Domain\Auth\Models\Driver;
use Domain\Auth\Models\User;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteSheetStopAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_only_assigned_driver_can_add_stops_during_an_active_trip(): void
    {
        $driverUser = User::where('email', 'conductor1@uleam.edu.ec')->firstOrFail();
        $teacher = User::where('email', 'docente@uleam.edu.ec')->firstOrFail();
        $sheet = $this->createSheet($driverUser, 'en_ruta');
        $vehicle = Vehicle::findOrFail($sheet->vehicle_id);

        $payload = [
            'location' => 'Portoviejo',
            'odometer_km' => (int) $vehicle->current_mileage + 1,
        ];

        $this->actingAs($teacher)->postJson("/api/hojas-ruta/{$sheet->id}/paradas", $payload)
            ->assertForbidden();

        $this->actingAs($driverUser)->postJson("/api/hojas-ruta/{$sheet->id}/paradas", $payload)
            ->assertCreated()
            ->assertJsonPath('stop.sequence', 1);

        $this->assertSame($payload['odometer_km'], $sheet->fresh()->final_mileage);
    }

    public function test_stops_cannot_be_added_before_departure_or_after_trip_closure(): void
    {
        $driverUser = User::where('email', 'conductor1@uleam.edu.ec')->firstOrFail();
        $sheet = $this->createSheet($driverUser, 'programado');

        $this->actingAs($driverUser)->postJson("/api/hojas-ruta/{$sheet->id}/paradas", [
            'location' => 'Portoviejo',
        ])->assertUnprocessable();
    }

    private function createSheet(User $driverUser, string $tripStatus): RouteSheet
    {
        $secretariat = User::where('email', 'secretaria@uleam.edu.ec')->firstOrFail();
        $vehicle = Vehicle::query()->firstOrFail();
        $driver = Driver::where('user_id', $driverUser->id)->firstOrFail();
        $request = MobilizationRequest::create([
            'requester_id' => User::where('email', 'docente@uleam.edu.ec')->value('id'),
            'mobilization_type' => 'interna',
            'origin' => 'MANTA',
            'destination' => 'PORTOVIEJO',
            'travel_reason' => 'Prueba de parada',
            'departure_date' => today()->toDateString(),
            'departure_time' => '09:00',
            'return_date' => today()->toDateString(),
            'return_time' => '12:00',
            'estimated_days' => 1,
            'projected_cost' => 0,
            'status' => 'aprobada',
        ]);

        return RouteSheet::create([
            'request_id' => $request->id,
            'vehicle_id' => $vehicle->id,
            'driver_id' => $driver->id,
            'transport_chief_id' => $secretariat->id,
            'initial_mileage' => (int) $vehicle->current_mileage,
            'trip_status' => $tripStatus,
            'driver_response' => 'aceptado',
        ]);
    }
}
