<?php

namespace Domain\Requests\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'component_name',
    'category'
])]
class ChecklistInventoryComponent extends Model
{
    use HasFactory;

    protected $table = 'checklist_inventory_components';

    public $timestamps = false;
}
