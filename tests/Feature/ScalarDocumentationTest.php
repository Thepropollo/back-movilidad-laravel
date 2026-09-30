<?php

namespace Tests\Feature;

use Tests\TestCase;

class ScalarDocumentationTest extends TestCase
{
    public function test_scalar_route_returns_200_and_renders_api_reference(): void
    {
        $response = $this->get('/scalar');

        $response->assertStatus(200);
        $response->assertSee('Scalar.createApiReference', false);
        $response->assertSee('ULEAM', false);
    }

    public function test_docs_route_redirects_to_scalar(): void
    {
        $response = $this->get('/docs');

        $response->assertStatus(302);
        $response->assertRedirect('/scalar');
    }

    public function test_openapi_json_endpoint_returns_valid_spec(): void
    {
        $response = $this->get('/openapi.json');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/json');

        $data = $response->json();

        $this->assertEquals('3.1.0', $data['openapi']);
        $this->assertArrayHasKey('info', $data);
        $this->assertArrayHasKey('paths', $data);
        $this->assertArrayHasKey('components', $data);

        // Validar endpoints esenciales
        $this->assertArrayHasKey('/api/login', $data['paths']);
        $this->assertArrayHasKey('/api/register', $data['paths']);
        $this->assertArrayHasKey('/api/solicitudes', $data['paths']);
        $this->assertArrayHasKey('/api/hojas-ruta', $data['paths']);
        $this->assertArrayHasKey('/api/vehicles', $data['paths']);
        $this->assertArrayHasKey('/api/drivers', $data['paths']);
        $this->assertArrayHasKey('/api/estaciones-servicio', $data['paths']);
        $this->assertArrayHasKey('/api/ordenes-combustible', $data['paths']);
        $this->assertArrayHasKey('/api/compensaciones/pendientes', $data['paths']);
        $this->assertArrayHasKey('/api/actas-entrega', $data['paths']);
        $this->assertArrayHasKey('/api/ordenes-taller', $data['paths']);
        $this->assertArrayHasKey('/api/documentos', $data['paths']);
        $this->assertArrayHasKey('/api/documentos/catalogo', $data['paths']);
        $this->assertArrayHasKey('/api/alertas', $data['paths']);
        $this->assertArrayHasKey('/api/tarifas', $data['paths']);
        $this->assertArrayHasKey('/api/admin/usuarios', $data['paths']);

        // Validar esquemas principales
        $schemas = $data['components']['schemas'];
        $this->assertArrayHasKey('User', $schemas);
        $this->assertArrayHasKey('MobilizationRequest', $schemas);
        $this->assertArrayHasKey('RouteSheet', $schemas);
        $this->assertArrayHasKey('Vehicle', $schemas);
        $this->assertArrayHasKey('Driver', $schemas);
        $this->assertArrayHasKey('FuelOrder', $schemas);
        $this->assertArrayHasKey('DriverCompensation', $schemas);
        $this->assertArrayHasKey('DeliveryReceptionAct', $schemas);
        $this->assertArrayHasKey('WorkshopWorkOrder', $schemas);
        $this->assertArrayHasKey('GeneratedDocument', $schemas);
    }

    public function test_openapi_yaml_endpoint_returns_yaml_content(): void
    {
        $response = $this->get('/openapi.yaml');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/x-yaml');
        $this->assertStringContainsString('openapi: 3.1.0', $response->getContent());
    }

    public function test_all_openapi_paths_exist_in_laravel_router(): void
    {
        $response = $this->get('/openapi.json');
        $data = $response->json();
        $openApiPaths = array_keys($data['paths']);

        $routerRoutes = collect(\Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
            ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
            ->unique()
            ->all();

        // Convert path templates like /api/vehicles/{id} to regex
        foreach ($openApiPaths as $path) {
            $regex = '#^'.preg_replace('/\{[a-zA-Z0-9_]+\}/', '[^/]+', $path).'$#';
            $matched = collect($routerRoutes)->contains(fn ($r) => preg_match($regex, $r) === 1);
            $this->assertTrue($matched, "La ruta documentada en OpenAPI [$path] debe estar registrada en Laravel.");
        }
    }

    public function test_openapi_methods_and_registration_contract_match_backend(): void
    {
        $data = $this->getJson('/openapi.json')->assertOk()->json();

        foreach (\Illuminate\Support\Facades\Route::getRoutes()->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/')) {
                continue;
            }

            $path = '/'.$route->uri();
            foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
                $this->assertArrayHasKey($path, $data['paths']);
                $this->assertArrayHasKey(strtolower($method), $data['paths'][$path]);
            }
        }

        $register = $data['paths']['/api/register']['post']['requestBody']['content']['application/json']['schema']['properties'];
        $this->assertArrayNotHasKey('role', $register);
        $this->assertArrayNotHasKey('example', $register['password']);
        $this->assertArrayNotHasKey('example', $register['email']);

        $login = $data['paths']['/api/login']['post']['requestBody']['content']['application/json']['schema']['properties'];
        $this->assertArrayNotHasKey('example', $login['password']);
        $this->assertArrayNotHasKey('example', $login['email']);
    }

    public function test_protected_endpoints_require_authentication(): void
    {
        $this->getJson('/api/solicitudes')->assertStatus(401);
        $this->getJson('/api/me')->assertStatus(401);
        $this->getJson('/api/vehicles')->assertStatus(401);
        $this->getJson('/api/drivers')->assertStatus(401);
        $this->getJson('/api/compensaciones/pendientes')->assertStatus(401);
        $this->getJson('/api/alertas')->assertStatus(401);
    }
}
