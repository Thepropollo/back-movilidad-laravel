<?php

namespace Tests\Feature;

use Domain\Auth\Models\Driver;
use Domain\Auth\Models\User;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueLogAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_driver_can_report_only_against_their_assigned_vehicle_and_trip(): void
    {
        $driverUser = User::where('email', 'conductor1@uleam.edu.ec')->firstOrFail();
        $driver = Driver::where('user_id', $driverUser->id)->firstOrFail();
        $vehicle = Vehicle::query()->firstOrFail();
        $otherVehicle = Vehicle::query()->whereKeyNot($vehicle->id)->firstOrFail();
        $sheet = $this->createAcceptedSheet($driver, $vehicle);

        $this->actingAs($driverUser)->postJson('/api/novedades', [
            'vehicle_id' => $otherVehicle->id,
            'route_sheet_id' => $sheet->id,
            'description' => 'Prueba de objeto ajeno',
        ])->assertForbidden();

        $this->actingAs($driverUser)->postJson('/api/novedades', [
            'vehicle_id' => $vehicle->id,
            'route_sheet_id' => $sheet->id,
            'description' => 'Prueba del vehículo asignado',
        ])->assertCreated()
            ->assertJsonPath('issue.reporting_driver_id', $driverUser->id)
            ->assertJsonPath('issue.route_sheet_id', $sheet->id);
    }

    private function createAcceptedSheet(Driver $driver, Vehicle $vehicle): RouteSheet
    {
        $requester = User::where('email', 'docente@uleam.edu.ec')->firstOrFail();
        $secretariat = User::where('email', 'secretaria@uleam.edu.ec')->firstOrFail();
        $request = MobilizationRequest::create([
            'requester_id' => $requester->id,
            'mobilization_type' => 'interna',
            'origin' => 'MANTA',
            'destination' => 'PORTOVIEJO',
            'travel_reason' => 'Prueba de novedad',
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
            'trip_status' => 'en_ruta',
            'driver_response' => 'aceptado',
        ]);
    }
}
