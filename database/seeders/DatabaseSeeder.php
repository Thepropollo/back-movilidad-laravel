<?php

namespace Database\Seeders;

use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Domain\Auth\Models\Driver;
use Domain\Auth\Models\DriverLicense;
use Domain\Auth\Models\DailyAttendance;
use Domain\Vehicles\Models\Vehicle;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\ServiceStation;
use Domain\Requests\Models\FuelOrder;
use Domain\Requests\Models\PassengerManifest;
use Domain\Requests\Models\DeliveryReceptionAct;
use Domain\Requests\Models\RateConfiguration;
use Domain\Requests\Models\ChecklistInventoryComponent;
use Domain\Workshop\Models\SupplyInventory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'solicitante',
                'description' => 'Docente / Tutor que solicita movilizaciones.'
            ],
            [
                'name' => 'rector',
                'description' => 'Autoridad máxima que autoriza viajes externos.'
            ],
            [
                'name' => 'jefe_transporte',
                'description' => 'Administrador que asigna vehículos, choferes y recursos.'
            ],
            [
                'name' => 'chofer',
                'description' => 'Conductor de vehículos de la institución.'
            ],
            [
                'name' => 'mecanico',
                'description' => 'Encargado del taller y actas de entrega/recepción.'
            ],
            [
                'name' => 'pasajero',
                'description' => 'Estudiante, servidor público o docente que viaja.'
            ]
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['description' => $role['description']]
            );
        }

        // Crear usuario de prueba: Solicitante
        $solicitanteRole = Role::where('name', 'solicitante')->first();
        User::firstOrCreate(
            ['email' => 'solicitante@test.com'],
            [
                'national_id' => '1312345678',
                'first_name' => 'Juan Carlos',
                'last_name' => 'Pérez (Solicitante)',
                'password' => Hash::make('password'),
                'faculty_institution' => 'FACULTAD DE CIENCIAS INFORMATICAS',
                'role_id' => $solicitanteRole ? $solicitanteRole->id : null,
            ]
        );

        // Crear usuario de prueba: Jefe de Transporte
        $jefeRole = Role::where('name', 'jefe_transporte')->first();
        User::firstOrCreate(
            ['email' => 'jefe@test.com'],
            [
                'national_id' => '1309876543',
                'first_name' => 'Ricardo Andrés',
                'last_name' => 'Loor (Jefe de Transporte)',
                'password' => Hash::make('password'),
                'faculty_institution' => 'DIRECCIÓN DE TRANSPORTE Y MOVILIDAD',
                'role_id' => $jefeRole ? $jefeRole->id : null,
            ]
        );

        // Crear usuario de prueba: Rector
        $rectorRole = Role::where('name', 'rector')->first();
        User::firstOrCreate(
            ['email' => 'rector@test.com'],
            [
                'national_id' => '1301122334',
                'first_name' => 'Martha Sofía',
                'last_name' => 'Mendoza (Rectora)',
                'password' => Hash::make('password'),
                'faculty_institution' => 'RECTORADO',
                'role_id' => $rectorRole ? $rectorRole->id : null,
            ]
        );

        // Crear choferes de prueba
        $choferRole = Role::where('name', 'chofer')->first();

        // Chofer 1: Luis (Habilitado y Disponible)
        $userDriver1 = User::firstOrCreate(
            ['email' => 'chofer1@test.com'],
            [
                'national_id' => '1311111111',
                'first_name' => 'Luis Alberto',
                'last_name' => 'Moreira (Chofer 1)',
                'password' => Hash::make('password'),
                'faculty_institution' => 'DIRECCIÓN DE TRANSPORTE Y MOVILIDAD',
                'role_id' => $choferRole ? $choferRole->id : null,
            ]
        );
        $driver1 = Driver::firstOrCreate(
            ['user_id' => $userDriver1->id],
            [
                'contract_type' => 'nombramiento',
                'is_available' => true,
            ]
        );
        DriverLicense::firstOrCreate(
            ['driver_id' => $driver1->id],
            [
                'license_type' => 'E',
                'current_points' => 30,
                'expiration_date' => \Carbon\Carbon::now()->addYears(5)->toDateString(),
            ]
        );

        // Chofer 2: Carlos (Inhabilitado - Licencia Expirada)
        $userDriver2 = User::firstOrCreate(
            ['email' => 'chofer2@test.com'],
            [
                'national_id' => '1322222222',
                'first_name' => 'Carlos Andrés',
                'last_name' => 'Vera (Chofer Inhabilitado)',
                'password' => Hash::make('password'),
                'faculty_institution' => 'DIRECCIÓN DE TRANSPORTE Y MOVILIDAD',
                'role_id' => $choferRole ? $choferRole->id : null,
            ]
        );
        $driver2 = Driver::firstOrCreate(
            ['user_id' => $userDriver2->id],
            [
                'contract_type' => 'contrato',
                'is_available' => true,
            ]
        );
        DriverLicense::firstOrCreate(
            ['driver_id' => $driver2->id],
            [
                'license_type' => 'C',
                'current_points' => 15,
                'expiration_date' => \Carbon\Carbon::now()->subDays(5)->toDateString(), // Expirada
            ]
        );

        // Sembrar vehículos de prueba
        // Vehículo 1: Disponible y listo
        Vehicle::firstOrCreate(
            ['plate' => 'MBA-1234'],
            [
                'brand' => 'Toyota',
                'model' => 'Hilux',
                'year' => 2022,
                'color' => 'Plateado',
                'fuel_type' => 'diesel',
                'current_mileage' => 45000,
                'next_oil_change_mileage' => 50000,
                'operational_status' => 'disponible',
            ]
        );

        // Vehículo 2: Bloqueado por Aceite
        Vehicle::firstOrCreate(
            ['plate' => 'MBA-5678'],
            [
                'brand' => 'Chevrolet',
                'model' => 'D-Max',
                'year' => 2021,
                'color' => 'Blanco',
                'fuel_type' => 'diesel',
                'current_mileage' => 80200,
                'next_oil_change_mileage' => 80000, // Superó límite
                'operational_status' => 'disponible',
            ]
        );

        // Vehículo 3: Bloqueado por estado taller
        Vehicle::firstOrCreate(
            ['plate' => 'MBA-9012'],
            [
                'brand' => 'Hyundai',
                'model' => 'H1',
                'year' => 2020,
                'color' => 'Gris',
                'fuel_type' => 'diesel',
                'current_mileage' => 60000,
                'next_oil_change_mileage' => 65000,
                'operational_status' => 'en_taller', // No disponible en patio
            ]
        );

        // Sembrar tarifas
        RateConfiguration::firstOrCreate(
            ['rate_key' => 'viatico_diario'],
            ['rate_value' => 80.00]
        );
        RateConfiguration::firstOrCreate(
            ['rate_key' => 'extra_50'],
            ['rate_value' => 5.00]
        );
        RateConfiguration::firstOrCreate(
            ['rate_key' => 'extra_100'],
            ['rate_value' => 7.50]
        );

        // Sembrar componentes de checklist
        $checklistComponents = [
            ['component_name' => 'Luces Delanteras y Traseras', 'category' => 'Luces'],
            ['component_name' => 'Luces de Freno y Direccionales', 'category' => 'Luces'],
            ['component_name' => 'Extintor de Incendios', 'category' => 'Seguridad'],
            ['component_name' => 'Botiquín de Primeros Auxilios', 'category' => 'Seguridad'],
            ['component_name' => 'Cinturones de Seguridad', 'category' => 'Seguridad'],
            ['component_name' => 'Gato Hidráulico y Llave de Ruedas', 'category' => 'Herramientas'],
            ['component_name' => 'Triángulos de Seguridad', 'category' => 'Herramientas'],
            ['component_name' => 'Limpia Parabrisas', 'category' => 'Carrocería'],
            ['component_name' => 'Retrovisores y Espejos', 'category' => 'Carrocería'],
            ['component_name' => 'Neumático de Repuesto', 'category' => 'Carrocería'],
        ];

        foreach ($checklistComponents as $item) {
            ChecklistInventoryComponent::firstOrCreate(
                ['component_name' => $item['component_name']],
                ['category' => $item['category']]
            );
        }

        // Sembrar insumos de taller
        $supplies = [
            ['supply_name' => 'Aceite Motor 15W40', 'current_stock' => 50, 'measurement_unit' => 'Galón'],
            ['supply_name' => 'Filtro de Aceite Hilux', 'current_stock' => 12, 'measurement_unit' => 'Unidad'],
            ['supply_name' => 'Pastillas de Freno Delanteras', 'current_stock' => 8, 'measurement_unit' => 'Juego'],
            ['supply_name' => 'Líquido de Frenos Dot4', 'current_stock' => 15, 'measurement_unit' => 'Litro'],
            ['supply_name' => 'Filtro de Aire Hilux', 'current_stock' => 10, 'measurement_unit' => 'Unidad'],
        ];

        foreach ($supplies as $item) {
            SupplyInventory::firstOrCreate(
                ['supply_name' => $item['supply_name']],
                [
                    'current_stock' => $item['current_stock'],
                    'measurement_unit' => $item['measurement_unit']
                ]
            );
        }

        // Sembrar solicitud y hoja de ruta pendiente de salida
        $requester = User::where('email', 'solicitante@test.com')->first();
        $driver = Driver::first();
        $vehicle = Vehicle::where('plate', 'MBA-1234')->first();
        $jefe = User::where('email', 'jefe@test.com')->first();

        if ($requester && $driver && $vehicle && $jefe) {
            $req = MobilizationRequest::firstOrCreate(
                ['destination' => 'QUITO', 'travel_reason' => 'Congreso Académico de TI ULEAM'],
                [
                    'requester_id' => $requester->id,
                    'mobilization_type' => 'externa',
                    'origin' => 'MANTA',
                    'departure_date' => \Carbon\Carbon::now()->addDay()->toDateString(),
                    'return_date' => \Carbon\Carbon::now()->addDays(4)->toDateString(),
                    'estimated_days' => 3,
                    'projected_cost' => 240.00,
                    'status' => 'aprobada',
                    'rectorate_approver_id' => User::where('email', 'rector@test.com')->first()->id ?? null
                ]
            );

            // Crear la hoja de ruta pendiente de salida (trip_status = programado)
            $sheet1 = RouteSheet::firstOrCreate(
                ['request_id' => $req->id],
                [
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'transport_chief_id' => $jefe->id,
                    'initial_mileage' => null,
                    'final_mileage' => null,
                    'trip_status' => 'programado'
                ]
            );

            // Sembrar estaciones de servicio
            $stations = [
                [
                    'commercial_name' => 'Gasolinera Primax Tarqui',
                    'ruc' => '1301234567001',
                    'address' => 'Av. 108 y Calle 101, Manta',
                    'active_agreement' => true
                ],
                [
                    'commercial_name' => 'Estación de Servicio Petrolecuador Manta',
                    'ruc' => '1309876543001',
                    'address' => 'Vía San Mateo km 1, Manta',
                    'active_agreement' => true
                ]
            ];

            $stationModels = [];
            foreach ($stations as $item) {
                $stationModels[] = ServiceStation::firstOrCreate(
                    ['ruc' => $item['ruc']],
                    [
                        'commercial_name' => $item['commercial_name'],
                        'address' => $item['address'],
                        'active_agreement' => $item['active_agreement']
                    ]
                );
            }

            // Sembrar una orden de combustible pre-existente asociada a la hoja de ruta anterior
            if (count($stationModels) > 0) {
                FuelOrder::firstOrCreate(
                    ['route_sheet_id' => $sheet1->id],
                    [
                        'order_code' => 'ULEAM-FUEL12',
                        'station_id' => $stationModels[0]->id,
                        'transport_chief_id' => $jefe->id,
                        'dispatched_fuel_type' => $vehicle->fuel_type,
                        'authorized_gallons' => 12.86,
                        'actual_dispatched_gallons' => null,
                        'total_amount_paid' => null,
                        'order_status' => 'emitida',
                        'dispatch_date' => null
                    ]
                );
            }

            // Sembrar rol de pasajero/estudiante y usuario estudiante
            $pasajeroRole = Role::where('name', 'pasajero')->first();
            $estudiante = User::firstOrCreate(
                ['email' => 'estudiante@test.com'],
                [
                    'national_id' => '1308888888',
                    'first_name' => 'Jean Pierre',
                    'last_name' => 'Mendoza (Estudiante)',
                    'password' => Hash::make('password'),
                    'faculty_institution' => 'FACULTAD DE CIENCIAS INFORMATICAS',
                    'role_id' => $pasajeroRole ? $pasajeroRole->id : null
                ]
            );

            // Sembrar manifiesto de pasajeros para la primera solicitud (Quito)
            PassengerManifest::firstOrCreate(
                ['request_id' => $req->id, 'user_id' => $estudiante->id],
                ['attended' => true]
            );

            // Sembrar asistencias del conductor (Luis Alberto) para cálculo de horas extras
            $today = \Carbon\Carbon::today();
            $yesterday = \Carbon\Carbon::yesterday();
            $twoDaysAgo = \Carbon\Carbon::today()->subDays(2);

            $datesToSeed = [$twoDaysAgo, $yesterday, $today];
            foreach ($datesToSeed as $d) {
                DailyAttendance::firstOrCreate(
                    ['driver_id' => $driver->id, 'date' => $d->toDateString()],
                    [
                        'check_in_time' => '08:00:00',
                        'check_out_time' => '17:00:00',
                        'notes' => 'Asistencia regular local.'
                    ]
                );
            }

            // Sembrar una segunda solicitud ya retornada y en estado 'pendiente_feedback' (Guayaquil)
            $req2 = MobilizationRequest::firstOrCreate(
                ['destination' => 'GUAYAQUIL', 'travel_reason' => 'Visita Técnica a Puerto de Guayaquil'],
                [
                    'requester_id' => $requester->id,
                    'mobilization_type' => 'externa',
                    'origin' => 'MANTA',
                    'departure_date' => $twoDaysAgo->toDateString(),
                    'return_date' => $today->toDateString(),
                    'estimated_days' => 3,
                    'projected_cost' => 160.00,
                    'status' => 'aprobada',
                    'rectorate_approver_id' => User::where('email', 'rector@test.com')->first()->id ?? null
                ]
            );

            $sheet2 = RouteSheet::firstOrCreate(
                ['request_id' => $req2->id],
                [
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'transport_chief_id' => $jefe->id,
                    'initial_mileage' => 45000,
                    'final_mileage' => 45400,
                    'trip_status' => 'pendiente_feedback'
                ]
            );

            // Manifiesto de pasajeros para Guayaquil
            PassengerManifest::firstOrCreate(
                ['request_id' => $req2->id, 'user_id' => $estudiante->id],
                ['attended' => true]
            );

            // Crear actas de salida y llegada para Guayaquil para permitir calcular tiempos reales
            // Salida: Hace 2 días a las 06:00 AM (2 horas suplementarias antes de las 08:00 AM)
            $salidaDate = $twoDaysAgo->copy()->setTime(6, 0, 0);
            DeliveryReceptionAct::firstOrCreate(
                ['route_sheet_id' => $sheet2->id, 'registration_type' => 'salida'],
                [
                    'mechanic_or_guard_id' => $jefe->id,
                    'fuel_level' => 'full',
                    'checkpoint_mileage' => 45000,
                    'general_observations' => 'Salida autorizada.',
                    'created_at' => $salidaDate
                ]
            );

            // Llegada: Hoy a las 21:00 PM (4 horas suplementarias después de las 17:00 PM)
            $llegadaDate = $today->copy()->setTime(21, 0, 0);
            DeliveryReceptionAct::firstOrCreate(
                ['route_sheet_id' => $sheet2->id, 'registration_type' => 'llegada'],
                [
                    'mechanic_or_guard_id' => $jefe->id,
                    'fuel_level' => '1/2',
                    'checkpoint_mileage' => 45400,
                    'general_observations' => 'Llegada registrada.',
                    'created_at' => $llegadaDate
                ]
            );
        }
    }
}
