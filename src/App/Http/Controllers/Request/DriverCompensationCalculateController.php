<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Actions\CalculateDriverCompensationAction;

class DriverCompensationCalculateController extends Controller
{
    public function __construct(
        protected CalculateDriverCompensationAction $action
    ) {}

    public function __invoke($hoja_ruta_id)
    {
        $calculation = $this->action->execute((int) $hoja_ruta_id);
        return response()->json($calculation);
    }
}
