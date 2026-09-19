<?php

namespace Tests\Feature;

use Domain\Auth\Models\Driver;
use Domain\Auth\Models\User;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DemoAccountsAndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, list<string>>
     */
    private const ACCOUNTS = [
        'secretaria@uleam.edu.ec' => ['secretaria'],
        'docente@uleam.edu.ec' => ['docente'],
        'vicerrector@uleam.edu.ec' => ['vicerrector'],
        'conductor1@uleam.edu.ec' => ['conductor'],
        'mecanico@uleam.edu.ec' => ['mecanico'],
        'conductor.mecanico@uleam.edu.ec' => ['conductor', 'mecanico'],
        'decano@uleam.edu.ec' => ['docente', 'responsable_facultad'],
        'e1314433382@live.uleam.edu.ec' => ['estudiante'],
        'chofer2@test.com' => ['conductor'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_todas_las_cuentas_seed_pueden_iniciar_sesion(): void
    {
        foreach (self::ACCOUNTS as $email => $expectedRoles) {
            $response = $this->postJson('/api/login', [
                'email' => $email,
                'password' => 'password',
            ]);

            $response->assertOk()
                ->assertJsonPath('status', 'success')
                ->assertJsonPath('data.user.email', $email);

            $roles = collect($response->json('data.user.roles'))->pluck('name')->all();
            sort($roles);
            $expected = $expectedRoles;
            sort($expected);

            $this->assertSame($expected, $roles, "Roles incorrectos para {$email}");
            $this->assertNotEmpty($response->json('data.access_token'));
        }

        $this->postJson('/api/login', [
            'email' => 'estudiante@test.com',
            'password' => 'password',
        ])->assertUnauthorized();
    }

    public function test_flujo_interno_docente_secretaria_conductor(): void
    {
        $this->actingAsEmail('docente@uleam.edu.ec');
        $created = $this->postJson('/api/solicitudes', $this->tripPayload('interna', 'PORTOVIEJO PRUEBA INTERNA'))
            ->assertCreated()
            ->json('request');

        $this->assertSame('pendiente_secretaria', $created['status']);
        $requestId = $created['id'];

        $this->actingAsEmail('secretaria@uleam.edu.ec');
        $this->getJson('/api/solicitudes?status=pendiente_secretaria')
            ->assertOk()
            ->assertJsonFragment(['id' => $requestId]);

        $this->patchJson("/api/solicitudes/{$requestId}/autorizar-secretaria", [
            'action' => 'approve',
            'observation' => 'Autorizada para prueba interna.',
        ])->assertOk()
            ->assertJsonPath('request.status', 'autorizada_secretaria');

        $vehicleId = Vehicle::where('plate', 'MBA-1234')->value('id');
        $driverId = Driver::where('user_id', User::where('email', 'conductor1@uleam.edu.ec')->value('id'))->value('id');

        $sheet = $this->postJson('/api/hojas-ruta', [
            'request_id' => $requestId,
            'vehicle_id' => $vehicleId,
            'driver_id' => $driverId,
        ])->assertCreated()
            ->json('route_sheet');

        $this->actingAsEmail('conductor1@uleam.edu.ec');
        $this->getJson('/api/mis-viajes')->assertOk()->assertJsonFragment(['id' => $sheet['id']]);
        $this->patchJson("/api/hojas-ruta/{$sheet['id']}/responder", [
            'action' => 'accept',
        ])->assertOk()
            ->assertJsonPath('route_sheet.driver_response', 'aceptado');

        $this->actingAsEmail('mecanico@uleam.edu.ec');
        $components = collect($this->getJson('/api/inspecciones/componentes')->assertOk()->json())
            ->map(fn (array $row) => [
                'id' => $row['id'],
                'physical_condition' => 'BUENO',
            ])
            ->all();

        $this->postJson('/api/actas-entrega', [
            'route_sheet_id' => $sheet['id'],
            'registration_type' => 'salida',
            'fuel_level' => 'full',
            'checkpoint_mileage' => 45000,
            'components' => $components,
        ])->assertCreated();

        $this->actingAsEmail('docente@uleam.edu.ec');
        $this->getJson("/api/solicitudes/{$requestId}/flujo")
            ->assertOk()
            ->assertJsonPath('request.status', 'aprobada');
    }

    public function test_flujo_externo_pasa_por_vicerrectorado_y_dual_acepta(): void
    {
        $this->actingAsEmail('decano@uleam.edu.ec');
        $requestId = $this->postJson('/api/solicitudes', $this->tripPayload('externa', 'QUITO PRUEBA EXTERNA'))
            ->assertCreated()
            ->json('request.id');

        $this->actingAsEmail('secretaria@uleam.edu.ec');
        $this->patchJson("/api/solicitudes/{$requestId}/autorizar-secretaria", [
            'action' => 'approve',
        ])->assertOk()
            ->assertJsonPath('request.status', 'pendiente_rectorado');

        $this->actingAsEmail('vicerrector@uleam.edu.ec');
        $this->getJson('/api/solicitudes')->assertOk()->assertJsonFragment(['id' => $requestId]);
        $this->patchJson("/api/solicitudes/{$requestId}/aprobar-rectorado", [
            'action' => 'approve',
        ])->assertOk()
            ->assertJsonPath('request.status', 'aprobado_rectorado');

        $this->actingAsEmail('secretaria@uleam.edu.ec');
        $drivers = collect($this->getJson('/api/drivers')->assertOk()->json());
        $dual = $drivers->firstWhere('email', 'conductor.mecanico@uleam.edu.ec');
        $blocked = $drivers->firstWhere('email', 'chofer2@test.com');

        $this->assertNotNull($dual);
        $this->assertTrue($dual['is_selectable']);
        $this->assertNotNull($blocked);
        $this->assertFalse($blocked['is_selectable']);

        $vehicleId = Vehicle::where('plate', 'MBA-2468')->value('id');
        $sheet = $this->postJson('/api/hojas-ruta', [
            'request_id' => $requestId,
            'vehicle_id' => $vehicleId,
            'driver_id' => $dual['id'],
        ])->assertCreated()
            ->json('route_sheet');

        $this->actingAsEmail('conductor.mecanico@uleam.edu.ec');
        $this->getJson('/api/mis-viajes')->assertOk()->assertJsonFragment(['id' => $sheet['id']]);
        $this->getJson('/api/ordenes-taller')->assertOk();
        $this->patchJson("/api/hojas-ruta/{$sheet['id']}/responder", [
            'action' => 'accept',
        ])->assertOk()
            ->assertJsonPath('route_sheet.driver_response', 'aceptado');
    }

    public function test_flujo_universitario_con_invitacion_del_estudiante(): void
    {
        $this->actingAsEmail('docente@uleam.edu.ec');
        $requestId = $this->postJson('/api/solicitudes', $this->tripPayload('interna', 'BAHIA PRUEBA UNIVERSITARIA'))
            ->assertCreated()
            ->json('request.id');

        $student = User::where('email', 'e1314433382@live.uleam.edu.ec')->firstOrFail();
        $this->getJson('/api/estudiantes?q=Jean')->assertOk();
        $invitation = $this->postJson("/api/solicitudes/{$requestId}/participantes", [
            'user_id' => $student->id,
        ])->assertCreated()
            ->json('participant');

        $this->assertSame('invitado', $invitation['invitation_status']);

        $this->actingAsEmail('e1314433382@live.uleam.edu.ec');
        $this->getJson('/api/mis-invitaciones')->assertOk()->assertJsonFragment(['id' => $invitation['id']]);
        $this->patchJson("/api/participantes/{$invitation['id']}/responder", [
            'action' => 'accept',
        ])->assertOk()
            ->assertJsonPath('participant.invitation_status', 'aceptado');

        $this->actingAsEmail('secretaria@uleam.edu.ec');
        $this->patchJson("/api/solicitudes/{$requestId}/autorizar-secretaria", [
            'action' => 'approve',
        ])->assertOk()
            ->assertJsonPath('request.status', 'autorizada_secretaria');

        $this->actingAsEmail('decano@uleam.edu.ec');
        $this->getJson('/api/solicitudes')->assertOk()->assertJsonFragment(['id' => $requestId]);
    }

    public function test_mecanico_puede_abrir_orden_de_taller(): void
    {
        $mechanic = $this->actingAsEmail('mecanico@uleam.edu.ec');
        $vehicleId = Vehicle::where('plate', 'MBA-9012')->value('id');

        $this->getJson('/api/inspecciones/pendientes')->assertOk();
        $this->getJson('/api/insumos')->assertOk();
        $this->postJson('/api/ordenes-taller', [
            'vehicle_id' => $vehicleId,
            'responsible_mechanic_id' => $mechanic->id,
            'maintenance_type' => 'correctivo',
            'work_details' => 'Revisión de prueba para validar el módulo de taller.',
        ])->assertCreated()
            ->assertJsonPath('work_order.responsible_mechanic_id', $mechanic->id);
    }

    public function test_secretaria_rechaza_y_vicerrector_no_puede_aprobar_interna(): void
    {
        $this->actingAsEmail('docente@uleam.edu.ec');
        $requestId = $this->postJson('/api/solicitudes', $this->tripPayload('interna', 'CHONE RECHAZO'))
            ->assertCreated()
            ->json('request.id');

        $this->actingAsEmail('vicerrector@uleam.edu.ec');
        $this->patchJson("/api/solicitudes/{$requestId}/aprobar-rectorado")
            ->assertStatus(400);

        $this->actingAsEmail('secretaria@uleam.edu.ec');
        $this->patchJson("/api/solicitudes/{$requestId}/autorizar-secretaria", [
            'action' => 'reject',
            'observation' => 'Falta justificación académica.',
        ])->assertOk()
            ->assertJsonPath('request.status', 'rechazada');
    }

    private function actingAsEmail(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function tripPayload(string $type, string $destination): array
    {
        return [
            'mobilization_type' => $type,
            'origin' => 'MANTA',
            'destination' => $destination,
            'travel_reason' => "Prueba automatizada de flujo {$type}",
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '08:00',
            'return_date' => now()->addDays(4)->toDateString(),
            'return_time' => '18:00',
            'declaracion_fondos_aceptada' => true,
        ];
    }
}
