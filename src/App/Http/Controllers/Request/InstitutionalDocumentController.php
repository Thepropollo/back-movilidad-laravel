<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\DeliveryReceptionAct;
use Domain\Requests\Actions\GenerateInstitutionalDocumentAction;
use Domain\Requests\Actions\SignInstitutionalDocumentAction;
use Domain\Requests\Models\GeneratedDocument;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Support\RequestWorkflow;
use Domain\Workshop\Models\IssueLog;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InstitutionalDocumentController extends Controller
{
    public function __construct(
        protected GenerateInstitutionalDocumentAction $generateAction,
        protected SignInstitutionalDocumentAction $signAction
    ) {}

    public function catalog()
    {
        return response()->json(GeneratedDocument::CATALOG);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = GeneratedDocument::with(['author', 'request', 'signatures.user'])
            ->orderByDesc('id');

        if ($request->filled('request_id')) {
            $query->where('request_id', $request->integer('request_id'));
        }
        if ($request->filled('type')) {
            $query->where('document_type', $request->query('type'));
        }

        if (! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            $query->where(function ($q) use ($user) {
                $q->where('generated_by', $user->id);
                if ($user->hasRole(['docente', 'solicitante'])) {
                    $q->orWhereHas('request', fn ($r) => $r->where('requester_id', $user->id));
                }
                if ($user->hasRole(['responsable_facultad'])) {
                    $q->orWhereHas('request.requester', fn ($r) => $r->where('faculty_institution', $user->faculty_institution));
                }
                if ($user->hasRole(['vicerrector', 'rector'])) {
                    $q->orWhereHas('request', fn ($r) => $r->where('mobilization_type', 'externa'));
                }
                if ($user->hasRole(['conductor', 'chofer'])) {
                    $q->orWhereHas('request.routeSheet.driver', fn ($r) => $r->where('user_id', $user->id));
                }
                if ($user->hasRole(['estudiante', 'pasajero'])) {
                    $q->orWhereHas('request.passengers', fn ($r) => $r->where('user_id', $user->id));
                }
                if ($user->hasRole(['mecanico'])) {
                    $q->orWhereIn('work_order_id', WorkshopWorkOrder::query()
                        ->select('id')
                        ->where('responsible_mechanic_id', $user->id));
                    $q->orWhereIn('issue_log_id', WorkshopWorkOrder::query()
                        ->select('issue_log_id')
                        ->where('responsible_mechanic_id', $user->id)
                        ->whereNotNull('issue_log_id'));
                    $q->orWhere(function ($documentQuery) use ($user) {
                        $documentQuery->where('document_type', 'acta_entrega')
                            ->whereIn('request_id', DeliveryReceptionAct::query()
                                ->join('route_sheets', 'route_sheets.id', '=', 'delivery_reception_acts.route_sheet_id')
                                ->select('route_sheets.request_id')
                                ->where('delivery_reception_acts.mechanic_or_guard_id', $user->id));
                    });
                }
            });
        }

        return response()->json(
            $query->limit(200)->get()->map(fn (GeneratedDocument $doc) => $this->present($doc, $user))->values()
        );
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|in:'.implode(',', array_keys(GeneratedDocument::CATALOG)),
            'request_id' => 'nullable|integer|exists:mobilization_requests,id',
            'work_order_id' => 'nullable|integer|exists:workshop_work_orders,id',
            'issue_log_id' => 'nullable|integer|exists:issue_logs,id',
        ]);

        $user = $request->user();
        $this->assertCanGenerate($user, $data['type'], $data['request_id'] ?? null, $data['work_order_id'] ?? null, $data['issue_log_id'] ?? null);

        $document = $this->generateAction->execute(
            type: $data['type'],
            actor: $user,
            requestId: isset($data['request_id']) ? (int) $data['request_id'] : null,
            workOrderId: isset($data['work_order_id']) ? (int) $data['work_order_id'] : null,
            issueLogId: isset($data['issue_log_id']) ? (int) $data['issue_log_id'] : null,
            filters: [
                'from' => $request->input('from'),
                'to' => $request->input('to'),
            ],
        );

        return response()->json([
            'message' => 'Documento generado.',
            'document' => $this->present($document->fresh(['author', 'request', 'signatures.user']), $user),
        ], 201);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|string|in:'.implode(',', array_keys(GeneratedDocument::CATALOG)),
            'file' => 'required|file|mimes:pdf,jpg,jpeg,png|mimetypes:application/pdf,image/jpeg,image/png|max:10240',
            'request_id' => 'nullable|integer|exists:mobilization_requests,id',
            'work_order_id' => 'nullable|integer|exists:workshop_work_orders,id',
            'issue_log_id' => 'nullable|integer|exists:issue_logs,id',
        ]);

        $user = $request->user();
        $this->assertCanGenerate($user, $data['type'], $data['request_id'] ?? null, $data['work_order_id'] ?? null, $data['issue_log_id'] ?? null);

        $this->assertAttachmentLimit($data);

        $file = $request->file('file');
        $path = $file->store('documentos', 'local');
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_BASENAME);
        $originalFilename = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalFilename) ?: 'documento';
        $originalFilename = mb_substr($originalFilename, 0, 180);

        $document = GeneratedDocument::create([
            'document_type' => $data['type'],
            'source' => 'uploaded',
            'request_id' => $data['request_id'] ?? null,
            'work_order_id' => $data['work_order_id'] ?? null,
            'issue_log_id' => $data['issue_log_id'] ?? null,
            'file_path' => $path,
            'original_filename' => $originalFilename,
            'mime_type' => $file->getMimeType() ?: 'application/pdf',
            'file_hash' => hash('sha256', $file->get()),
            'generated_by' => $user->id,
        ]);

        if (! empty($data['request_id'])) {
            $mobilization = MobilizationRequest::find($data['request_id']);
            if ($mobilization) {
                RequestWorkflow::record(
                    $mobilization,
                    $mobilization->status,
                    'DOCUMENTO_ADJUNTO',
                    $user->id,
                    'Se archivó '.$document->label().' (escaneado / firmado).'
                );
            }
        }

        return response()->json([
            'message' => 'Documento archivado. La información del sistema y el PDF quedan vinculados.',
            'document' => $this->present($document->fresh(['author', 'request', 'signatures.user']), $user),
        ], 201);
    }

    public function sign(Request $request, int $id)
    {
        $data = $request->validate([
            'slot' => 'required|string|max:40',
            'password' => 'required|string',
            'declaration' => 'accepted',
            'signature_image' => 'nullable|string|max:400000',
        ]);

        $document = GeneratedDocument::findOrFail($id);
        $user = $request->user();
        $this->assertCanDownload($user, $document);

        $signature = $this->signAction->execute(
            document: $document,
            actor: $user,
            slot: $data['slot'],
            password: $data['password'],
            ipAddress: $request->ip(),
            signatureImage: $data['signature_image'] ?? null
        );

        return response()->json([
            'message' => 'Documento firmado digitalmente.',
            'signature' => [
                'slot' => $signature->slot,
                'label' => $signature->slot_label,
                'signed_at' => optional($signature->signed_at)?->toIso8601String(),
                'fingerprint' => $signature->key_fingerprint,
            ],
            'document' => $this->present($document->fresh(['author', 'request', 'signatures.user']), $user),
        ]);
    }

    public function verify(Request $request, int $id)
    {
        $document = GeneratedDocument::with('signatures.user')->findOrFail($id);
        $this->assertCanDownload($request->user(), $document);

        $service = app(\Domain\Requests\Support\DigitalSignatureService::class);
        $currentFileHash = $this->hashStoredFile($document->file_path);
        $fileIntegrityValid = $document->file_hash && $currentFileHash
            ? hash_equals($document->file_hash, $currentFileHash)
            : false;

        $results = $document->signatures->map(function ($row) use ($service) {
            $valid = $row->signed_payload && $row->crypto_signature
                ? $service->verify($row->signed_payload, $row->crypto_signature)
                : false;

            return [
                'slot' => $row->slot,
                'label' => $row->slot_label,
                'signer' => trim(($row->user?->first_name.' '.$row->user?->last_name) ?: ''),
                'signed_at' => optional($row->signed_at)?->toIso8601String(),
                'fingerprint' => $row->key_fingerprint,
                'valid' => $valid,
            ];
        })->values();

        return response()->json([
            'document_id' => $document->id,
            'valid' => $fileIntegrityValid && $results->isNotEmpty() && $results->every(fn ($row) => $row['valid'] === true),
            'file_integrity_valid' => $fileIntegrityValid,
            'signatures' => $results,
        ]);
    }

    public function download(Request $request, int $id): Response
    {
        $document = GeneratedDocument::findOrFail($id);
        $this->assertCanDownload($request->user(), $document);

        if (! Storage::disk('local')->exists($document->file_path)) {
            abort(404, 'Archivo no encontrado.');
        }

        $filename = str_replace(['"', "\r", "\n"], '', $document->original_filename);

        return Storage::disk('local')->response(
            $document->file_path,
            $filename,
            [
                'Content-Type' => $document->mime_type ?: 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$filename.'"',
            ]
        );
    }

    private function assertCanGenerate($user, string $type, ?int $requestId, ?int $workOrderId, ?int $issueLogId): void
    {
        if ($user->hasRole(['secretaria', 'jefe_transporte'])) {
            return;
        }

        $allowed = match ($type) {
            'orden_movilizacion', 'hoja_ruta' => $user->hasRole(['docente', 'solicitante', 'responsable_facultad', 'vicerrector', 'rector', 'conductor', 'chofer', 'estudiante', 'pasajero']),
            'acta_entrega', 'orden_taller', 'provision_lubricantes', 'control_aceite' => $user->hasRole(['mecanico']),
            'libro_novedades' => $user->hasRole(['mecanico', 'conductor', 'chofer']),
            'informe_mensual' => $user->hasRole(['vicerrector', 'rector']),
            default => false,
        };

        if (! $allowed) {
            throw ValidationException::withMessages(['type' => ['No puede emitir este formato.']]);
        }

        if ($requestId) {
            $requestModel = MobilizationRequest::with(['requester', 'routeSheet.driver', 'passengers'])->findOrFail($requestId);
            $mechanicAct = $type === 'acta_entrega'
                && $user->hasRole('mecanico')
                && DeliveryReceptionAct::query()
                    ->where('mechanic_or_guard_id', $user->id)
                    ->whereHas('routeSheet', fn ($q) => $q->where('request_id', $requestId))
                    ->exists();
            $owns = $requestModel->requester_id === $user->id
                || ($user->hasRole(['responsable_facultad']) && optional($requestModel->requester)->faculty_institution === $user->faculty_institution)
                || ($user->hasRole(['vicerrector', 'rector']) && $requestModel->mobilization_type === 'externa')
                || optional($requestModel->routeSheet?->driver)->user_id === $user->id
                || $requestModel->passengers->contains('user_id', $user->id)
                || $mechanicAct;
            if (! $owns) {
                throw ValidationException::withMessages(['request_id' => ['No tiene acceso a esta solicitud.']]);
            }
        }

        if ($workOrderId && $user->hasRole(['mecanico']) && ! $user->hasRole(['secretaria'])) {
            $workOrder = WorkshopWorkOrder::findOrFail($workOrderId);
            if ((int) $workOrder->responsible_mechanic_id !== (int) $user->id) {
                throw ValidationException::withMessages(['work_order_id' => ['La orden no está asignada a este mecánico.']]);
            }
        }

        if ($issueLogId && $user->hasRole(['conductor', 'chofer']) && ! $user->hasRole(['secretaria', 'mecanico'])) {
            $issue = IssueLog::findOrFail($issueLogId);
            if ((int) $issue->reporting_driver_id !== (int) $user->id) {
                throw ValidationException::withMessages(['issue_log_id' => ['Solo puede emitir su propia novedad.']]);
            }
        }

        if ($issueLogId && $user->hasRole(['mecanico']) && ! $user->hasRole(['secretaria'])) {
            $assigned = WorkshopWorkOrder::query()
                ->where('issue_log_id', $issueLogId)
                ->where('responsible_mechanic_id', $user->id)
                ->exists();
            if (! $assigned) {
                throw ValidationException::withMessages(['issue_log_id' => ['La novedad no está asignada a este mecánico.']]);
            }
        }
    }

    private function assertCanDownload($user, GeneratedDocument $document): void
    {
        try {
            $this->assertCanGenerate(
                $user,
                $document->document_type,
                $document->request_id,
                $document->work_order_id,
                $document->issue_log_id
            );
        } catch (ValidationException $e) {
            if ((int) $document->generated_by !== (int) $user->id) {
                abort(403, 'No puede descargar este documento.');
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(GeneratedDocument $document, $user): array
    {
        $document->loadMissing(['author', 'signatures.user']);
        $signed = $document->signatures->keyBy('slot');
        $service = app(\Domain\Requests\Support\DigitalSignatureService::class);
        $signatureSlots = [];
        foreach (GeneratedDocument::signatureSlots($document->document_type) as $key => $meta) {
            $row = $signed->get($key);
            $signatureSlots[] = [
                'slot' => $key,
                'label' => $meta['label'],
                'signed' => (bool) $row,
                'signer' => $row ? trim(($row->user?->first_name.' '.$row->user?->last_name) ?: '') : null,
                'signed_at' => optional($row?->signed_at)?->toIso8601String(),
                'can_sign' => ! $row && $user->hasRole($meta['roles']),
                'valid' => $row && $row->signed_payload && $row->crypto_signature
                    ? $service->verify($row->signed_payload, $row->crypto_signature)
                    : null,
                'fingerprint' => $row?->key_fingerprint,
            ];
        }

        return [
            'id' => $document->id,
            'document_type' => $document->document_type,
            'label' => $document->label(),
            'code' => $document->code(),
            'source' => $document->source,
            'request_id' => $document->request_id,
            'work_order_id' => $document->work_order_id,
            'issue_log_id' => $document->issue_log_id,
            'original_filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'file_hash' => $document->file_hash,
            'generated_by' => $document->generated_by,
            'created_at' => optional($document->created_at)?->toIso8601String(),
            'author' => $document->author ? [
                'id' => $document->author->id,
                'first_name' => $document->author->first_name,
                'last_name' => $document->author->last_name,
            ] : null,
            'signature_slots' => $signatureSlots,
        ];
    }

    /** @param array<string, mixed> $data */
    private function assertAttachmentLimit(array $data): void
    {
        $limit = 10;
        $checks = [
            'request_id' => 'request_id',
            'work_order_id' => 'work_order_id',
            'issue_log_id' => 'issue_log_id',
        ];

        foreach ($checks as $input => $column) {
            if (empty($data[$input])) {
                continue;
            }

            if (GeneratedDocument::query()->where($column, $data[$input])->count() >= $limit) {
                throw ValidationException::withMessages([
                    'file' => ['El trámite ya alcanzó el límite de 10 documentos adjuntos.'],
                ]);
            }
        }
    }

    private function hashStoredFile(?string $path): ?string
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }

        $stream = Storage::disk('local')->readStream($path);
        if (! is_resource($stream)) {
            return null;
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}
