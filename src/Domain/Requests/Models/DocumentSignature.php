<?php

namespace Domain\Requests\Models;

use Domain\Auth\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'document_id',
    'user_id',
    'slot',
    'slot_label',
    'document_hash',
    'signed_payload',
    'crypto_signature',
    'key_fingerprint',
    'signature_image_path',
    'ip_address',
    'signed_at',
])]
class DocumentSignature extends Model
{
    protected $table = 'document_signatures';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'document_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
