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

class TripEvaluationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_only_an_accepted_passenger_can_evaluate_a_trip_after_arrival_and_only_once(): void
    {
        $teacher = User::where('email', 'docente@uleam.edu.ec')->firstOrFail();
        $student = User::where('email', 'e1314433382@live.uleam.edu.ec')->firstOrFail();
        $sheet = $this->createArrivedSheet($teacher);
        PassengerManifest::create([
            'request_id' => $sheet->request_id,
            'user_id' => $student->id,
            'attended' => true,
            'invitation_status' => 'aceptado',
            'responded_at' => now(),
        ]);

        $this->actingAs($teacher)->postJson('/api/evaluaciones', [
            'hoja_ruta_id' => $sheet->id,
            'calificacion_conductor' => 5,
            'calificacion_vehiculo' => 5,
        ])->assertForbidden();

        $this->actingAs($student)->postJson('/api/evaluaciones', [
            'hoja_ruta_id' => $sheet->id,
            'calificacion_conductor' => 5,
            'calificacion_vehiculo' => 5,
        ])->assertCreated();

        $this->actingAs($student)->postJson('/api/evaluaciones', [
            'hoja_ruta_id' => $sheet->id,
            'calificacion_conductor' => 5,
            'calificacion_vehiculo' => 5,
        ])->assertUnprocessable();
    }

    private function createArrivedSheet(User $requester): RouteSheet
    {
        $secretariat = User::where('email', 'secretaria@uleam.edu.ec')->firstOrFail();
        $request = MobilizationRequest::create([
            'requester_id' => $requester->id,
            'mobilization_type' => 'interna',
            'origin' => 'MANTA',
            'destination' => 'PORTOVIEJO',
            'travel_reason' => 'Prueba de evaluación',
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
            'vehicle_id' => Vehicle::query()->value('id'),
            'driver_id' => Driver::query()->value('id'),
            'transport_chief_id' => $secretariat->id,
            'initial_mileage' => 100,
            'final_mileage' => 150,
            'trip_status' => 'pendiente_feedback',
            'driver_response' => 'aceptado',
        ]);
    }
}
