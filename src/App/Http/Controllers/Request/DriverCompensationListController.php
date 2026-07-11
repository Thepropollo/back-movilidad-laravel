<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Models\DriverCompensation;

class DriverCompensationListController extends Controller
{
    public function __invoke()
    {
        $compensations = DriverCompensation::with([
            'routeSheet.vehicle',
            'routeSheet.driver.user',
            'routeSheet.request.requester'
        ])
        ->where('payment_status', 'pendiente_comprobante')
        ->orderBy('id', 'desc')
        ->get();

        return response()->json($compensations);
    }
}
