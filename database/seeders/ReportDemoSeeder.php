<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Domain\Auth\Models\Driver;
use Domain\Auth\Models\User;
use Domain\Requests\Models\FuelOrder;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\ServiceStation;
use Domain\Vehicles\Models\Vehicle;
use Domain\Workshop\Models\IssueLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Adds repeatable, clearly marked data for local report demonstrations.
 * Run after DatabaseSeeder with:
 * php artisan db:seed --class=Database\\Seeders\\ReportDemoSeeder
 */
class ReportDemoSeeder extends Seeder
{
    private const REQUEST_COUNT = 80;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('ReportDemoSeeder solo se puede ejecutar en local o testing.');
        }

        $requesters = User::query()
            ->whereIn('email', ['docente@uleam.edu.ec', 'decano@uleam.edu.ec'])
            ->orderBy('email')
            ->get()
            ->values();
        $secretaria = User::where('email', 'secretaria@uleam.edu.ec')->first();
        $vicerrector = User::where('email', 'vicerrector@uleam.edu.ec')->first();
        $drivers = Driver::query()
            ->whereIn('user_id', User::query()
                ->whereIn('email', ['conductor1@uleam.edu.ec', 'conductor.mecanico@uleam.edu.ec'])
                ->select('id'))
            ->orderBy('id')
            ->get()
            ->values();
        $vehicles = Vehicle::query()
            ->whereIn('plate', ['MBA-1234', 'MBA-2468'])
            ->orderBy('plate')
            ->get()
            ->values();
        $station = ServiceStation::query()->where('active_agreement', true)->orderBy('id')->first();

        if ($requesters->count() < 2 || ! $secretaria || ! $vicerrector || $drivers->count() < 2 || $vehicles->count() < 2 || ! $station) {
            throw new \RuntimeException('Faltan cuentas, conductores, vehículos o estación. Ejecuta primero DatabaseSeeder.');
        }

        $destinations = ['PORTOVIEJO', 'JIPIJAPA', 'CHONE', 'BAHÍA DE CARÁQUEZ', 'GUAYAQUIL', 'QUITO', 'SANTO DOMINGO', 'MONTECRISTI'];
        $createdRequests = [];
        $createdSheets = [];

        DB::transaction(function () use ($requesters, $secretaria, $vicerrector, $drivers, $vehicles, $station, $destinations, &$createdRequests, &$createdSheets): void {
            for ($number = 1; $number <= self::REQUEST_COUNT; $number++) {
                $isPendingSecretaria = $number <= 12;
                $isPendingVicerrectorado = $number > 12 && $number <= 20;
                $isExternal = $isPendingVicerrectorado || $number % 3 === 0;
                $requester = $requesters[($number - 1) % $requesters->count()];
                $destination = $destinations[($number - 1) % count($destinations)];
                $estimatedDays = ($number % 4) + 1;
                $departureDate = $isPendingSecretaria || $isPendingVicerrectorado
                    ? Carbon::today()->addDays(($number % 28) + 1)
                    : Carbon::today()->subDays((($number * 17) % 330) + 1);
                $status = $isPendingSecretaria
                    ? 'pendiente_secretaria'
                    : ($isPendingVicerrectorado ? 'pendiente_rectorado' : 'aprobada');
                $routeNumber = $number - 21;
                $isFinished = ! $isPendingSecretaria && ! $isPendingVicerrectorado && $routeNumber % 4 !== 0;

                if ($isFinished) {
                    $status = 'finalizada';
                }

                $request = MobilizationRequest::firstOrCreate(
                    ['travel_reason' => sprintf('DEMO-REPORTE-%04d: actividad académica institucional', $number)],
                    [
                        'requester_id' => $requester->id,
                        'mobilization_type' => $isExternal ? 'externa' : 'interna',
                        'origin' => 'MANTA',
                        'destination' => $destination,
                        'departure_date' => $departureDate->toDateString(),
                        'departure_time' => '07:30:00',
                        'return_date' => $departureDate->copy()->addDays($estimatedDays)->toDateString(),
                        'return_time' => '17:00:00',
                        'estimated_days' => $estimatedDays,
                        'projected_cost' => 75 + (($number * 23) % 925),
                        'status' => $status,
                        'secretaria_approver_id' => $isPendingSecretaria ? null : $secretaria->id,
                        'rectorate_approver_id' => $isExternal && ! $isPendingVicerrectorado ? $vicerrector->id : null,
                    ]
                );
                $createdRequests[] = $request->id;

                if ($isPendingSecretaria || $isPendingVicerrectorado) {
                    continue;
                }

                $driver = $drivers[($routeNumber + 1) % $drivers->count()];
                $vehicle = $vehicles[$routeNumber % $vehicles->count()];
                $initialMileage = 35000 + ($routeNumber * 137);
                $tripStatus = $isFinished ? 'finalizado' : 'pendiente_feedback';
                $sheet = RouteSheet::firstOrCreate(
                    ['request_id' => $request->id],
                    [
                        'vehicle_id' => $vehicle->id,
                        'driver_id' => $driver->id,
                        'transport_chief_id' => $secretaria->id,
                        'initial_mileage' => $initialMileage,
                        'final_mileage' => $initialMileage + 80 + (($number * 29) % 520),
                        'trip_status' => $tripStatus,
                        'driver_response' => 'aceptado',
                        'driver_responded_at' => $departureDate->copy()->subDay(),
                    ]
                );
                $createdSheets[] = $sheet->id;

                if ($routeNumber % 2 === 0) {
                    $gallons = 8 + (($number * 13) % 240) / 10;
                    $dispatched = $routeNumber % 6 !== 0;
                    FuelOrder::firstOrCreate(
                        ['order_code' => sprintf('DEMO-RPT-FUEL-%04d', $number)],
                        [
                            'route_sheet_id' => $sheet->id,
                            'station_id' => $station->id,
                            'transport_chief_id' => $secretaria->id,
                            'dispatched_fuel_type' => $vehicle->fuel_type,
                            'authorized_gallons' => $gallons,
                            'actual_dispatched_gallons' => $dispatched ? $gallons - 0.35 : null,
                            'total_amount_paid' => $dispatched ? ($gallons - 0.35) * 1.80 : null,
                            'order_status' => $dispatched ? 'despachada' : 'emitida',
                            'dispatch_date' => $dispatched ? $departureDate->copy()->setTime(6, 30) : null,
                        ]
                    );
                }
            }

            for ($number = 1; $number <= 16; $number++) {
                $vehicle = $vehicles[($number - 1) % $vehicles->count()];
                $driver = $drivers[($number - 1) % $drivers->count()];
                $description = sprintf('DEMO-REPORTE-NOVEDAD-%02d: revisión preventiva de unidad.', $number);

                IssueLog::firstOrCreate(
                    ['description' => $description],
                    [
                        'vehicle_id' => $vehicle->id,
                        'route_sheet_id' => null,
                        'reporting_driver_id' => $driver->user_id,
                        'breakdown_date' => Carbon::today()->subDays(($number * 11) % 300)->toDateString(),
                        'status' => ['pendiente', 'en_revision', 'solventado'][($number - 1) % 3],
                    ]
                );
            }
        });

        $this->command?->info(sprintf(
            'Datos de reportes listos: %d solicitudes, %d hojas de ruta y 16 novedades DEMO-REPORTE (repetible).',
            count(array_unique($createdRequests)),
            count(array_unique($createdSheets))
        ));
    }
}
