<?php

namespace Tests\Feature;

use Domain\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstitutionalReportsAndDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_secretaria_dashboard_incluye_cola_y_kpis(): void
    {
        $this->actingAsEmail('secretaria@uleam.edu.ec');

        $this->getJson('/api/dashboard/metrics?focus=secretaria')
            ->assertOk()
            ->assertJsonPath('title', 'Operación de la flota')
            ->assertJsonStructure([
                'kpis',
                'queue',
                'charts',
                'exports',
                'recent',
            ]);
    }

    public function test_docente_dashboard_no_ve_cola_de_secretaria(): void
    {
        $this->actingAsEmail('docente@uleam.edu.ec');

        $response = $this->getJson('/api/dashboard/metrics?focus=docente')->assertOk();
        $this->assertSame('Mis movilizaciones', $response->json('title'));
        $labels = collect($response->json('kpis'))->pluck('label')->all();
        $this->assertNotContains('Por autorizar', $labels);
        $this->assertContains('En autorización', $labels);
        $this->assertContains('Salidas del mes', $labels);
    }

    public function test_facultad_y_vicerrector_tienen_tablero_con_constancia(): void
    {
        $this->actingAsEmail('decano@uleam.edu.ec');
        $facultad = $this->getJson('/api/dashboard/metrics?focus=responsable_facultad')->assertOk();
        $this->assertSame('Movilidad de la facultad', $facultad->json('title'));
        $this->assertGreaterThanOrEqual(4, count($facultad->json('kpis')));
        $this->assertNotEmpty($facultad->json('recent'));

        $this->actingAsEmail('vicerrector@uleam.edu.ec');
        $vic = $this->getJson('/api/dashboard/metrics?focus=vicerrector')->assertOk();
        $this->assertSame('Autorización de viajes externos', $vic->json('title'));
        $labels = collect($vic->json('kpis'))->pluck('label')->all();
        $this->assertContains('Por aprobar', $labels);
        $this->assertContains('Externas del mes', $labels);
    }

    public function test_flujo_devuelve_linea_de_fases_y_alerta_al_solicitante(): void
    {
        $this->actingAsEmail('docente@uleam.edu.ec');
        $requestId = $this->postJson('/api/solicitudes', [
            'mobilization_type' => 'interna',
            'origin' => 'MANTA',
            'destination' => 'CHONE FASES',
            'travel_reason' => 'Práctica de campo para línea de proceso',
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '08:00',
            'return_date' => now()->addDays(3)->toDateString(),
            'return_time' => '18:00',
            'declaracion_fondos_aceptada' => true,
        ])->assertCreated()->json('request.id');

        $flujo = $this->getJson("/api/solicitudes/{$requestId}/flujo")->assertOk();
        $phases = collect($flujo->json('phases'));
        $this->assertContains('secretaria', $phases->pluck('key')->all());
        $this->assertSame('current', $phases->firstWhere('key', 'secretaria')['state']);
        $this->assertSame('pending', $phases->firstWhere('key', 'asignacion')['state']);

        $alerts = collect($this->getJson('/api/alertas')->assertOk()->json('alerts'));
        $this->assertTrue(
            $alerts->contains(fn (array $alert) => $alert['type'] === 'solicitud_en_tramite'),
            'El docente debe ver el aviso de trámite en Secretaría.'
        );

        $this->actingAsEmail('secretaria@uleam.edu.ec');
        $this->patchJson("/api/solicitudes/{$requestId}/autorizar-secretaria", [
            'action' => 'approve',
            'observation' => 'Autorizada para asignar.',
        ])->assertOk();

        $this->actingAsEmail('docente@uleam.edu.ec');
        $after = collect($this->getJson('/api/alertas')->assertOk()->json('alerts'));
        $this->assertFalse(
            $after->contains(fn (array $alert) => $alert['type'] === 'solicitud_en_tramite'),
            'Al autorizar, el aviso de espera en Secretaría debe desaparecer.'
        );
        $this->assertTrue(
            $after->contains(fn (array $alert) => $alert['type'] === 'solicitud_autorizada'),
            'El docente debe ver que ya está autorizada y espera unidad.'
        );

        $asignada = collect(
            $this->getJson("/api/solicitudes/{$requestId}/flujo")->assertOk()->json('phases')
        )->firstWhere('key', 'secretaria');
        $this->assertSame('done', $asignada['state']);
    }

    public function test_reportes_se_exportan_en_pdf_institucional(): void
    {
        $this->actingAsEmail('secretaria@uleam.edu.ec');

        foreach (['solicitudes', 'mensual', 'aceite', 'novedades', 'flota'] as $kind) {
            $response = $this->get("/api/reportes/{$kind}?format=pdf");
            $response->assertOk();
            $this->assertStringStartsWith('%PDF', $response->streamedContent());
            $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        }
    }

    public function test_mecanico_descarga_control_de_aceite_y_no_flota(): void
    {
        $this->actingAsEmail('mecanico@uleam.edu.ec');

        $this->get('/api/reportes/aceite?format=pdf')->assertOk();
        $this->getJson('/api/reportes/flota')->assertForbidden();
    }

    public function test_generar_orden_pdf_deja_trazabilidad(): void
    {
        $this->actingAsEmail('docente@uleam.edu.ec');
        $requestId = $this->postJson('/api/solicitudes', [
            'mobilization_type' => 'interna',
            'origin' => 'MANTA',
            'destination' => 'PORTOVIEJO PDF',
            'travel_reason' => 'Prueba de formato institucional',
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '08:00',
            'return_date' => now()->addDays(4)->toDateString(),
            'return_time' => '18:00',
            'declaracion_fondos_aceptada' => true,
            'occupant_count' => 12,
            'activity_type' => 'visitas_academicas',
            'academic_program' => 'Sistemas',
        ])->assertCreated()->json('request.id');

        $this->actingAsEmail('secretaria@uleam.edu.ec');
        $created = $this->postJson('/api/documentos/generar', [
            'type' => 'orden_movilizacion',
            'request_id' => $requestId,
        ])->assertCreated()->json('document');

        $this->assertSame('orden_movilizacion', $created['document_type']);

        $file = $this->get("/api/documentos/{$created['id']}/archivo");
        $file->assertOk();
        $this->assertStringStartsWith('%PDF', $file->streamedContent());
        $this->assertStringContainsString('PST-01-F-003', $file->streamedContent());

        $flujo = $this->getJson("/api/solicitudes/{$requestId}/flujo")->assertOk();
        $actions = collect($flujo->json('timeline'))->pluck('action')->all();
        $this->assertContains('DOCUMENTO_GENERADO', $actions);
    }

    public function test_secretaria_y_docente_firman_orden_y_queda_en_pdf(): void
    {
        $this->actingAsEmail('docente@uleam.edu.ec');
        $requestId = $this->postJson('/api/solicitudes', [
            'mobilization_type' => 'interna',
            'origin' => 'MANTA',
            'destination' => 'PORTOVIEJO FIRMA',
            'travel_reason' => 'Prueba de firma electrónica',
            'departure_date' => now()->addDays(3)->toDateString(),
            'departure_time' => '08:00',
            'return_date' => now()->addDays(4)->toDateString(),
            'return_time' => '18:00',
            'declaracion_fondos_aceptada' => true,
        ])->assertCreated()->json('request.id');

        $this->actingAsEmail('secretaria@uleam.edu.ec');
        $documentId = $this->postJson('/api/documentos/generar', [
            'type' => 'orden_movilizacion',
            'request_id' => $requestId,
        ])->assertCreated()->json('document.id');

        $this->postJson("/api/documentos/{$documentId}/firmar", [
            'slot' => 'secretaria',
            'password' => 'password',
            'declaration' => true,
        ])->assertOk()->assertJsonPath('signature.slot', 'secretaria');

        $this->postJson("/api/documentos/{$documentId}/firmar", [
            'slot' => 'secretaria',
            'password' => 'password',
            'declaration' => true,
        ])->assertStatus(422);

        $this->actingAsEmail('docente@uleam.edu.ec');
        $this->postJson("/api/documentos/{$documentId}/firmar", [
            'slot' => 'secretaria',
            'password' => 'password',
            'declaration' => true,
        ])->assertStatus(422);

        $this->actingAsEmail('docente@uleam.edu.ec');
        $this->postJson("/api/documentos/{$documentId}/firmar", [
            'slot' => 'solicitante',
            'password' => 'wrong',
            'declaration' => true,
        ])->assertStatus(422);

        $this->postJson("/api/documentos/{$documentId}/firmar", [
            'slot' => 'solicitante',
            'password' => 'password',
            'declaration' => true,
        ])->assertOk();

        $pdf = $this->get("/api/documentos/{$documentId}/archivo");
        $pdf->assertOk();
        $this->assertStringContainsString('FIRMA DIGITAL', $pdf->streamedContent());

        $this->getJson("/api/documentos/{$documentId}/verificar")
            ->assertOk()
            ->assertJsonPath('valid', true);

        $flujo = $this->getJson("/api/solicitudes/{$requestId}/flujo")->assertOk();
        $this->assertContains('DOCUMENTO_FIRMADO', collect($flujo->json('timeline'))->pluck('action')->all());
    }

    public function test_vicerrector_no_puede_exportar_flota(): void
    {
        $this->actingAsEmail('vicerrector@uleam.edu.ec');
        $this->getJson('/api/reportes/flota')->assertForbidden();
        $this->get('/api/reportes/mensual?format=pdf')->assertOk();
    }

    public function test_alertas_de_viaje_incluyen_detalle_visible(): void
    {
        $this->actingAsEmail('docente@uleam.edu.ec');
        $requestId = $this->postJson('/api/solicitudes', [
            'mobilization_type' => 'interna',
            'origin' => 'MANTA',
            'destination' => 'PORTOVIEJO ALERTA',
            'travel_reason' => 'Práctica de campo',
            'departure_date' => now()->addDays(2)->toDateString(),
            'departure_time' => '07:30',
            'return_date' => now()->addDays(2)->toDateString(),
            'return_time' => '18:00',
            'declaracion_fondos_aceptada' => true,
        ])->assertCreated()->json('request.id');

        $this->actingAsEmail('secretaria@uleam.edu.ec');
        $pending = collect($this->getJson('/api/alertas')->assertOk()->json('alerts'))
            ->first(fn (array $alert) => $alert['type'] === 'viaje_autorizar' && ($alert['detail']['destino'] ?? null) === 'PORTOVIEJO ALERTA');

        $this->assertNotEmpty($pending);
        $this->assertSame('MANTA', $pending['detail']['origen']);
        $this->assertStringContainsString('Autorizar', $pending['detail']['accion']);

        $this->patchJson("/api/solicitudes/{$requestId}/autorizar-secretaria", [
            'action' => 'approve',
            'observation' => 'Autorizada; el aviso debe desaparecer.',
        ])->assertOk();

        $after = collect($this->getJson('/api/alertas')->assertOk()->json('alerts'));
        $this->assertFalse(
            $after->contains(fn (array $alert) => $alert['type'] === 'viaje_autorizar' && ($alert['detail']['destino'] ?? null) === 'PORTOVIEJO ALERTA'),
            'La notificación debe eliminarse cuando Secretaría ya autorizó el viaje.'
        );

        $this->actingAsEmail('conductor1@uleam.edu.ec');
        $driverPending = collect($this->getJson('/api/alertas')->assertOk()->json('alerts'))
            ->contains(fn (array $alert) => $alert['type'] === 'viaje_autorizar');
        $this->assertFalse($driverPending);
    }

    private function actingAsEmail(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }
}
