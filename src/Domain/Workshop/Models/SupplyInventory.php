<?php

namespace Domain\Workshop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'supply_name',
    'current_stock',
    'measurement_unit'
])]
/**
 * Model representing supply inventories in the workshop.
 */
class SupplyInventory extends Model
{
    use HasFactory;

    protected $table = 'supply_inventories';

    public $timestamps = false;
}
