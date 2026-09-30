<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\Driver;
use Domain\Auth\Models\User;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\PassengerManifest;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Support\RequestWorkflow;
use Domain\Vehicles\Models\Vehicle;
use Domain\Workshop\Models\IssueLog;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardMetricsController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $focus = (string) $request->query('focus', '');
        $order = match ($focus) {
            'secretaria' => ['secretaria', 'vicerrector', 'responsable_facultad', 'docente', 'mecanico', 'conductor', 'estudiante'],
            'vicerrector' => ['vicerrector', 'secretaria', 'responsable_facultad', 'docente', 'mecanico', 'conductor', 'estudiante'],
            'responsable_facultad' => ['responsable_facultad', 'docente', 'secretaria', 'vicerrector', 'mecanico', 'conductor', 'estudiante'],
            'docente' => ['docente', 'responsable_facultad', 'secretaria', 'vicerrector', 'mecanico', 'conductor', 'estudiante'],
            'mecanico' => ['mecanico', 'conductor', 'secretaria', 'vicerrector', 'responsable_facultad', 'docente', 'estudiante'],
            'conductor' => ['conductor', 'mecanico', 'secretaria', 'vicerrector', 'responsable_facultad', 'docente', 'estudiante'],
            'estudiante' => ['estudiante', 'docente', 'secretaria', 'vicerrector', 'responsable_facultad', 'mecanico', 'conductor'],
            default => ['secretaria', 'vicerrector', 'responsable_facultad', 'docente', 'conductor', 'mecanico', 'estudiante'],
        };

        foreach ($order as $role) {
            $payload = match ($role) {
                'secretaria' => $user->hasRole(['secretaria', 'jefe_transporte']) ? $this->secretaria($user) : null,
                'vicerrector' => $user->hasRole(['vicerrector', 'rector']) ? $this->vicerrector($user) : null,
                'responsable_facultad' => $user->hasRole(['responsable_facultad']) ? $this->facultad($user) : null,
                'docente' => $user->hasRole(['docente', 'solicitante']) ? $this->docente($user) : null,
                'mecanico' => $user->hasRole(['mecanico']) ? $this->mecanico($user) : null,
                'conductor' => $user->hasRole(['conductor', 'chofer']) ? $this->conductor($user) : null,
                'estudiante' => $user->hasRole(['estudiante', 'pasajero']) ? $this->estudiante($user) : null,
                default => null,
            };
            if ($payload) {
                return response()->json($payload);
            }
        }

        return response()->json($this->emptyDashboard('Panel'));
    }

    private function secretaria(User $user): array
    {
        $pendingAuth = MobilizationRequest::where('status', 'pendiente_secretaria')->count();
        $pendingRectorate = MobilizationRequest::where('status', 'pendiente_rectorado')->count();
        $pendingAssign = MobilizationRequest::whereIn('status', ['autorizada_secretaria', 'aprobado_rectorado'])
            ->whereDoesntHave('routeSheet')
            ->count();
        $acceptPending = RouteSheet::where('driver_response', 'pendiente')->count();
        $oilDue = Vehicle::whereColumn('current_mileage', '>=', 'next_oil_change_mileage')->count();
        $openIssues = IssueLog::whereNotIn('status', ['cerrada', 'cerrado', 'resuelto'])->count();
        $openOrders = WorkshopWorkOrder::whereNull('exit_date')->count();
        $inWorkshop = Vehicle::where('operational_status', 'en_taller')->count();

        $byStatus = MobilizationRequest::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        $byType = MobilizationRequest::query()
            ->select('mobilization_type', DB::raw('count(*) as total'))
            ->groupBy('mobilization_type')
            ->pluck('total', 'mobilization_type')
            ->all();
        $byFaculty = MobilizationRequest::query()
            ->join('users', 'users.id', '=', 'mobilization_requests.requester_id')
            ->select('users.faculty_institution', DB::raw('count(*) as total'))
            ->groupBy('users.faculty_institution')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'faculty_institution')
            ->all();
        $monthly = RouteSheet::with('request')->get()
            ->groupBy(fn (RouteSheet $s) => optional($s->request?->departure_date)?->format('Y-m') ?: 's/f')
            ->map->count()
            ->sortKeys()
            ->take(-6)
            ->all();

        $openStatuses = ['pendiente_secretaria', 'pendiente_rectorado', 'autorizada_secretaria', 'aprobado_rectorado', 'aprobada', 'en_ruta'];
        $soon = MobilizationRequest::whereIn('status', $openStatuses)
            ->whereBetween('departure_date', [now()->toDateString(), now()->addDays(2)->toDateString()])
            ->count();
        $fleetAlerts = $oilDue + $openIssues + $inWorkshop + $openOrders;
        $recent = MobilizationRequest::with(['requester', 'routeSheet'])
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (MobilizationRequest $r) => RequestWorkflow::card($r, '/app/secretaria/flujo'));

        return [
            'title' => 'Operación de la flota',
            'subtitle' => 'Autorizar, asignar, mantener y reportar en un solo lugar.',
            'period' => now()->format('Y-m'),
            'kpis' => [
                $this->kpi('pendientes', 'Por autorizar', $pendingAuth, $pendingAuth ? 'warn' : 'ok', '/app/secretaria/autorizar', 'Bandeja digital'),
                $this->kpi('asignar', 'Por asignar', $pendingAssign, $pendingAssign ? 'warn' : 'ok', '/app/secretaria/asignar', 'Conductor y vehículo'),
                $this->kpi('proximas', 'Salidas en 48 h', $soon, $soon ? 'warn' : 'ok', '/app/secretaria/agenda', 'Prioridad por fecha'),
                $this->kpi('flota', 'Alertas de flota', $fleetAlerts, $fleetAlerts ? 'danger' : 'ok', '/app/secretaria/flota/estado', 'Aceite, taller y novedades'),
            ],
            'queue' => array_values(array_filter([
                $pendingAuth ? $this->queueItem('auth', 'Autorizar solicitudes', $pendingAuth, '/app/secretaria/autorizar', 'Internas y externas, sin oficio físico.') : null,
                $pendingRectorate ? $this->queueItem('vic', 'Externas en Vicerrectorado', $pendingRectorate, '/app/secretaria/solicitudes', 'Distintas de las internas: requieren visto bueno.') : null,
                $pendingAssign ? $this->queueItem('assign', 'Asignar conductor y vehículo', $pendingAssign, '/app/secretaria/asignar', 'Evite cruces: use la disponibilidad.') : null,
                $soon ? $this->queueItem('soon', 'Priorizar salidas próximas', $soon, '/app/secretaria/agenda', 'Agenda de las próximas 48 horas.') : null,
                $fleetAlerts ? $this->queueItem('fleet', 'Atender mantenimiento', $fleetAlerts, '/app/secretaria/taller', 'Preventivo antes de que la unidad falle.') : null,
                $acceptPending ? $this->queueItem('driver', 'Reasignar si el conductor rechaza', $acceptPending, '/app/secretaria/reasignar', 'Hojas de ruta sin aceptación.') : null,
            ])),
            'charts' => [
                $this->chart('por_tipo', 'Interna vs externa', $byType),
                $this->chart('por_estado', 'Solicitudes por estado', $byStatus),
                $this->chart('por_facultad', 'Demanda por facultad', $byFaculty),
                $this->chart('mensual', 'Viajes por mes', $monthly),
            ],
            'recent' => $recent,
            'exports' => [
                ['label' => 'Informe mensual', 'kind' => 'mensual', 'href' => '/app/secretaria/reportes'],
                ['label' => 'Documentos PDF', 'kind' => 'documentos', 'href' => '/app/secretaria/documentos'],
                ['label' => 'Control de aceite', 'kind' => 'aceite', 'href' => '/app/secretaria/reportes'],
                ['label' => 'Libro de novedades', 'kind' => 'novedades', 'href' => '/app/secretaria/reportes'],
            ],
        ];
    }

    private function vicerrector(User $user): array
    {
        $pending = MobilizationRequest::where('status', 'pendiente_rectorado')->count();
        $approved = MobilizationRequest::where('mobilization_type', 'externa')->whereIn('status', ['aprobado_rectorado', 'aprobada'])->count();
        $rejected = MobilizationRequest::where('mobilization_type', 'externa')
            ->whereIn('status', ['rechazada', 'rechazado_rectorado', 'rechazada_rectorado'])
            ->count();
        $thisMonth = MobilizationRequest::where('mobilization_type', 'externa')
            ->whereMonth('departure_date', now()->month)
            ->whereYear('departure_date', now()->year)
            ->count();
        $byStatus = MobilizationRequest::where('mobilization_type', 'externa')
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();
        $byFaculty = MobilizationRequest::where('mobilization_type', 'externa')
            ->join('users', 'users.id', '=', 'mobilization_requests.requester_id')
            ->select('users.faculty_institution', DB::raw('count(*) as total'))
            ->groupBy('users.faculty_institution')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'faculty_institution')
            ->all();

        return [
            'title' => 'Autorización de viajes externos',
            'subtitle' => 'Constancia de vistos buenos y rechazos de Vicerrectorado.',
            'period' => now()->format('Y-m'),
            'kpis' => [
                $this->kpi('pendientes', 'Por aprobar', $pending, $pending ? 'warn' : 'ok', '/app/vicerrector/pendientes', 'Bandeja de externas'),
                $this->kpi('aprobadas', 'Externas autorizadas', $approved, 'ok', '/app/vicerrector/historial'),
                $this->kpi('rechazadas', 'Externas rechazadas', $rejected, $rejected ? 'danger' : 'ok', '/app/vicerrector/historial'),
                $this->kpi('mes', 'Externas del mes', $thisMonth, 'info', '/app/vicerrector/reportes'),
            ],
            'queue' => array_values(array_filter([
                $pending ? $this->queueItem('vic', 'Revisar viajes externos', $pending, '/app/vicerrector/pendientes', 'Destino, fechas y justificación académica.') : null,
            ])),
            'charts' => [
                $this->chart('por_facultad', 'Externas por facultad', $byFaculty),
                $this->chart('por_estado', 'Externas por estado', $byStatus),
            ],
            'recent' => MobilizationRequest::with('routeSheet')->where('mobilization_type', 'externa')->orderByDesc('id')->limit(5)->get()
                ->map(fn ($r) => RequestWorkflow::card($r, '/app/vicerrector/pendientes')),
            'exports' => [
                ['label' => 'Informe mensual', 'kind' => 'mensual', 'href' => '/app/vicerrector/reportes'],
                ['label' => 'Documentos PDF', 'kind' => 'documentos', 'href' => '/app/vicerrector/documentos'],
            ],
        ];
    }

    private function facultad(User $user): array
    {
        $base = MobilizationRequest::whereHas('requester', fn ($q) => $q->where('faculty_institution', $user->faculty_institution));
        $pending = (clone $base)->whereIn('status', ['pendiente_secretaria', 'pendiente_rectorado'])->count();
        $approved = (clone $base)->whereIn('status', ['autorizada_secretaria', 'aprobado_rectorado', 'aprobada', 'en_ruta', 'finalizada'])->count();
        $thisMonth = (clone $base)->whereMonth('departure_date', now()->month)->whereYear('departure_date', now()->year)->count();
        $soon = (clone $base)->whereIn('status', ['pendiente_secretaria', 'pendiente_rectorado', 'autorizada_secretaria', 'aprobado_rectorado', 'aprobada', 'en_ruta'])
            ->whereBetween('departure_date', [now()->toDateString(), now()->addDays(2)->toDateString()])
            ->count();
        $byStatus = (clone $base)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status')->all();
        $byType = (clone $base)->select('mobilization_type', DB::raw('count(*) as total'))->groupBy('mobilization_type')->pluck('total', 'mobilization_type')->all();

        return [
            'title' => 'Movilidad de la facultad',
            'subtitle' => $user->faculty_institution ?: 'Solicitudes digitales de su unidad académica.',
            'period' => now()->format('Y-m'),
            'kpis' => [
                $this->kpi('pendientes', 'En trámite', $pending, $pending ? 'warn' : 'ok', '/app/facultad/seguimiento'),
                $this->kpi('aprobadas', 'Autorizadas', $approved, 'ok', '/app/facultad/historial'),
                $this->kpi('proximas', 'Salidas en 48 h', $soon, $soon ? 'warn' : 'ok', '/app/facultad/seguimiento'),
                $this->kpi('mes', 'Salidas del mes', $thisMonth, 'info', '/app/facultad/reportes'),
            ],
            'queue' => array_values(array_filter([
                $pending ? $this->queueItem('trace', 'Seguir trámites abiertos', $pending, '/app/facultad/seguimiento', 'Estado, responsables y fechas.') : null,
                $soon ? $this->queueItem('soon', 'Salidas próximas', $soon, '/app/facultad/seguimiento', 'Prioridad de las próximas 48 horas.') : null,
                $this->queueItem('new', 'Registrar salida digital', 1, '/app/facultad/solicitar', 'Sin oficio físico: el flujo queda en el sistema.'),
            ])),
            'charts' => [
                $this->chart('por_tipo', 'Interna vs externa', $byType),
                $this->chart('por_estado', 'Estado de la facultad', $byStatus),
            ],
            'recent' => (clone $base)->with('routeSheet')->orderByDesc('id')->limit(5)->get()
                ->map(fn ($r) => RequestWorkflow::card($r, '/app/facultad/seguimiento')),
            'exports' => [
                ['label' => 'Mis solicitudes', 'kind' => 'solicitudes', 'href' => '/app/facultad/reportes'],
                ['label' => 'Documentos PDF', 'kind' => 'documentos', 'href' => '/app/facultad/documentos'],
            ],
        ];
    }

    private function docente(User $user): array
    {
        $base = MobilizationRequest::where('requester_id', $user->id);
        $pending = (clone $base)->whereIn('status', ['pendiente_secretaria', 'pendiente_rectorado'])->count();
        $approved = (clone $base)->whereIn('status', ['aprobada', 'autorizada_secretaria', 'aprobado_rectorado', 'en_ruta'])->count();
        $thisMonth = (clone $base)->whereMonth('departure_date', now()->month)->whereYear('departure_date', now()->year)->count();
        $invitePending = PassengerManifest::whereIn('request_id', (clone $base)->select('id'))
            ->where('invitation_status', 'invitado')
            ->count();
        $byStatus = (clone $base)->select('status', DB::raw('count(*) as total'))->groupBy('status')->pluck('total', 'status')->all();
        $byType = (clone $base)->select('mobilization_type', DB::raw('count(*) as total'))->groupBy('mobilization_type')->pluck('total', 'mobilization_type')->all();

        return [
            'title' => 'Mis movilizaciones',
            'subtitle' => 'Constancia de solicitudes, participantes y documentos.',
            'period' => now()->format('Y-m'),
            'kpis' => [
                $this->kpi('pendientes', 'En autorización', $pending, $pending ? 'warn' : 'ok', '/app/docente/flujo'),
                $this->kpi('aprobadas', 'Aprobadas / en viaje', $approved, 'ok', '/app/docente/seguimiento'),
                $this->kpi('mes', 'Salidas del mes', $thisMonth, 'info', '/app/docente/reportes'),
                $this->kpi('invitaciones', 'Por confirmar', $invitePending, $invitePending ? 'warn' : 'ok', '/app/docente/participantes', 'Participantes'),
            ],
            'queue' => array_values(array_filter([
                $pending ? $this->queueItem('trace', 'Seguir autorización', $pending, '/app/docente/flujo', 'Quién actúa y qué falta.') : null,
                $invitePending ? $this->queueItem('inv', 'Confirmar participantes', $invitePending, '/app/docente/participantes', 'Invitaciones sin respuesta.') : null,
                $this->queueItem('new', 'Nueva solicitud digital', 1, '/app/docente/solicitar', 'Práctica, visita o gestión institucional.'),
            ])),
            'charts' => [
                $this->chart('por_tipo', 'Interna vs externa', $byType),
                $this->chart('por_estado', 'Mis solicitudes', $byStatus),
            ],
            'recent' => (clone $base)->with('routeSheet')->orderByDesc('id')->limit(5)->get()
                ->map(fn ($r) => RequestWorkflow::card($r, '/app/docente/flujo')),
            'exports' => [
                ['label' => 'Mis reportes', 'kind' => 'solicitudes', 'href' => '/app/docente/reportes'],
                ['label' => 'Documentos PDF', 'kind' => 'documentos', 'href' => '/app/docente/documentos'],
            ],
        ];
    }

    private function mecanico(User $user): array
    {
        $pendingActs = RouteSheet::whereDoesntHave('deliveryActs')->whereIn('trip_status', ['programado', 'en_ruta'])->count();
        $openOrders = WorkshopWorkOrder::whereNull('exit_date')->count();
        $oilDue = Vehicle::whereColumn('current_mileage', '>=', 'next_oil_change_mileage')->count();
        $issues = IssueLog::count();

        return [
            'title' => 'Taller y patio',
            'subtitle' => 'Inspecciones, órdenes de trabajo y control de aceite.',
            'period' => now()->format('Y-m'),
            'kpis' => [
                $this->kpi('inspeccion', 'Inspecciones pendientes', $pendingActs, $pendingActs ? 'warn' : 'ok', '/app/mecanico/inspeccion'),
                $this->kpi('ot', 'OT abiertas', $openOrders, $openOrders ? 'warn' : 'ok', '/app/mecanico/ordenes'),
                $this->kpi('aceite', 'Aceite vencido', $oilDue, $oilDue ? 'danger' : 'ok', '/app/mecanico/reportes'),
                $this->kpi('novedades', 'Novedades registradas', $issues, 'info', '/app/mecanico/ordenes'),
            ],
            'queue' => array_values(array_filter([
                $pendingActs ? $this->queueItem('act', 'Levantar acta de entrega-recepción', $pendingActs, '/app/mecanico/inspeccion', 'Formato PST-01-F-004.1') : null,
                $oilDue ? $this->queueItem('oil', 'Programar cambio de aceite', $oilDue, '/app/mecanico/lubricantes', 'Unidades bloqueadas para asignación.') : null,
                $this->queueItem('pdf', 'Imprimir OT y control de aceite', 1, '/app/mecanico/documentos', 'Respaldo físico de taller.'),
            ])),
            'charts' => [
                $this->chart('aceite', 'Control de aceite', [
                    'vencido' => $oilDue,
                    'ok' => max(0, Vehicle::count() - $oilDue),
                ]),
            ],
            'recent' => WorkshopWorkOrder::with('vehicle')->orderByDesc('id')->limit(6)->get()->map(fn ($o) => [
                'id' => $o->id,
                'label' => 'OT #'.$o->id.' · '.($o->vehicle?->plate ?? 's/p'),
                'meta' => $o->maintenance_type.($o->exit_date ? ' · cerrada' : ' · abierta'),
                'href' => '/app/mecanico/ordenes',
            ]),
            'exports' => [
                ['label' => 'Control de aceite PDF', 'kind' => 'aceite', 'href' => '/app/mecanico/reportes'],
                ['label' => 'Libro de novedades', 'kind' => 'novedades', 'href' => '/app/mecanico/reportes'],
            ],
        ];
    }

    private function conductor(User $user): array
    {
        $driverId = Driver::where('user_id', $user->id)->value('id');
        $trips = RouteSheet::where('driver_id', $driverId ?: 0);
        $pending = (clone $trips)->where('driver_response', 'pendiente')->count();
        $enRoute = (clone $trips)->where('trip_status', 'en_ruta')->count();
        $programmed = (clone $trips)->where('trip_status', 'programado')->count();
        $issues = IssueLog::where('reporting_driver_id', $user->id)->count();

        return [
            'title' => 'Mis viajes',
            'subtitle' => 'Aceptar asignaciones, registrar paradas y novedades.',
            'period' => now()->format('Y-m'),
            'kpis' => [
                $this->kpi('pendientes', 'Por aceptar', $pending, $pending ? 'warn' : 'ok', '/app/conductor/viajes'),
                $this->kpi('programados', 'Programados', $programmed, 'info', '/app/conductor/viajes'),
                $this->kpi('ruta', 'En ruta', $enRoute, 'info', '/app/conductor/mapa'),
                $this->kpi('novedades', 'Novedades reportadas', $issues, 'info', '/app/conductor/novedades'),
            ],
            'queue' => array_values(array_filter([
                $pending ? $this->queueItem('accept', 'Aceptar o rechazar viajes', $pending, '/app/conductor/viajes', 'Secretaría espera su respuesta.') : null,
                $this->queueItem('pdf', 'Hoja de ruta PDF', 1, '/app/conductor/documentos', 'Respaldo para el viaje.'),
            ])),
            'charts' => [
                $this->chart('viajes', 'Mis hojas de ruta', [
                    'pendiente' => $pending,
                    'programado' => $programmed,
                    'en_ruta' => $enRoute,
                ]),
            ],
            'recent' => (clone $trips)->with('request')->orderByDesc('id')->limit(6)->get()->map(fn ($s) => [
                'id' => $s->id,
                'label' => 'Hoja #'.$s->id.' · '.($s->request?->destination ?? ''),
                'meta' => $s->driver_response.' · '.$s->trip_status,
                'href' => '/app/conductor/viajes',
            ]),
            'exports' => [
                ['label' => 'Documentos del viaje', 'kind' => 'documentos', 'href' => '/app/conductor/documentos'],
            ],
        ];
    }

    private function estudiante(User $user): array
    {
        $pending = PassengerManifest::where('user_id', $user->id)->where('invitation_status', 'invitado')->count();
        $accepted = PassengerManifest::where('user_id', $user->id)->where('invitation_status', 'aceptado')->count();

        return [
            'title' => 'Mis invitaciones',
            'subtitle' => 'Confirme participación y consulte el PDF del viaje.',
            'period' => now()->format('Y-m'),
            'kpis' => [
                $this->kpi('invitaciones', 'Por confirmar', $pending, $pending ? 'warn' : 'ok', '/app/estudiante/invitaciones'),
                $this->kpi('aceptadas', 'Aceptadas', $accepted, 'ok', '/app/estudiante/flujo'),
            ],
            'queue' => array_values(array_filter([
                $pending ? $this->queueItem('inv', 'Responder invitaciones', $pending, '/app/estudiante/invitaciones', 'El docente espera su confirmación.') : null,
                $this->queueItem('pdf', 'Ver documentos del viaje', 1, '/app/estudiante/documentos', 'Orden y hoja de ruta en PDF.'),
            ])),
            'charts' => [
                $this->chart('invitaciones', 'Participación', [
                    'pendiente' => $pending,
                    'aceptadas' => $accepted,
                ]),
            ],
            'recent' => PassengerManifest::with('request')
                ->where('user_id', $user->id)
                ->orderByDesc('id')
                ->limit(8)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'label' => '#'.$p->request_id.' · '.($p->request?->destination ?? 'Viaje'),
                    'meta' => $p->invitation_status,
                    'href' => '/app/estudiante/invitaciones',
                ]),
            'exports' => [
                ['label' => 'Documentos PDF', 'kind' => 'documentos', 'href' => '/app/estudiante/documentos'],
            ],
        ];
    }

    private function emptyDashboard(string $title): array
    {
        return [
            'title' => $title,
            'subtitle' => '',
            'period' => now()->format('Y-m'),
            'kpis' => [],
            'queue' => [],
            'charts' => [],
            'recent' => [],
            'exports' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function kpi(string $key, string $label, int|float $value, string $tone, string $href, ?string $hint = null): array
    {
        return compact('key', 'label', 'value', 'tone', 'href', 'hint');
    }

    /**
     * @return array<string, mixed>
     */
    private function queueItem(string $id, string $title, int $count, string $href, string $description): array
    {
        return compact('id', 'title', 'count', 'href', 'description');
    }

    /**
     * @param  array<string, mixed>  $items
     * @return array<string, mixed>
     */
    private function chart(string $id, string $title, array $items): array
    {
        $mapped = [];
        foreach ($items as $label => $value) {
            if ($label === '' || $label === null) {
                $label = 'Sin dato';
            }
            $mapped[] = ['label' => (string) $label, 'value' => (int) $value];
        }

        return ['id' => $id, 'title' => $title, 'items' => $mapped];
    }
}
