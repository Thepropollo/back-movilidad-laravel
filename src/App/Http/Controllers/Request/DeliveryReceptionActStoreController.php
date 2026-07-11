<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Requests\Actions\CreateDeliveryReceptionActAction;

class DeliveryReceptionActStoreController extends Controller
{
    public function __construct(
        protected CreateDeliveryReceptionActAction $action
    ) {}

    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        $request->validate([
            'route_sheet_id' => 'required|integer|exists:route_sheets,id',
            'registration_type' => 'required|string|in:salida,llegada',
            'fuel_level' => 'required|string|in:1/4,1/2,3/4,full',
            'checkpoint_mileage' => 'required|integer',
            'components' => 'required|array',
            'components.*.id' => 'required|integer|exists:checklist_inventory_components,id',
            'components.*.physical_condition' => 'required|string|in:BUENO,REGULAR,MALO',
        ], [
            'route_sheet_id.required' => 'La hoja de ruta es obligatoria.',
            'registration_type.required' => 'El tipo de registro es obligatorio (salida o llegada).',
            'fuel_level.required' => 'El nivel de combustible es obligatorio.',
            'checkpoint_mileage.required' => 'El kilometraje en garita es obligatorio.',
            'components.required' => 'Debe evaluar los componentes del checklist.',
        ]);

        $act = $this->action->execute(
            routeSheetId: (int) $request->input('route_sheet_id'),
            mechanicOrGuardId: (int) $user->id,
            registrationType: $request->input('registration_type'),
            fuelLevel: $request->input('fuel_level'),
            checkpointMileage: (int) $request->input('checkpoint_mileage'),
            components: $request->input('components')
        );

        return response()->json([
            'message' => 'Inspección en patio registrada de forma exitosa.',
            'act' => $act
        ], 201);
    }
}
