<?php

namespace Domain\Workshop\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Domain\Vehicles\Models\Vehicle;
use Domain\Requests\Models\RouteSheet;
use Domain\Auth\Models\User;

#[Fillable([
    'vehicle_id',
    'route_sheet_id',
    'reporting_driver_id',
    'breakdown_date',
    'description',
    'status'
])]
class IssueLog extends Model
{
    use HasFactory;

    protected $table = 'issue_logs';

    public $timestamps = false;

    protected $casts = [
        'breakdown_date' => 'date'
    ];

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function routeSheet(): BelongsTo
    {
        return $this->belongsTo(RouteSheet::class, 'route_sheet_id');
    }

    public function reportingDriver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporting_driver_id');
    }
}
