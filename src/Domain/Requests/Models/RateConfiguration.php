<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'rate_key',
    'rate_value'
])]
class RateConfiguration extends Model
{
    use HasFactory;

    protected $table = 'rate_configurations';

    const CREATED_AT = null;

    protected function casts(): array
    {
        return [
            'rate_value' => 'decimal:2',
        ];
    }
}
