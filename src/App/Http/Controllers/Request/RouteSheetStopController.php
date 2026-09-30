<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Requests\Actions\CreateRouteSheetStopAction;
use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Illuminate\Http\Request;

class RouteSheetStopController extends Controller
{
    public function index(Request $request, int $id)
    {
        $sheet = RouteSheet::with('stops')->findOrFail($id);
        $this->authorizeSheet($request, $sheet);

        return response()->json($sheet->stops);
    }

    public function store(Request $request, int $id, CreateRouteSheetStopAction $action)
    {
        $sheet = RouteSheet::findOrFail($id);
        $this->authorizeSheet($request, $sheet, true);

        $data = $request->validate([
            'location' => 'required|string|max:150',
            'departure_at' => 'nullable|date',
            'arrival_time' => 'nullable|date',
            'odometer_km' => 'nullable|integer|min:0',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'notes' => 'nullable|string|max:500',
        ]);

        $stop = $action->execute((int) $sheet->id, (int) $request->user()->id, $data);

        return response()->json(['message' => 'Parada registrada.', 'stop' => $stop], 201);
    }

    private function authorizeSheet(Request $request, RouteSheet $sheet, bool $write = false): void
    {
        $user = $request->user();
        if (! $write && $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return;
        }

        $driver = Driver::where('user_id', $user->id)->first();
        if ($driver && (int) $sheet->driver_id === (int) $driver->id) {
            return;
        }

        abort(response()->json(['message' => 'Acceso denegado.'], 403));
    }
}
