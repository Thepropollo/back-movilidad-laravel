<?php

namespace Domain\Requests\Actions;

use Domain\Auth\Models\User;
use Domain\Requests\Models\DocumentSignature;
use Domain\Requests\Models\GeneratedDocument;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Support\RequestWorkflow;
use Domain\Requests\Support\SimplePdf;
use Domain\Vehicles\Models\Vehicle;
use Domain\Workshop\Models\IssueLog;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class GenerateInstitutionalDocumentAction
{
    /** @var array<string, string> */
    private const ACTIVITIES = [
        'visitas_academicas' => 'Visitas académicas',
        'clases_practicas' => 'Clases prácticas',
        'proyectos_vinculacion' => 'Proyectos de vinculación',
        'congresos_cursos' => 'Congresos y/o cursos',
        'reuniones_interinstitucionales' => 'Reuniones interinstitucionales',
        'reuniones_matriz' => 'Reuniones en matriz y/o extensión',
        'otros' => 'Otros',
    ];

    private ?int $signingDocumentId = null;

    /**
     * @param  array{from?: string, to?: string}  $filters
     */
    public function execute(
        string $type,
        User $actor,
        ?int $requestId = null,
        ?int $workOrderId = null,
        ?int $issueLogId = null,
        array $filters = []
    ): GeneratedDocument {
        if (! isset(GeneratedDocument::CATALOG[$type])) {
            throw ValidationException::withMessages(['type' => ['Tipo de documento no soportado.']]);
        }

        $this->signingDocumentId = null;
        $binary = $this->buildPdf($type, $requestId, $workOrderId, $issueLogId, $filters);
        $hash = hash('sha256', $binary);

        $filename = $type.'-'.now()->format('YmdHis').'.pdf';
        $path = 'documentos/'.$filename;
        Storage::disk('local')->put($path, $binary);

        $document = GeneratedDocument::create([
            'document_type' => $type,
            'source' => 'generated',
            'request_id' => $requestId,
            'work_order_id' => $workOrderId,
            'issue_log_id' => $issueLogId,
            'file_path' => $path,
            'original_filename' => $filename,
            'mime_type' => 'application/pdf',
            'file_hash' => $hash,
            'generated_by' => $actor->id,
        ]);

        if ($requestId) {
            $request = MobilizationRequest::find($requestId);
            if ($request) {
                RequestWorkflow::record(
                    $request,
                    $request->status,
                    'DOCUMENTO_GENERADO',
                    $actor->id,
                    GeneratedDocument::CATALOG[$type]['label'].' emitido como respaldo físico.'
                );
            }
        }

        return $document;
    }

    public function rebuild(GeneratedDocument $document): string
    {
        $this->signingDocumentId = $document->id;
        $binary = $this->buildPdf(
            $document->document_type,
            $document->request_id,
            $document->work_order_id,
            $document->issue_log_id,
            []
        );
        Storage::disk('local')->put($document->file_path, $binary);
        $document->update(['file_hash' => hash('sha256', $binary)]);

        return $binary;
    }

    /**
     * @param  array{from?: string, to?: string}  $filters
     */
    private function buildPdf(
        string $type,
        ?int $requestId,
        ?int $workOrderId,
        ?int $issueLogId,
        array $filters
    ): string {
        return match ($type) {
            'orden_movilizacion' => $this->orden($this->requestOrFail($requestId)),
            'hoja_ruta' => $this->hojaRuta($this->requestOrFail($requestId)),
            'acta_entrega' => $this->acta($this->requestOrFail($requestId)),
            'orden_taller' => $this->ordenTaller($this->workOrderOrFail($workOrderId)),
            'libro_novedades' => $this->novedad($this->issueOrFail($issueLogId)),
            'provision_lubricantes' => $this->provision($this->workOrderOrFail($workOrderId)),
            'informe_mensual' => $this->informeMensual($filters),
            'control_aceite' => $this->controlAceite(),
            default => throw ValidationException::withMessages(['type' => ['Tipo no implementado.']]),
        };
    }

    private function applySignatures(SimplePdf $pdf, string $type): void
    {
        $slots = [];
        foreach (GeneratedDocument::signatureSlots($type) as $key => $meta) {
            $slots[] = ['key' => $key, 'label' => $meta['label']];
        }
        $signed = [];
        if ($this->signingDocumentId) {
            $rows = DocumentSignature::with('user')->where('document_id', $this->signingDocumentId)->get();
            foreach ($rows as $row) {
                $signed[$row->slot] = [
                    'name' => trim($row->user?->first_name.' '.$row->user?->last_name),
                    'national_id' => $row->user?->national_id,
                    'signed_at' => optional($row->signed_at)?->format('d/m/Y H:i'),
                    'hash' => $row->key_fingerprint ?: $row->document_hash,
                ];
            }
        }
        $pdf->signatureStamps($slots, $signed);
    }

    private function orden(MobilizationRequest $request): string
    {
        $request->loadMissing([
            'requester',
            'secretariaApprover',
            'rectorateApprover',
            'routeSheet.vehicle',
            'routeSheet.driver.user',
            'routeSheet.driver.licenses',
            'passengers',
        ]);
        $code = $request->mobilization_type === 'externa' ? 'PST-01-F-09' : 'PST-01-F-003';
        $pdf = new SimplePdf(
            'Orden de movilización '.($request->mobilization_type === 'externa' ? 'externa' : 'interna'),
            $code,
            'PST-01 Procedimiento de Transporte Institucional'
        );
        $driverUser = $request->routeSheet?->driver?->user;
        $license = $request->routeSheet?->driver?->licenses?->first();
        $vehicle = $request->routeSheet?->vehicle;

        $pdf->fields([
            ['No. orden', str_pad((string) $request->id, 4, '0', STR_PAD_LEFT)],
            ['No. comunicación', $request->communication_number],
            ['Tipo', $request->mobilization_type === 'externa' ? 'Externa' : 'Interna'],
            ['Ciudad de origen', $request->origin],
            ['Vigencia desde', optional($request->departure_date)?->format('d/m/Y').' '.$request->departure_time],
            ['Vigencia hasta', optional($request->return_date)?->format('d/m/Y').' '.$request->return_time],
        ], 3);
        $pdf->fields([
            ['Motivo / comisión', $request->travel_reason],
        ], 1);
        $pdf->fields([
            ['No. ocupantes', $request->occupant_count ?: max(1, $request->passengers->count())],
            ['Servidores públicos', $request->public_servants_count],
            ['Carrera / programa', $request->academic_program],
            ['Destino', $request->destination],
            ['Km inicio', $request->routeSheet?->initial_mileage],
            ['Km fin', $request->routeSheet?->final_mileage],
        ], 3);

        $pdf->heading('Tipo de actividad');
        $selected = $request->activity_type;
        $checks = [];
        foreach (self::ACTIVITIES as $key => $label) {
            $checks[$label] = $selected === $key;
        }
        $pdf->checks($checks, 2);

        $pdf->heading('Conductor');
        $pdf->fields([
            ['Nombres y apellidos', trim(($driverUser?->first_name.' '.$driverUser?->last_name) ?: '')],
            ['Cargo', $driverUser?->job_title ?: 'Conductor institucional'],
            ['Cédula', $driverUser?->national_id],
            ['Licencia', $license?->license_type],
        ], 2);

        $pdf->heading('Vehículo');
        $pdf->fields([
            ['Placa', $vehicle?->plate],
            ['Marca / modelo', trim(($vehicle?->brand.' '.$vehicle?->model) ?: '')],
            ['Color', $vehicle?->color],
            ['Matrícula', $vehicle?->registration_number],
        ], 2);

        $pdf->heading('Solicitante y autorización');
        $pdf->fields([
            ['Solicitante', trim($request->requester?->first_name.' '.$request->requester?->last_name)],
            ['Unidad / facultad', $request->requester?->faculty_institution],
            ['Elaborado / Secretaría', trim(($request->secretariaApprover?->first_name.' '.$request->secretariaApprover?->last_name) ?: 'Pendiente')],
            ['Vicerrectorado', trim(($request->rectorateApprover?->first_name.' '.$request->rectorateApprover?->last_name) ?: 'No aplica / pendiente')],
            ['Estado del trámite', $request->status],
        ], 2);
        $pdf->note('Documento generado desde el sistema de movilidad. Imprimir para archivo físico y firmas manuscritas.');
        $this->applySignatures($pdf, 'orden_movilizacion');

        return $pdf->output();
    }

    private function hojaRuta(MobilizationRequest $request): string
    {
        $request->loadMissing([
            'requester',
            'passengers.user',
            'routeSheet.vehicle',
            'routeSheet.driver.user',
            'routeSheet.stops',
        ]);
        $sheet = $request->routeSheet;
        if (! $sheet) {
            throw ValidationException::withMessages(['request_id' => ['Aún no hay hoja de ruta asignada.']]);
        }
        $pdf = new SimplePdf('Hoja de ruta', 'PST-01-F-006', 'PST-01 Procedimiento de Transporte Institucional');
        $pdf->fields([
            ['Placa', $sheet->vehicle?->plate],
            ['Chofer', trim($sheet->driver?->user?->first_name.' '.$sheet->driver?->user?->last_name)],
            ['Destino', $request->destination],
            ['Salida', optional($request->departure_date)?->format('d/m/Y').' '.$request->departure_time],
            ['Retorno', optional($request->return_date)?->format('d/m/Y').' '.$request->return_time],
            ['Km inicio', $sheet->initial_mileage],
            ['Km llegada', $sheet->final_mileage],
            ['Comisionado/a', trim($request->requester?->first_name.' '.$request->requester?->last_name)],
        ], 2);
        $pdf->fields([
            ['Facultad', $request->requester?->faculty_institution],
            ['Carrera', $request->academic_program],
            ['N. alumnos', $request->passengers->where('invitation_status', 'aceptado')->count()],
            ['N. servidores', $request->public_servants_count],
        ], 2);
        $pdf->heading('Tipo de actividad');
        $checks = [];
        foreach (self::ACTIVITIES as $key => $label) {
            $checks[$label] = $request->activity_type === $key;
        }
        $pdf->checks($checks, 2);
        $pdf->paragraph('Descripción de la actividad: '.$request->travel_reason);
        $rows = $sheet->stops->map(fn ($stop) => [
            (string) $stop->sequence,
            (string) ($stop->location ?: $stop->visited_canton),
            (string) ($stop->arrival_time ?: ''),
            (string) ($stop->odometer_km ?: ''),
        ])->all();
        $pdf->heading('Lugares visitados');
        $pdf->table(['#', 'Lugar / cantón', 'Hora', 'Km'], $rows, [30, 280, 90, 80]);
        $this->applySignatures($pdf, 'hoja_ruta');

        return $pdf->output();
    }

    private function acta(MobilizationRequest $request): string
    {
        $request->loadMissing([
            'routeSheet.vehicle',
            'routeSheet.driver.user',
            'routeSheet.deliveryActs.checklistDetails.component',
            'routeSheet.deliveryActs.mechanicOrGuard',
        ]);
        $sheet = $request->routeSheet;
        if (! $sheet) {
            throw ValidationException::withMessages(['request_id' => ['No hay inspección asociada.']]);
        }
        $pdf = new SimplePdf('Acta de entrega-recepción', 'PST-01-F-004.1', 'PST-01 Procedimiento de Transporte Institucional');
        $pdf->fields([
            ['Placa', $sheet->vehicle?->plate],
            ['Marca / modelo / año', trim($sheet->vehicle?->brand.' '.$sheet->vehicle?->model.' '.$sheet->vehicle?->year)],
            ['Color', $sheet->vehicle?->color],
            ['Matrícula', $sheet->vehicle?->registration_number],
            ['Chofer / custodio', trim($sheet->driver?->user?->first_name.' '.$sheet->driver?->user?->last_name)],
            ['Hoja de ruta', $sheet->id],
        ], 2);
        foreach ($sheet->deliveryActs as $act) {
            $pdf->heading('Registro de '.strtoupper($act->registration_type));
            $pdf->fields([
                ['Combustible', $act->fuel_level],
                ['Kilometraje', $act->checkpoint_mileage],
                ['Registrado por', trim($act->mechanicOrGuard?->first_name.' '.$act->mechanicOrGuard?->last_name)],
            ], 3);
            $rows = $act->checklistDetails->map(fn ($row) => [
                (string) ($row->component?->component_name ?? $row->component_id),
                (string) $row->physical_condition,
            ])->all();
            $pdf->table(['Componente / accesorio', 'Estado'], $rows, [360, 160]);
        }
        $this->applySignatures($pdf, 'acta_entrega');

        return $pdf->output();
    }

    private function ordenTaller(WorkshopWorkOrder $order): string
    {
        $order->loadMissing(['vehicle', 'responsibleMechanic', 'supervisor', 'issueLog']);
        $pdf = new SimplePdf('Orden de revisión mecánica', 'PST-01-F-003-OT', 'PST-01 Procedimiento de Transporte Institucional');
        $pdf->fields([
            ['N. orden', $order->id],
            ['Tipo de mantenimiento', $order->maintenance_type],
            ['Fecha ingreso', optional($order->entry_date)?->format('d/m/Y H:i')],
            ['Fecha egreso', optional($order->exit_date)?->format('d/m/Y H:i')],
            ['Vehículo', trim($order->vehicle?->brand.' '.$order->vehicle?->model)],
            ['Placa', $order->vehicle?->plate],
            ['Mecánico responsable', trim($order->responsibleMechanic?->first_name.' '.$order->responsibleMechanic?->last_name)],
            ['Supervisor', trim($order->supervisor?->first_name.' '.$order->supervisor?->last_name)],
        ], 2);
        $pdf->heading('Detalle del trabajo');
        $pdf->paragraph($order->work_details ?: 'Sin detalle');
        if ($order->issueLog) {
            $pdf->paragraph('Novedad asociada: '.$order->issueLog->description);
        }
        $this->applySignatures($pdf, 'orden_taller');

        return $pdf->output();
    }

    private function novedad(IssueLog $issue): string
    {
        $issue->loadMissing(['vehicle', 'reportingDriver']);
        $pdf = new SimplePdf('Libro de novedades del taller', 'LIBRO-NOVEDADES', 'Control operativo de patio y taller');
        $pdf->fields([
            ['Fecha de avería', optional($issue->breakdown_date)?->format('d/m/Y')],
            ['Placa', $issue->vehicle?->plate],
            ['Marca / modelo', trim($issue->vehicle?->brand.' '.$issue->vehicle?->model)],
            ['Kilometraje', $issue->vehicle?->current_mileage],
            ['Estado', $issue->status],
            ['Reporta', trim($issue->reportingDriver?->first_name.' '.$issue->reportingDriver?->last_name)],
        ], 2);
        $pdf->heading('Novedades observadas');
        $pdf->paragraph($issue->description);
        $this->applySignatures($pdf, 'libro_novedades');

        return $pdf->output();
    }

    private function provision(WorkshopWorkOrder $order): string
    {
        $order->loadMissing(['vehicle', 'responsibleMechanic', 'supplyProvisions.supply']);
        $pdf = new SimplePdf('Provisión de lubricantes y filtros', 'PROVISION-LUBRICANTES', 'Control de insumos de taller');
        $pdf->fields([
            ['Fecha', optional($order->exit_date ?? $order->entry_date)?->format('d/m/Y')],
            ['N. oficio / OT', $order->id],
            ['Placa', $order->vehicle?->plate],
            ['Marca / modelo', trim($order->vehicle?->brand.' '.$order->vehicle?->model)],
            ['Kilometraje', $order->vehicle?->current_mileage],
            ['Mecánico', trim($order->responsibleMechanic?->first_name.' '.$order->responsibleMechanic?->last_name)],
        ], 2);
        $rows = $order->supplyProvisions->map(fn ($row) => [
            (string) ($row->supply?->supply_name ?? $row->supply_id),
            (string) $row->quantity_used,
            (string) ($row->supply?->measurement_unit ?? ''),
        ])->all();
        $pdf->table(['Insumo', 'Cantidad', 'Unidad'], $rows === [] ? [['Sin insumos descontados', '0', '']] : $rows, [320, 100, 100]);
        $this->applySignatures($pdf, 'provision_lubricantes');

        return $pdf->output();
    }

    /**
     * @param  array{from?: string, to?: string}  $filters
     */
    private function informeMensual(array $filters = []): string
    {
        $query = RouteSheet::with(['request.secretariaApprover', 'vehicle', 'driver.user', 'fuelOrders']);
        if (! empty($filters['from'])) {
            $query->whereHas('request', fn ($q) => $q->whereDate('departure_date', '>=', $filters['from']));
        }
        if (! empty($filters['to'])) {
            $query->whereHas('request', fn ($q) => $q->whereDate('departure_date', '<=', $filters['to']));
        }
        $sheets = $query->orderByDesc('id')->limit(80)->get();
        $rows = $sheets->map(fn ($sheet) => [
            (string) ($sheet->vehicle?->plate ?? ''),
            (string) $sheet->id,
            (string) optional($sheet->request?->departure_date)?->format('d/m/Y'),
            (string) ($sheet->request?->departure_time ?? ''),
            (string) ($sheet->initial_mileage ?? ''),
            (string) ($sheet->final_mileage ?? ''),
            (string) (($sheet->final_mileage && $sheet->initial_mileage) ? $sheet->final_mileage - $sheet->initial_mileage : ''),
            (string) ($sheet->request?->destination ?? ''),
            trim(($sheet->request?->secretariaApprover?->first_name.' '.$sheet->request?->secretariaApprover?->last_name) ?: ''),
            trim(($sheet->driver?->user?->first_name.' '.$sheet->driver?->user?->last_name) ?: ''),
            (string) ($sheet->fuelOrders->sum('actual_dispatched_gallons') ?: $sheet->fuelOrders->sum('authorized_gallons')),
        ])->all();

        $pdf = new SimplePdf(
            'Informe mensual de viajes',
            'PAM-04-F-007',
            'PAM-04 Informe de gestión de movilidad',
            true
        );
        $pdf->fields([
            ['Periodo desde', $filters['from'] ?? now()->startOfMonth()->toDateString()],
            ['Periodo hasta', $filters['to'] ?? now()->toDateString()],
            ['Elaborado', now()->format('d/m/Y H:i')],
            ['Registros', count($rows)],
        ], 4);
        $pdf->table(
            ['Placa', 'Salvo', 'Fecha', 'Hora', 'Km ini', 'Km fin', 'Recorrido', 'Detalle', 'Autorizado por', 'Conductor', 'Comb.'],
            $rows,
            [55, 40, 58, 38, 42, 42, 50, 110, 90, 90, 40]
        );
        $this->applySignatures($pdf, 'informe_mensual');

        return $pdf->output();
    }

    private function controlAceite(): string
    {
        $rows = Vehicle::query()->orderBy('plate')->get()->map(fn (Vehicle $v) => [
            $v->plate,
            trim($v->brand.' '.$v->model),
            (string) $v->registration_number,
            (string) $v->current_mileage,
            (string) $v->next_oil_change_mileage,
            (string) ($v->next_oil_change_mileage - $v->current_mileage),
            $v->current_mileage >= $v->next_oil_change_mileage ? 'VENCIDO' : 'OK',
        ])->all();
        $pdf = new SimplePdf('Control de cambio de aceite', 'CTRL-ACEITE', 'Mantenimiento preventivo de flota');
        $pdf->table(
            ['Placa', 'Unidad', 'Matrícula', 'Km actual', 'Próximo cambio', 'Restante', 'Estado'],
            $rows,
            [70, 140, 80, 70, 90, 70, 60]
        );
        $this->applySignatures($pdf, 'control_aceite');

        return $pdf->output();
    }

    private function requestOrFail(?int $id): MobilizationRequest
    {
        if (! $id) {
            throw ValidationException::withMessages(['request_id' => ['La solicitud es requerida.']]);
        }

        return MobilizationRequest::findOrFail($id);
    }

    private function workOrderOrFail(?int $id): WorkshopWorkOrder
    {
        if (! $id) {
            throw ValidationException::withMessages(['work_order_id' => ['La orden de taller es requerida.']]);
        }

        return WorkshopWorkOrder::findOrFail($id);
    }

    private function issueOrFail(?int $id): IssueLog
    {
        if (! $id) {
            throw ValidationException::withMessages(['issue_log_id' => ['La novedad es requerida.']]);
        }

        return IssueLog::findOrFail($id);
    }
}
