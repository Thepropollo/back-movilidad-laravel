<?php

namespace Domain\Requests\Models;

use Domain\Auth\Models\User;
use Domain\Workshop\Models\IssueLog;
use Domain\Workshop\Models\WorkshopWorkOrder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'document_type',
    'source',
    'request_id',
    'work_order_id',
    'issue_log_id',
    'file_path',
    'original_filename',
    'mime_type',
    'file_hash',
    'generated_by',
])]
class GeneratedDocument extends Model
{
    protected $table = 'generated_documents';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /** @var array<string, array{code: string, label: string}> */
    public const CATALOG = [
        'orden_movilizacion' => ['code' => 'PST-01-F-003', 'label' => 'Orden de movilización'],
        'hoja_ruta' => ['code' => 'PST-01-F-006', 'label' => 'Hoja de ruta'],
        'acta_entrega' => ['code' => 'PST-01-F-004.1', 'label' => 'Acta de entrega-recepción'],
        'orden_taller' => ['code' => 'PST-01-F-003-OT', 'label' => 'Orden de revisión mecánica'],
        'libro_novedades' => ['code' => 'LIBRO-NOVEDADES', 'label' => 'Libro de novedades'],
        'provision_lubricantes' => ['code' => 'PROVISION-LUBRICANTES', 'label' => 'Provisión de lubricantes y filtros'],
        'informe_mensual' => ['code' => 'PAM-04-F-007', 'label' => 'Informe mensual de viajes'],
        'control_aceite' => ['code' => 'CTRL-ACEITE', 'label' => 'Control de cambio de aceite'],
    ];

    /**
     * @var array<string, array<string, array{label: string, roles: list<string>}>>
     */
    public const SIGNATURE_SLOTS = [
        'orden_movilizacion' => [
            'solicitante' => ['label' => 'Solicitante', 'roles' => ['docente', 'solicitante', 'responsable_facultad']],
            'secretaria' => ['label' => 'Secretaría / Transporte', 'roles' => ['secretaria', 'jefe_transporte']],
            'autoridad' => ['label' => 'Autoridad', 'roles' => ['vicerrector', 'rector', 'secretaria', 'jefe_transporte']],
        ],
        'hoja_ruta' => [
            'conductor' => ['label' => 'Chofer', 'roles' => ['conductor', 'chofer']],
            'solicitante' => ['label' => 'Comisionado/a', 'roles' => ['docente', 'solicitante', 'responsable_facultad']],
            'secretaria' => ['label' => 'Secretaría de Transporte', 'roles' => ['secretaria', 'jefe_transporte']],
        ],
        'acta_entrega' => [
            'mecanico' => ['label' => 'Entregado por', 'roles' => ['mecanico']],
            'conductor' => ['label' => 'Recibido por', 'roles' => ['conductor', 'chofer']],
            'secretaria' => ['label' => 'Supervisor de patio', 'roles' => ['secretaria', 'jefe_transporte']],
        ],
        'orden_taller' => [
            'secretaria' => ['label' => 'Supervisor', 'roles' => ['secretaria', 'jefe_transporte']],
            'mecanico' => ['label' => 'Mecánico', 'roles' => ['mecanico']],
        ],
        'libro_novedades' => [
            'mecanico' => ['label' => 'Mecánico responsable', 'roles' => ['mecanico']],
            'conductor' => ['label' => 'Custodio', 'roles' => ['conductor', 'chofer']],
            'secretaria' => ['label' => 'Analista de Transporte', 'roles' => ['secretaria', 'jefe_transporte']],
        ],
        'provision_lubricantes' => [
            'mecanico' => ['label' => 'Mecánico responsable', 'roles' => ['mecanico']],
            'secretaria' => ['label' => 'Custodio / Secretaría', 'roles' => ['secretaria', 'jefe_transporte']],
        ],
        'informe_mensual' => [
            'secretaria' => ['label' => 'Elaborado / Revisado', 'roles' => ['secretaria', 'jefe_transporte']],
            'autoridad' => ['label' => 'Aprobado por', 'roles' => ['vicerrector', 'rector']],
        ],
        'control_aceite' => [
            'mecanico' => ['label' => 'Mecánico', 'roles' => ['mecanico']],
            'secretaria' => ['label' => 'Secretaría de Transporte', 'roles' => ['secretaria', 'jefe_transporte']],
        ],
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(MobilizationRequest::class, 'request_id');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkshopWorkOrder::class, 'work_order_id');
    }

    public function issueLog(): BelongsTo
    {
        return $this->belongsTo(IssueLog::class, 'issue_log_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function signatures(): HasMany
    {
        return $this->hasMany(DocumentSignature::class, 'document_id')->orderBy('id');
    }

    /**
     * @return array<string, array{label: string, roles: list<string>}>
     */
    public static function signatureSlots(string $type): array
    {
        return self::SIGNATURE_SLOTS[$type] ?? [];
    }

    public function label(): string
    {
        return self::CATALOG[$this->document_type]['label'] ?? $this->document_type;
    }

    public function code(): string
    {
        return self::CATALOG[$this->document_type]['code'] ?? $this->document_type;
    }
}
