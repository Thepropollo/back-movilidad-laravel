<?php

namespace Domain\Vehicles\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vehicle_id',
    'document_type',
    'issue_date',
    'expiration_date',
    'pdf_file'
])]
class VehicleLegalDocument extends Model
{
    use HasFactory;

    protected $table = 'vehicle_legal_documents';

    public $timestamps = false;

    /**
     * Obtener el vehículo asociado con este documento legal.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
