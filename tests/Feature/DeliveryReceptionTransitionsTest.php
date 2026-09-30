<?php

namespace Tests\Feature;

use Domain\Auth\Models\Driver;
use Domain\Auth\Models\User;
use Domain\Requests\Models\DeliveryReceptionAct;
use Domain\Requests\Models\ChecklistInventoryComponent;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryReceptionTransitionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_arrival_requires_an_in_progress_trip_and_uses_the_authenticated_actor(): void
    {
        $mechanic = User::where('email', 'mecanico@uleam.edu.ec')->firstOrFail();
        $forgedActor = User::where('email', 'docente@uleam.edu.ec')->firstOrFail();
        $sheet = $this->createSheet('en_ruta', 'aceptado');
        $vehicle = Vehicle::findOrFail($sheet->vehicle_id);
        $mileage = (int) $vehicle->current_mileage + 1;

        $this->actingAs($mechanic)->postJson('/api/actas-recepcion-llegada', [
            'hoja_ruta_id' => $sheet->id,
            'mecanico_o_guardia_id' => $forgedActor->id,
            'kilometraje_garita' => $mileage,
            'nivel_combustible' => 'full',
        ])->assertOk();

        $this->assertDatabaseHas('delivery_reception_acts', [
            'route_sheet_id' => $sheet->id,
            'registration_type' => 'llegada',
            'mechanic_or_guard_id' => $mechanic->id,
        ]);
        $this->assertSame('pendiente_feedback', $sheet->fresh()->trip_status);

        $this->actingAs($mechanic)->postJson('/api/actas-recepcion-llegada', [
            'hoja_ruta_id' => $sheet->id,
            'kilometraje_garita' => $mileage + 1,
            'nivel_combustible' => 'full',
        ])->assertUnprocessable();

        $this->assertSame(1, DeliveryReceptionAct::where('route_sheet_id', $sheet->id)
            ->where('registration_type', 'llegada')->count());
    }

    public function test_departure_inspection_rejects_an_unaccepted_assignment(): void
    {
        $mechanic = User::where('email', 'mecanico@uleam.edu.ec')->firstOrFail();
        $sheet = $this->createSheet('programado', 'pendiente');

        $this->actingAs($mechanic)->postJson('/api/actas-entrega', [
            'route_sheet_id' => $sheet->id,
            'registration_type' => 'salida',
            'fuel_level' => 'full',
            'checkpoint_mileage' => (int) Vehicle::findOrFail($sheet->vehicle_id)->current_mileage,
            'components' => [[
                'id' => ChecklistInventoryComponent::query()->value('id'),
                'physical_condition' => 'BUENO',
            ]],
        ])->assertUnprocessable();

        $this->assertSame(0, DeliveryReceptionAct::where('route_sheet_id', $sheet->id)->count());
    }

    private function createSheet(string $tripStatus, string $driverResponse): RouteSheet
    {
        $requester = User::where('email', 'docente@uleam.edu.ec')->firstOrFail();
        $secretariat = User::where('email', 'secretaria@uleam.edu.ec')->firstOrFail();
        $vehicle = Vehicle::query()->firstOrFail();
        $driver = Driver::query()->firstOrFail();
        $request = MobilizationRequest::create([
            'requester_id' => $requester->id,
            'mobilization_type' => 'interna',
            'origin' => 'MANTA',
            'destination' => 'PORTOVIEJO',
            'travel_reason' => 'Prueba de inspección',
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
            'driver_response' => $driverResponse,
        ]);
    }
}
