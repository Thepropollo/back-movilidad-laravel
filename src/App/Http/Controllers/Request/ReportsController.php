<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Support\SimplePdf;
use Domain\Vehicles\Models\Vehicle;
use Domain\Workshop\Models\IssueLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function solicitudes(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte', 'vicerrector', 'rector', 'responsable_facultad', 'docente', 'solicitante'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $query = MobilizationRequest::with(['requester', 'secretariaApprover', 'rectorateApprover', 'routeSheet.vehicle', 'routeSheet.driver.user']);

        if ($user->hasRole(['docente', 'solicitante']) && ! $user->hasRole(['secretaria', 'responsable_facultad', 'vicerrector'])) {
            $query->where('requester_id', $user->id);
        } elseif ($user->hasRole(['responsable_facultad']) && ! $user->hasRole(['secretaria'])) {
            $query->whereHas('requester', fn ($q) => $q->where('faculty_institution', $user->faculty_institution));
        } elseif ($user->hasRole(['vicerrector', 'rector']) && ! $user->hasRole(['secretaria'])) {
            $query->where('mobilization_type', 'externa');
        }

        $this->applyDateFilters($query, $request, 'departure_date');
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('type')) {
            $query->where('mobilization_type', $request->query('type'));
        }

        $rows = $query->orderByDesc('id')->get()->map(function (MobilizationRequest $r) {
            return [
                'id' => $r->id,
                'tipo' => $r->mobilization_type,
                'origen' => $r->origin,
                'destino' => $r->destination,
                'salida' => optional($r->departure_date)?->toDateString(),
                'retorno' => optional($r->return_date)?->toDateString(),
                'estado' => $r->status,
                'solicitante' => trim(($r->requester?->first_name.' '.$r->requester?->last_name) ?: ''),
                'facultad' => $r->requester?->faculty_institution,
                'costo_proyectado' => $r->projected_cost,
                'vehiculo' => $r->routeSheet?->vehicle?->plate,
                'conductor' => trim(($r->routeSheet?->driver?->user?->first_name.' '.$r->routeSheet?->driver?->user?->last_name) ?: ''),
                'estado_viaje' => $r->routeSheet?->trip_status,
            ];
        });

        return $this->respond(
            $request,
            'reporte_solicitudes',
            'Registro de solicitudes de movilización',
            'PST-01-RPT-SOL',
            [
                'id' => 'No.',
                'tipo' => 'Tipo',
                'origen' => 'Origen',
                'destino' => 'Destino',
                'salida' => 'Salida',
                'retorno' => 'Retorno',
                'estado' => 'Estado',
                'solicitante' => 'Solicitante',
                'facultad' => 'Facultad',
                'costo_proyectado' => 'Costo',
                'vehiculo' => 'Placa',
                'conductor' => 'Conductor',
                'estado_viaje' => 'Viaje',
            ],
            $rows->all(),
            [
                'por_estado' => $rows->groupBy('estado')->map->count(),
                'por_tipo' => $rows->groupBy('tipo')->map->count(),
                'costo_total' => $rows->sum('costo_proyectado'),
            ],
            true
        );
    }

    public function viajes(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte', 'vicerrector', 'rector'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $query = RouteSheet::with(['request.requester', 'vehicle', 'driver.user', 'stops', 'fuelOrders']);
        $this->applyRouteDateFilters($query, $request);

        $rows = $query->orderByDesc('id')->get()->map(function (RouteSheet $s) {
            return [
                'hoja_ruta_id' => $s->id,
                'destino' => $s->request?->destination,
                'salida' => optional($s->request?->departure_date)?->toDateString(),
                'conductor' => trim(($s->driver?->user?->first_name.' '.$s->driver?->user?->last_name) ?: ''),
                'vehiculo' => $s->vehicle?->plate,
                'respuesta_conductor' => $s->driver_response,
                'estado_viaje' => $s->trip_status,
                'paradas' => $s->stops->count(),
                'km_inicial' => $s->initial_mileage,
                'km_final' => $s->final_mileage,
                'combustible' => $s->fuelOrders->sum(fn ($o) => $o->actual_dispatched_gallons ?? $o->authorized_gallons ?? 0),
            ];
        });

        return $this->respond(
            $request,
            'reporte_viajes',
            'Registro de viajes y hojas de ruta',
            'PST-01-F-006-RPT',
            [
                'hoja_ruta_id' => 'Hoja',
                'destino' => 'Destino',
                'salida' => 'Salida',
                'conductor' => 'Conductor',
                'vehiculo' => 'Placa',
                'respuesta_conductor' => 'Respuesta',
                'estado_viaje' => 'Estado',
                'paradas' => 'Paradas',
                'km_inicial' => 'Km ini',
                'km_final' => 'Km fin',
                'combustible' => 'Comb.',
            ],
            $rows->all(),
            [],
            true
        );
    }

    public function flota(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $rows = Vehicle::all()->map(function (Vehicle $v) {
            return [
                'placa' => $v->plate,
                'unidad' => trim($v->brand.' '.$v->model),
                'matricula' => $v->registration_number,
                'km_actual' => $v->current_mileage,
                'proximo_aceite' => $v->next_oil_change_mileage,
                'estado' => $v->operational_status,
                'mantenimiento_vencido' => $v->current_mileage >= $v->next_oil_change_mileage ? 'si' : 'no',
            ];
        });

        return $this->respond(
            $request,
            'reporte_flota',
            'Estado operativo de la flota',
            'FLOTA-RPT',
            [
                'placa' => 'Placa',
                'unidad' => 'Unidad',
                'matricula' => 'Matrícula',
                'km_actual' => 'Km actual',
                'proximo_aceite' => 'Próximo aceite',
                'estado' => 'Estado',
                'mantenimiento_vencido' => 'Aceite vencido',
            ],
            $rows->all(),
            [
                'por_estado' => $rows->groupBy('estado')->map->count(),
                'mantenimiento_vencido' => $rows->where('mantenimiento_vencido', 'si')->count(),
            ]
        );
    }

    public function mensual(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte', 'vicerrector', 'rector'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $query = RouteSheet::with(['request.requester', 'request.secretariaApprover', 'vehicle', 'driver.user', 'fuelOrders']);
        $this->applyRouteDateFilters($query, $request);

        $rows = $query->orderByDesc('id')->get()->map(function (RouteSheet $s) {
            $km = ($s->final_mileage && $s->initial_mileage)
                ? $s->final_mileage - $s->initial_mileage
                : null;

            return [
                'placa' => $s->vehicle?->plate,
                'salvo_conducto' => $s->id,
                'fecha' => optional($s->request?->departure_date)?->toDateString(),
                'hora' => $s->request?->departure_time,
                'km_inicio' => $s->initial_mileage,
                'km_fin' => $s->final_mileage,
                'km_recorrido' => $km,
                'detalle' => $s->request?->destination,
                'autorizado_por' => trim(($s->request?->secretariaApprover?->first_name.' '.$s->request?->secretariaApprover?->last_name) ?: ''),
                'conductor' => trim(($s->driver?->user?->first_name.' '.$s->driver?->user?->last_name) ?: ''),
                'combustible' => $s->fuelOrders->sum(fn ($o) => $o->actual_dispatched_gallons ?? $o->authorized_gallons ?? 0),
            ];
        });

        return $this->respond(
            $request,
            'informe_mensual_viajes',
            'Informe mensual de viajes',
            'PAM-04-F-007',
            [
                'placa' => 'Placa',
                'salvo_conducto' => 'Salvo conducto',
                'fecha' => 'Fecha',
                'hora' => 'Hora',
                'km_inicio' => 'Km inicio',
                'km_fin' => 'Km fin',
                'km_recorrido' => 'Km recorrido',
                'detalle' => 'Detalle',
                'autorizado_por' => 'Autorizado por',
                'conductor' => 'Conductor',
                'combustible' => 'Combustible',
            ],
            $rows->all(),
            ['km_total' => $rows->sum('km_recorrido'), 'combustible_total' => $rows->sum('combustible')],
            true
        );
    }

    public function aceite(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte', 'mecanico'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $rows = Vehicle::query()->orderBy('plate')->get()->map(function (Vehicle $v) {
            return [
                'placa' => $v->plate,
                'unidad' => trim($v->brand.' '.$v->model),
                'matricula' => $v->registration_number,
                'km_actual' => $v->current_mileage,
                'proximo_cambio' => $v->next_oil_change_mileage,
                'restante' => $v->next_oil_change_mileage - $v->current_mileage,
                'estado' => $v->current_mileage >= $v->next_oil_change_mileage ? 'vencido' : 'ok',
            ];
        });

        return $this->respond(
            $request,
            'control_cambio_aceite',
            'Control de cambio de aceite',
            'CTRL-ACEITE',
            [
                'placa' => 'Placa',
                'unidad' => 'Unidad',
                'matricula' => 'Matrícula',
                'km_actual' => 'Km actual',
                'proximo_cambio' => 'Próximo cambio',
                'restante' => 'Restante',
                'estado' => 'Estado',
            ],
            $rows->all(),
            ['vencidos' => $rows->where('estado', 'vencido')->count()]
        );
    }

    public function novedades(Request $request)
    {
        $user = $request->user();
        if (! $user->hasRole(['secretaria', 'jefe_transporte', 'mecanico', 'conductor', 'chofer'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $query = IssueLog::with(['vehicle', 'reportingDriver'])->orderByDesc('id');
        if ($user->hasRole(['conductor', 'chofer']) && ! $user->hasRole(['secretaria', 'mecanico'])) {
            $query->where('reporting_driver_id', $user->id);
        }

        $rows = $query->get()->map(function ($issue) {
            return [
                'id' => $issue->id,
                'fecha' => optional($issue->breakdown_date)?->toDateString(),
                'placa' => $issue->vehicle?->plate,
                'unidad' => trim(($issue->vehicle?->brand.' '.$issue->vehicle?->model) ?: ''),
                'estado' => $issue->status,
                'descripcion' => $issue->description,
                'reporta' => trim(($issue->reportingDriver?->first_name.' '.$issue->reportingDriver?->last_name) ?: ''),
            ];
        });

        return $this->respond(
            $request,
            'libro_novedades',
            'Libro de novedades del taller',
            'LIBRO-NOVEDADES',
            [
                'id' => 'No.',
                'fecha' => 'Fecha',
                'placa' => 'Placa',
                'unidad' => 'Unidad',
                'estado' => 'Estado',
                'descripcion' => 'Novedades',
                'reporta' => 'Reporta',
            ],
            $rows->all()
        );
    }

    /**
     * @param  array<string, string>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $summary
     */
    private function respond(
        Request $request,
        string $filename,
        string $title,
        string $code,
        array $columns,
        array $rows,
        array $summary = [],
        bool $landscape = false
    ) {
        $format = $request->query('format');
        if ($format === 'csv') {
            return $this->csv($filename.'.csv', array_keys($columns), $rows);
        }
        if ($format === 'pdf') {
            return $this->pdf($filename.'.pdf', $title, $code, $columns, $rows, $request, $landscape);
        }

        return response()->json([
            'generated_at' => now()->toIso8601String(),
            'code' => $code,
            'title' => $title,
            'total' => count($rows),
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    /**
     * @param  array<string, string>  $columns
     * @param  list<array<string, mixed>>  $rows
     */
    private function pdf(
        string $filename,
        string $title,
        string $code,
        array $columns,
        array $rows,
        Request $request,
        bool $landscape
    ): StreamedResponse {
        $pdf = new SimplePdf($title, $code, 'Dirección de Transporte y Movilidad', $landscape);
        $pdf->fields([
            ['Periodo desde', $request->query('from') ?: 'Sin filtro'],
            ['Periodo hasta', $request->query('to') ?: 'Sin filtro'],
            ['Elaborado por', trim($request->user()->first_name.' '.$request->user()->last_name)],
            ['Registros', count($rows)],
        ], 4);
        $tableRows = [];
        $keys = array_keys($columns);
        foreach ($rows as $row) {
            $line = [];
            foreach ($keys as $key) {
                $line[] = (string) ($row[$key] ?? '');
            }
            $tableRows[] = $line;
        }
        $pdf->table(array_values($columns), $tableRows);
        $pdf->note('Respaldo físico institucional. Conservar el PDF impreso junto al expediente de movilidad.');
        $pdf->signatures('Elaborado por', 'Revisado por Secretaría', 'Aprobado por');
        $binary = $pdf->output();

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array<string, mixed>>  $rows
     */
    private function csv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                $line = [];
                foreach ($headers as $h) {
                    $line[] = $row[$h] ?? '';
                }
                fputcsv($out, $line, ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function applyDateFilters($query, Request $request, string $column): void
    {
        if ($request->filled('from')) {
            $query->whereDate($column, '>=', $request->query('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate($column, '<=', $request->query('to'));
        }
    }

    private function applyRouteDateFilters($query, Request $request): void
    {
        if ($request->filled('from')) {
            $query->whereHas('request', fn ($q) => $q->whereDate('departure_date', '>=', $request->query('from')));
        }
        if ($request->filled('to')) {
            $query->whereHas('request', fn ($q) => $q->whereDate('departure_date', '<=', $request->query('to')));
        }
    }
}
