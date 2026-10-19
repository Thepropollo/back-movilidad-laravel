<?php

namespace Database\Seeders;

use Carbon\Carbon;
use Domain\Auth\Models\DailyAttendance;
use Domain\Auth\Models\Driver;
use Domain\Auth\Models\DriverLicense;
use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Domain\Requests\Models\FuelOrder;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\PassengerManifest;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\RouteSheetStop;
use Domain\Requests\Models\ServiceStation;
use Domain\Requests\Models\TripEvaluation;
use Domain\Requests\Support\RequestWorkflow;
use Domain\Vehicles\Models\Vehicle;
use Domain\Workshop\Models\IssueLog;
use Domain\Workshop\Models\SupplyInventory;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Domain\Workshop\Models\WorkOrderSupplyProvision;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OctoberOperationalSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('OctoberOperationalSeeder solo se puede ejecutar en entornos local o testing.');
        }

        DB::transaction(function (): void {
            $secretaria = User::where('email', 'secretaria@uleam.edu.ec')->first();
            $vicerrector = User::where('email', 'vicerrector@uleam.edu.ec')->first();
            $mecanico = User::where('email', 'mecanico@uleam.edu.ec')->first();
            $docenteInformatica = User::where('email', 'docente@uleam.edu.ec')->first();
            $decano = User::where('email', 'decano@uleam.edu.ec')->first();

            $docenteRole = Role::where('name', 'docente')->first();
            $estudianteRole = Role::where('name', 'estudiante')->first();
            $conductorRole = Role::where('name', 'conductor')->first();

            // 1. Docentes de facultades representativas de ULEAM
            $docenteMedicina = User::firstOrCreate(
                ['email' => 'docente.medicina@uleam.edu.ec'],
                [
                    'national_id' => '1311223344',
                    'first_name' => 'Dra. María Elena',
                    'last_name' => 'García (Medicina)',
                    'password' => Hash::make('password'),
                    'faculty_institution' => 'FACULTAD DE CIENCIAS MÉDICAS',
                    'role_id' => $docenteRole?->id,
                ]
            );
            $docenteMedicina->syncRoleNames(['docente'], 'docente');

            $docenteCivil = User::firstOrCreate(
                ['email' => 'docente.civil@uleam.edu.ec'],
                [
                    'national_id' => '1322334455',
                    'first_name' => 'Ing. Carlos',
                    'last_name' => 'Zambrano (Civil)',
                    'password' => Hash::make('password'),
                    'faculty_institution' => 'FACULTAD DE INGENIERÍA CIVIL',
                    'role_id' => $docenteRole?->id,
                ]
            );
            $docenteCivil->syncRoleNames(['docente'], 'docente');

            $docenteMar = User::firstOrCreate(
                ['email' => 'docente.mar@uleam.edu.ec'],
                [
                    'national_id' => '1333445566',
                    'first_name' => 'Biól. Patricio',
                    'last_name' => 'Mendoza (Ciencias del Mar)',
                    'password' => Hash::make('password'),
                    'faculty_institution' => 'FACULTAD DE CIENCIAS DEL MAR',
                    'role_id' => $docenteRole?->id,
                ]
            );
            $docenteMar->syncRoleNames(['docente'], 'docente');

            $docenteEducacion = User::firstOrCreate(
                ['email' => 'docente.educacion@uleam.edu.ec'],
                [
                    'national_id' => '1344556677',
                    'first_name' => 'Lcda. Carmen',
                    'last_name' => 'Intriago (Educación)',
                    'password' => Hash::make('password'),
                    'faculty_institution' => 'FACULTAD DE EDUCACIÓN, TURISMO Y ARTES',
                    'role_id' => $docenteRole?->id,
                ]
            );
            $docenteEducacion->syncRoleNames(['docente'], 'docente');

            // 2. Estudiantes activos con invitaciones y evaluaciones
            $estudiantes = [];
            $nombresEstudiantes = [
                ['Kevin', 'Alcívar', 'kevin.alcivar@est.uleam.edu.ec', '1315550001'],
                ['Valeria', 'Solórzano', 'valeria.solorzano@est.uleam.edu.ec', '1315550002'],
                ['Bryan', 'Moreira', 'bryan.moreira@est.uleam.edu.ec', '1315550003'],
                ['Dayana', 'Párraga', 'dayana.parraga@est.uleam.edu.ec', '1315550004'],
                ['Mateo', 'Delgado', 'mateo.delgado@est.uleam.edu.ec', '1315550005'],
            ];

            foreach ($nombresEstudiantes as $estData) {
                $est = User::firstOrCreate(
                    ['email' => $estData[2]],
                    [
                        'national_id' => $estData[3],
                        'first_name' => $estData[0],
                        'last_name' => $estData[1],
                        'password' => Hash::make('password'),
                        'faculty_institution' => 'FACULTAD DE CIENCIAS INFORMÁTICAS',
                        'role_id' => $estudianteRole?->id,
                    ]
                );
                $est->syncRoleNames(['estudiante'], 'estudiante');
                $estudiantes[] = $est;
            }

            // 3. Choferes institucionales
            $conductores = Driver::with('user')->get();
            $conductor1 = $conductores[0] ?? null;
            $conductor2 = $conductores[1] ?? null;

            if (! $conductor1) {
                $userCond1 = User::firstOrCreate(
                    ['email' => 'chofer.principal@uleam.edu.ec'],
                    [
                        'national_id' => '1308889991',
                        'first_name' => 'Manuel',
                        'last_name' => 'Bailón (Chofer)',
                        'password' => Hash::make('password'),
                        'faculty_institution' => 'DIRECCIÓN DE TRANSPORTE Y MOVILIDAD',
                        'role_id' => $conductorRole?->id,
                    ]
                );
                $conductor1 = Driver::firstOrCreate(
                    ['user_id' => $userCond1->id],
                    ['contract_type' => 'nombramiento', 'is_available' => true]
                );
            }

            if (! $conductor2) {
                $userCond2 = User::firstOrCreate(
                    ['email' => 'chofer.auxiliar@uleam.edu.ec'],
                    [
                        'national_id' => '1308889992',
                        'first_name' => 'Jorge',
                        'last_name' => 'Pico (Chofer)',
                        'password' => Hash::make('password'),
                        'faculty_institution' => 'DIRECCIÓN DE TRANSPORTE Y MOVILIDAD',
                        'role_id' => $conductorRole?->id,
                    ]
                );
                $conductor2 = Driver::firstOrCreate(
                    ['user_id' => $userCond2->id],
                    ['contract_type' => 'contrato', 'is_available' => true]
                );
            }

            // Registrar asistencias de conductores de octubre 2026 (días 1 al 17)
            for ($day = 1; $day <= 17; $day++) {
                $dateStr = sprintf('2026-10-%02d', $day);
                // Si es fin de semana, solo algunos días tuvieron guardia
                $carbonDate = Carbon::parse($dateStr);
                $isWeekend = $carbonDate->isWeekend();

                if (! $isWeekend || $day === 3 || $day === 10 || $day === 17) {
                    DailyAttendance::firstOrCreate(
                        ['driver_id' => $conductor1->id, 'date' => $dateStr],
                        [
                            'check_in_time' => '07:45:00',
                            'check_out_time' => '16:45:00',
                            'notes' => $isWeekend ? 'Guardia y despacho de comisión extraordinaria' : 'Turno regular institucional sin novedades',
                        ]
                    );

                    DailyAttendance::firstOrCreate(
                        ['driver_id' => $conductor2->id, 'date' => $dateStr],
                        [
                            'check_in_time' => '07:55:00',
                            'check_out_time' => '17:00:00',
                            'notes' => 'Asistencia registrada en garita principal',
                        ]
                    );
                }
            }

            // 4. Flota vehicular institucional
            $vehiculoToyota = Vehicle::firstOrCreate(
                ['plate' => 'MBA-1234'],
                [
                    'brand' => 'Toyota',
                    'model' => 'Hilux CD 4x4',
                    'year' => 2023,
                    'color' => 'Blanco Institucional',
                    'fuel_type' => 'diesel',
                    'current_mileage' => 48250,
                    'next_oil_change_mileage' => 50000,
                    'operational_status' => 'en_viaje', // En viaje HOY hacia Guayaquil
                ]
            );

            $vehiculoVan = Vehicle::firstOrCreate(
                ['plate' => 'MBA-7001'],
                [
                    'brand' => 'Hyundai',
                    'model' => 'H-1 Minibús (16 Pasajeros)',
                    'year' => 2022,
                    'color' => 'Plateado',
                    'fuel_type' => 'diesel',
                    'current_mileage' => 62100,
                    'next_oil_change_mileage' => 65000,
                    'operational_status' => 'disponible',
                ]
            );

            $vehiculoDmax = Vehicle::firstOrCreate(
                ['plate' => 'MBA-3579'],
                [
                    'brand' => 'Chevrolet',
                    'model' => 'D-Max High Country',
                    'year' => 2021,
                    'color' => 'Gris Plomo',
                    'fuel_type' => 'diesel',
                    'current_mileage' => 84300,
                    'next_oil_change_mileage' => 85000,
                    'operational_status' => 'en_taller', // En taller actualmente
                ]
            );

            $vehiculoBus = Vehicle::firstOrCreate(
                ['plate' => 'MBA-4812'],
                [
                    'brand' => 'Hino',
                    'model' => 'AK8J Bus Universitario (38 Pasajeros)',
                    'year' => 2020,
                    'color' => 'Azul Marino ULEAM',
                    'fuel_type' => 'diesel',
                    'current_mileage' => 112400,
                    'next_oil_change_mileage' => 115000,
                    'operational_status' => 'disponible',
                ]
            );

            $vehiculoSedan = Vehicle::firstOrCreate(
                ['plate' => 'MBA-5678'],
                [
                    'brand' => 'Kia',
                    'model' => 'Cerato Sedán',
                    'year' => 2022,
                    'color' => 'Negro',
                    'fuel_type' => 'super',
                    'current_mileage' => 50150,
                    'next_oil_change_mileage' => 50000, // Aceite superado para generar alerta real en dashboard
                    'operational_status' => 'disponible',
                ]
            );

            $station = ServiceStation::firstOrCreate(
                ['commercial_name' => 'Estación de Servicio Primax Tarqui'],
                [
                    'ruc' => '1391745628001',
                    'address' => 'Av. 4 de Noviembre y Calle 115, Tarqui, Manta',
                    'active_agreement' => true,
                ]
            );

            // 5. Insumos y Órdenes de Trabajo del Taller
            $filtroAceite = SupplyInventory::firstOrCreate(
                ['supply_name' => 'Filtro de Aceite Hilux / D-Max'],
                ['current_stock' => 14, 'measurement_unit' => 'Unidad']
            );

            $pastillasFreno = SupplyInventory::firstOrCreate(
                ['supply_name' => 'Pastillas de Freno Delanteras'],
                ['current_stock' => 6, 'measurement_unit' => 'Juego']
            );

            // Novedad activa en taller (D-Max con frenos)
            $novedadFrenos = IssueLog::firstOrCreate(
                ['description' => 'Desgaste severo en pastillas de freno delanteras y chirrido al desacelerar.'],
                [
                    'vehicle_id' => $vehiculoDmax->id,
                    'reporting_driver_id' => $conductor2->user_id,
                    'breakdown_date' => '2026-10-15',
                    'status' => 'en_revision',
                ]
            );

            // Orden de trabajo activa en taller con mecánico asignado
            $ordenTallerActiva = WorkshopWorkOrder::firstOrCreate(
                ['issue_log_id' => $novedadFrenos->id],
                [
                    'vehicle_id' => $vehiculoDmax->id,
                    'responsible_mechanic_id' => $mecanico?->id ?? 1,
                    'supervisor_id' => $secretaria?->id ?? 1,
                    'maintenance_type' => 'correctivo',
                    'work_details' => 'Sustitución inmediata de juego de pastillas de freno y rectificación ligera de discos.',
                    'entry_date' => Carbon::parse('2026-10-16 08:30:00'),
                    'exit_date' => null, // Sigue en taller hoy
                ]
            );

            // Novedad previa ya solventada a inicios de octubre
            $novedadSolventada = IssueLog::firstOrCreate(
                ['description' => 'Filtro de aire saturado y pérdida de potencia en pendiente.'],
                [
                    'vehicle_id' => $vehiculoToyota->id,
                    'reporting_driver_id' => $conductor1->user_id,
                    'breakdown_date' => '2026-10-04',
                    'status' => 'solventado',
                ]
            );

            $ordenSolventada = WorkshopWorkOrder::firstOrCreate(
                ['issue_log_id' => $novedadSolventada->id],
                [
                    'vehicle_id' => $vehiculoToyota->id,
                    'responsible_mechanic_id' => $mecanico?->id ?? 1,
                    'supervisor_id' => $secretaria?->id ?? 1,
                    'maintenance_type' => 'preventivo',
                    'work_details' => 'Cambio de filtro de aire y limpieza de sensores MAF.',
                    'entry_date' => Carbon::parse('2026-10-05 09:00:00'),
                    'exit_date' => Carbon::parse('2026-10-05 16:30:00'),
                ]
            );

            // 6. Solicitudes y Comisiones de Movilización en Octubre 2026
            $catalogoViajesOctubre = [
                // Octubre 02 - Finalizada (Medicina)
                [
                    'docente' => $docenteMedicina,
                    'tipo' => 'interna',
                    'origen' => 'MANTA',
                    'destino' => 'PORTOVIEJO',
                    'direccion' => 'Hospital General Verdi Cevallos Balda',
                    'motivo' => 'Supervisión de internado rotativo y prácticas clínicas de 8vo semestre.',
                    'salida' => '2026-10-02 07:30:00',
                    'retorno' => '2026-10-02 16:30:00',
                    'dias' => 1,
                    'costo' => 45.00,
                    'ocupantes' => 12,
                    'vehiculo' => $vehiculoVan,
                    'conductor' => $conductor1,
                    'km_ini' => 61750,
                    'km_fin' => 61840,
                    'status' => 'finalizada',
                    'calif_chofer' => 5,
                    'calif_vehiculo' => 5,
                    'comentario' => 'Puntualidad impecable del chofer y excelente estado del aire acondicionado.',
                    'galones' => 6.5,
                ],
                // Octubre 05 - Finalizada (Civil)
                [
                    'docente' => $docenteCivil,
                    'tipo' => 'interna',
                    'origen' => 'MANTA',
                    'destino' => 'CHONE',
                    'direccion' => 'Campamento de Obras Hidráulicas del Río Chone',
                    'motivo' => 'Levantamiento topográfico y ensayo de suelos con estudiantes de Ingeniería.',
                    'salida' => '2026-10-05 06:45:00',
                    'retorno' => '2026-10-06 18:00:00',
                    'dias' => 2,
                    'costo' => 180.00,
                    'ocupantes' => 28,
                    'vehiculo' => $vehiculoBus,
                    'conductor' => $conductor2,
                    'km_ini' => 111800,
                    'km_fin' => 112040,
                    'status' => 'finalizada',
                    'calif_chofer' => 5,
                    'calif_vehiculo' => 4,
                    'comentario' => 'Conducción segura en caminos de segundo orden. Muy recomendado.',
                    'galones' => 22.0,
                ],
                // Octubre 08 - Finalizada (Ciencias del Mar)
                [
                    'docente' => $docenteMar,
                    'tipo' => 'interna',
                    'origen' => 'MANTA',
                    'destino' => 'BAHÍA DE CARÁQUEZ',
                    'direccion' => 'Estación de Biodiversidad Marina ULEAM Bahía',
                    'motivo' => 'Monitoreo de parámetros fisicoquímicos en estuario y toma de muestras.',
                    'salida' => '2026-10-08 07:00:00',
                    'retorno' => '2026-10-08 17:30:00',
                    'dias' => 1,
                    'costo' => 60.00,
                    'ocupantes' => 14,
                    'vehiculo' => $vehiculoVan,
                    'conductor' => $conductor1,
                    'km_ini' => 61845,
                    'km_fin' => 61995,
                    'status' => 'finalizada',
                    'calif_chofer' => 5,
                    'calif_vehiculo' => 5,
                    'comentario' => 'Unidad limpia, viaje confortable y retorno según cronograma.',
                    'galones' => 9.0,
                ],
                // Octubre 12 - Finalizada (Educación)
                [
                    'docente' => $docenteEducacion,
                    'tipo' => 'interna',
                    'origen' => 'MANTA',
                    'destino' => 'JIPIJAPA',
                    'direccion' => 'Campus Pedagógico y Unidad Educativa del Milenio',
                    'motivo' => 'Evaluación de proyectos de vinculación escolar y talleres pedagógicos.',
                    'salida' => '2026-10-12 08:00:00',
                    'retorno' => '2026-10-12 16:00:00',
                    'dias' => 1,
                    'costo' => 50.00,
                    'ocupantes' => 10,
                    'vehiculo' => $vehiculoVan,
                    'conductor' => $conductor2,
                    'km_ini' => 62000,
                    'km_fin' => 62100,
                    'status' => 'finalizada',
                    'calif_chofer' => 4,
                    'calif_vehiculo' => 5,
                    'comentario' => 'Viaje tranquilo y chofer atento a las indicaciones de paradas.',
                    'galones' => 7.0,
                ],
                // Octubre 14 - Finalizada (Informática - Decanato)
                [
                    'docente' => $decano,
                    'tipo' => 'externa',
                    'origen' => 'MANTA',
                    'destino' => 'QUITO',
                    'direccion' => 'Sede Senescyt y Auditorio EPN',
                    'motivo' => 'Mesa técnica interinstitucional sobre acreditación de carreras de software.',
                    'salida' => '2026-10-13 06:00:00',
                    'retorno' => '2026-10-14 20:00:00',
                    'dias' => 2,
                    'costo' => 240.00,
                    'ocupantes' => 4,
                    'vehiculo' => $vehiculoToyota,
                    'conductor' => $conductor1,
                    'km_ini' => 47400,
                    'km_fin' => 48200,
                    'status' => 'finalizada',
                    'calif_chofer' => 5,
                    'calif_vehiculo' => 5,
                    'comentario' => 'Excelente viaje a la sierra. Chofer con gran experiencia en carretera.',
                    'galones' => 24.5,
                ],
            ];

            foreach ($catalogoViajesOctubre as $item) {
                $dep = Carbon::parse($item['salida']);
                $ret = Carbon::parse($item['retorno']);

                $sol = MobilizationRequest::firstOrCreate(
                    [
                        'travel_reason' => $item['motivo'],
                        'departure_date' => $dep->toDateString(),
                    ],
                    [
                        'requester_id' => $item['docente']->id,
                        'mobilization_type' => $item['tipo'],
                        'origin' => $item['origen'],
                        'destination' => $item['destino'],
                        'destination_address' => $item['direccion'],
                        'departure_time' => $dep->toTimeString(),
                        'return_date' => $ret->toDateString(),
                        'return_time' => $ret->toTimeString(),
                        'estimated_days' => $item['dias'],
                        'projected_cost' => $item['costo'],
                        'status' => $item['status'],
                        'occupant_count' => $item['ocupantes'],
                        'secretaria_approver_id' => $secretaria?->id,
                        'rectorate_approver_id' => $item['tipo'] === 'externa' ? $vicerrector?->id : null,
                        'created_at' => $dep->copy()->subDays(4),
                    ]
                );

                $sheet = RouteSheet::firstOrCreate(
                    ['request_id' => $sol->id],
                    [
                        'vehicle_id' => $item['vehiculo']->id,
                        'driver_id' => $item['conductor']->id,
                        'transport_chief_id' => $secretaria?->id ?? 1,
                        'initial_mileage' => $item['km_ini'],
                        'final_mileage' => $item['km_fin'],
                        'trip_status' => 'finalizado',
                        'driver_response' => 'aceptado',
                        'driver_responded_at' => $dep->copy()->subDays(2),
                    ]
                );

                // Parada de control registrada
                RouteSheetStop::firstOrCreate(
                    ['route_sheet_id' => $sheet->id, 'sequence' => 1],
                    [
                        'location' => $item['destino'],
                        'visited_canton' => $item['destino'],
                        'departure_at' => $dep->copy()->addHours(2),
                        'arrival_time' => $dep->copy()->addHours(3),
                        'odometer_km' => $item['km_ini'] + 50,
                        'latitude' => -0.9537,
                        'longitude' => -80.7189,
                        'notes' => 'Parada de llegada y desembarque de delegación.',
                    ]
                );

                // Vale de combustible despachado
                FuelOrder::firstOrCreate(
                    ['route_sheet_id' => $sheet->id],
                    [
                        'order_code' => sprintf('VALE-OCT-%04d', $sheet->id),
                        'station_id' => $station->id,
                        'transport_chief_id' => $secretaria?->id ?? 1,
                        'dispatched_fuel_type' => $item['vehiculo']->fuel_type,
                        'authorized_gallons' => $item['galones'],
                        'actual_dispatched_gallons' => $item['galones'],
                        'total_amount_paid' => round($item['galones'] * 1.80, 2),
                        'order_status' => 'despachada',
                        'dispatch_date' => $dep->copy()->subHour(),
                    ]
                );

                // Calificación post-viaje
                TripEvaluation::firstOrCreate(
                    ['route_sheet_id' => $sheet->id, 'passenger_id' => $item['docente']->id],
                    [
                        'driver_rating' => $item['calif_chofer'],
                        'vehicle_rating' => $item['calif_vehiculo'],
                        'comments' => $item['comentario'],
                    ]
                );

                // Manifiesto de estudiantes invitados y aceptados
                foreach ($estudiantes as $estudiante) {
                    PassengerManifest::firstOrCreate(
                        ['request_id' => $sol->id, 'user_id' => $estudiante->id],
                        [
                            'invitation_status' => 'aceptado',
                            'responded_at' => $dep->copy()->subDays(3),
                        ]
                    );
                }
            }

            // 7. VIAJE ACTIVO HOY: Octubre 17, 2026 (En Ruta)
            $solicitudHoy = MobilizationRequest::firstOrCreate(
                [
                    'travel_reason' => 'Participación en el Congreso Nacional de Tecnologías Aplicadas e Inteligencia Artificial.',
                    'departure_date' => '2026-10-17',
                ],
                [
                    'requester_id' => $docenteInformatica?->id ?? 1,
                    'mobilization_type' => 'externa',
                    'origin' => 'MANTA',
                    'destination' => 'GUAYAQUIL',
                    'destination_address' => 'Universidad de Guayaquil, Facultad de Ingeniería',
                    'departure_time' => '06:30:00',
                    'return_date' => '2026-10-17',
                    'return_time' => '21:00:00',
                    'estimated_days' => 1,
                    'projected_cost' => 95.00,
                    'status' => 'en_ruta', // ¡En ruta hoy!
                    'occupant_count' => 5,
                    'secretaria_approver_id' => $secretaria?->id,
                    'rectorate_approver_id' => $vicerrector?->id,
                    'created_at' => Carbon::parse('2026-10-14 10:00:00'),
                ]
            );

            $hojaRutaHoy = RouteSheet::firstOrCreate(
                ['request_id' => $solicitudHoy->id],
                [
                    'vehicle_id' => $vehiculoToyota->id,
                    'driver_id' => $conductor1->id,
                    'transport_chief_id' => $secretaria?->id ?? 1,
                    'initial_mileage' => 48200,
                    'final_mileage' => null, // Aún en ruta
                    'trip_status' => 'en_ruta', // Conductor viajando en este instante
                    'driver_response' => 'aceptado',
                    'driver_responded_at' => Carbon::parse('2026-10-16 14:20:00'),
                ]
            );

            // Paradas registradas HOY a lo largo de la ruta
            RouteSheetStop::firstOrCreate(
                ['route_sheet_id' => $hojaRutaHoy->id, 'sequence' => 1],
                [
                    'location' => 'Manta - Campus Matriz ULEAM',
                    'visited_canton' => 'Manta',
                    'departure_at' => Carbon::parse('2026-10-17 06:35:00'),
                    'arrival_time' => Carbon::parse('2026-10-17 06:30:00'),
                    'odometer_km' => 48200,
                    'latitude' => -0.9537,
                    'longitude' => -80.7189,
                    'notes' => 'Salida puntual con delegación académica.',
                ]
            );

            RouteSheetStop::firstOrCreate(
                ['route_sheet_id' => $hojaRutaHoy->id, 'sequence' => 2],
                [
                    'location' => 'Jipijapa - Parada de Control Peaje',
                    'visited_canton' => 'Jipijapa',
                    'departure_at' => Carbon::parse('2026-10-17 08:00:00'),
                    'arrival_time' => Carbon::parse('2026-10-17 07:50:00'),
                    'odometer_km' => 48255,
                    'latitude' => -1.3486,
                    'longitude' => -80.5786,
                    'notes' => 'Control técnico y verificación de neumáticos.',
                ]
            );

            FuelOrder::firstOrCreate(
                ['route_sheet_id' => $hojaRutaHoy->id],
                [
                    'order_code' => 'VALE-OCT-HOY-001',
                    'station_id' => $station->id,
                    'transport_chief_id' => $secretaria?->id ?? 1,
                    'dispatched_fuel_type' => 'diesel',
                    'authorized_gallons' => 16.0,
                    'actual_dispatched_gallons' => 16.0,
                    'total_amount_paid' => 28.80,
                    'order_status' => 'despachada',
                    'dispatch_date' => Carbon::parse('2026-10-17 06:15:00'),
                ]
            );

            // 8. SALIDAS PROGRAMADAS PARA LAS PRÓXIMAS 48 HORAS (18 y 19 de Octubre)
            // Salida Mañana (18 de Octubre)
            $solicitudManana = MobilizationRequest::firstOrCreate(
                [
                    'travel_reason' => 'Práctica de campo de Topografía y Obras Viales en Chone.',
                    'departure_date' => '2026-10-18',
                ],
                [
                    'requester_id' => $docenteCivil->id,
                    'mobilization_type' => 'interna',
                    'origin' => 'MANTA',
                    'destination' => 'CHONE',
                    'destination_address' => 'Campus Extensión ULEAM Chone y Río Grande',
                    'departure_time' => '07:00:00',
                    'return_date' => '2026-10-18',
                    'return_time' => '17:00:00',
                    'estimated_days' => 1,
                    'projected_cost' => 110.00,
                    'status' => 'aprobada',
                    'occupant_count' => 32,
                    'secretaria_approver_id' => $secretaria?->id,
                    'created_at' => Carbon::parse('2026-10-15 09:30:00'),
                ]
            );

            RouteSheet::firstOrCreate(
                ['request_id' => $solicitudManana->id],
                [
                    'vehicle_id' => $vehiculoBus->id,
                    'driver_id' => $conductor2->id,
                    'transport_chief_id' => $secretaria?->id ?? 1,
                    'initial_mileage' => null,
                    'final_mileage' => null,
                    'trip_status' => 'programado', // Salida en 48 horas lista
                    'driver_response' => 'aceptado',
                    'driver_responded_at' => Carbon::parse('2026-10-16 16:00:00'),
                ]
            );

            // Salida Pasado Mañana (19 de Octubre)
            $solicitudPasadoManana = MobilizationRequest::firstOrCreate(
                [
                    'travel_reason' => 'Brigada médica comunitaria y atención odontológica preventiva.',
                    'departure_date' => '2026-10-19',
                ],
                [
                    'requester_id' => $docenteMedicina->id,
                    'mobilization_type' => 'interna',
                    'origin' => 'MANTA',
                    'destination' => 'PEDERNALES',
                    'destination_address' => 'Centro de Salud Cojimíes y Campus Pedernales',
                    'departure_time' => '06:00:00',
                    'return_date' => '2026-10-20',
                    'return_time' => '18:00:00',
                    'estimated_days' => 2,
                    'projected_cost' => 195.00,
                    'status' => 'aprobada',
                    'occupant_count' => 15,
                    'secretaria_approver_id' => $secretaria?->id,
                    'created_at' => Carbon::parse('2026-10-16 11:00:00'),
                ]
            );

            RouteSheet::firstOrCreate(
                ['request_id' => $solicitudPasadoManana->id],
                [
                    'vehicle_id' => $vehiculoVan->id,
                    'driver_id' => $conductor1->id,
                    'transport_chief_id' => $secretaria?->id ?? 1,
                    'initial_mileage' => null,
                    'final_mileage' => null,
                    'trip_status' => 'programado',
                    'driver_response' => 'pendiente', // Chofer tiene que aceptar en su panel
                ]
            );

            // 9. BANDEJA DE SECRETARÍA: Solicitudes pendientes de autorizar
            MobilizationRequest::firstOrCreate(
                [
                    'travel_reason' => 'Toma de muestras oceanográficas en la Bahía de Caráquez para tesis de grado.',
                    'departure_date' => '2026-10-22',
                ],
                [
                    'requester_id' => $docenteMar->id,
                    'mobilization_type' => 'interna',
                    'origin' => 'MANTA',
                    'destination' => 'BAHÍA DE CARÁQUEZ',
                    'destination_address' => 'Isla Corazón y Estuario del Río Chone',
                    'departure_time' => '07:30:00',
                    'return_date' => '2026-10-22',
                    'return_time' => '17:00:00',
                    'estimated_days' => 1,
                    'projected_cost' => 55.00,
                    'status' => 'pendiente_secretaria', // Para que Secretaría autorice en su bandeja
                    'occupant_count' => 8,
                    'created_at' => Carbon::parse('2026-10-17 09:15:00'),
                ]
            );

            MobilizationRequest::firstOrCreate(
                [
                    'travel_reason' => 'Taller de articulación curricular y capacitación docente en la Extensión El Carmen.',
                    'departure_date' => '2026-10-23',
                ],
                [
                    'requester_id' => $docenteEducacion->id,
                    'mobilization_type' => 'interna',
                    'origin' => 'MANTA',
                    'destination' => 'EL CARMEN',
                    'destination_address' => 'Campus Extensión ULEAM El Carmen',
                    'departure_time' => '06:30:00',
                    'return_date' => '2026-10-24',
                    'return_time' => '18:00:00',
                    'estimated_days' => 2,
                    'projected_cost' => 160.00,
                    'status' => 'pendiente_secretaria',
                    'occupant_count' => 14,
                    'created_at' => Carbon::parse('2026-10-17 11:30:00'),
                ]
            );

            // 10. BANDEJA DE VICERRECTORADO: Comisiones externas pendientes de firma
            MobilizationRequest::firstOrCreate(
                [
                    'travel_reason' => 'Representación institucional en la Cumbre de Decanos de Ingeniería del Ecuador en Cuenca.',
                    'departure_date' => '2026-10-25',
                ],
                [
                    'requester_id' => $docenteCivil->id,
                    'mobilization_type' => 'externa',
                    'origin' => 'MANTA',
                    'destination' => 'CUENCA',
                    'destination_address' => 'Universidad de Cuenca, Auditorio Central',
                    'departure_time' => '05:30:00',
                    'return_date' => '2026-10-27',
                    'return_time' => '20:00:00',
                    'estimated_days' => 3,
                    'projected_cost' => 380.00,
                    'status' => 'pendiente_rectorado', // En espera de visto bueno de Vicerrector
                    'occupant_count' => 4,
                    'secretaria_approver_id' => $secretaria?->id,
                    'created_at' => Carbon::parse('2026-10-16 15:45:00'),
                ]
            );

            MobilizationRequest::firstOrCreate(
                [
                    'travel_reason' => 'Pasantía de investigación epidemiológica en el Instituto Nacional de Salud Pública (INSPI) Quito.',
                    'departure_date' => '2026-10-28',
                ],
                [
                    'requester_id' => $docenteMedicina->id,
                    'mobilization_type' => 'externa',
                    'origin' => 'MANTA',
                    'destination' => 'QUITO',
                    'destination_address' => 'Laboratorios Centrales del INSPI Quito',
                    'departure_time' => '06:00:00',
                    'return_date' => '2026-10-30',
                    'return_time' => '19:00:00',
                    'estimated_days' => 3,
                    'projected_cost' => 420.00,
                    'status' => 'pendiente_rectorado',
                    'occupant_count' => 6,
                    'secretaria_approver_id' => $secretaria?->id,
                    'created_at' => Carbon::parse('2026-10-17 14:00:00'),
                ]
            );
        });
    }
}
