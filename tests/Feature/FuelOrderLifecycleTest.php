<?php

namespace Tests\Feature;

use Domain\Auth\Models\Driver;
use Domain\Auth\Models\User;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\ServiceStation;
use Domain\Vehicles\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FuelOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_a_route_gets_one_fuel_order_and_the_order_is_dispatched_once(): void
    {
        $secretariat = User::where('email', 'secretaria@uleam.edu.ec')->firstOrFail();
        $station = ServiceStation::where('active_agreement', true)->firstOrFail();
        $sheet = $this->createSheet();

        $order = $this->actingAs($secretariat)->postJson('/api/ordenes-combustible', [
            'route_sheet_id' => $sheet->id,
            'station_id' => $station->id,
        ])->assertCreated()->json('fuel_order');

        $this->actingAs($secretariat)->postJson('/api/ordenes-combustible', [
            'route_sheet_id' => $sheet->id,
            'station_id' => $station->id,
        ])->assertUnprocessable();

        $dispatch = [
            'galones_reales_despachados' => 0.01,
            'valor_total_pagado' => 0.01,
        ];
        $this->actingAs($secretariat)->patchJson("/api/ordenes-combustible/{$order['order_code']}/despachar", $dispatch)
            ->assertOk()
            ->assertJsonPath('fuel_order.order_status', 'despachada');

        $this->actingAs($secretariat)->patchJson("/api/ordenes-combustible/{$order['order_code']}/despachar", $dispatch)
            ->assertUnprocessable();
    }

    private function createSheet(): RouteSheet
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
            'travel_reason' => 'Prueba de combustible',
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
            'trip_status' => 'programado',
            'driver_response' => 'pendiente',
        ]);
    }
}
