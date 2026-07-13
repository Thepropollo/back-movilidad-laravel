<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Vehicles\Models\Vehicle;
use Domain\Auth\Models\SystemLog;
use Domain\Requests\Models\RouteSheet;

class AdminVehicleController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return response()->json(Vehicle::orderBy('plate')->get());
    }

    public function store(Request $request)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $request->validate([
            'plate' => 'required|string|max:10|unique:vehicles,plate',
            'brand' => 'required|string|max:50',
            'model' => 'required|string|max:50',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'required|string|max:30',
            'fuel_type' => 'required|string|in:diesel,extra,super',
            'current_mileage' => 'required|integer|min:0',
            'next_oil_change_mileage' => 'required|integer|min:0',
            'operational_status' => 'required|string|in:disponible,en_viaje,en_taller,inactivo',
        ], [
            'plate.unique' => 'La placa ingresada ya está registrada.',
            'year.max' => 'El año del vehículo no puede ser superior al año entrante.',
            'fuel_type.in' => 'El tipo de combustible debe ser diesel, extra o super.',
        ]);

        $vehicle = Vehicle::create($request->all());

        SystemLog::create([
            'user_id' => $admin->id,
            'action' => "REGISTRÓ_VEHÍCULO: {$vehicle->brand} {$vehicle->model} (Placa: {$vehicle->plate})",
            'affected_table' => 'vehicles',
            'record_id' => $vehicle->id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Vehículo registrado con éxito.',
            'vehicle' => $vehicle
        ], 210);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $vehicle = Vehicle::findOrFail($id);

        $request->validate([
            'plate' => "required|string|max:10|unique:vehicles,plate,{$id}",
            'brand' => 'required|string|max:50',
            'model' => 'required|string|max:50',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'required|string|max:30',
            'fuel_type' => 'required|string|in:diesel,extra,super',
            'current_mileage' => "required|integer|min:{$vehicle->current_mileage}",
            'next_oil_change_mileage' => 'required|integer|min:0',
            'operational_status' => 'required|string|in:disponible,en_viaje,en_taller,inactivo',
        ], [
            'plate.unique' => 'La placa ingresada ya está registrada.',
            'current_mileage.min' => "El kilometraje no puede ser inferior al registrado previamente ({$vehicle->current_mileage} km).",
        ]);

        $vehicle->update($request->all());

        SystemLog::create([
            'user_id' => $admin->id,
            'action' => "ACTUALIZÓ_VEHÍCULO: {$vehicle->brand} {$vehicle->model} (Placa: {$vehicle->plate})",
            'affected_table' => 'vehicles',
            'record_id' => $vehicle->id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Vehículo actualizado con éxito.',
            'vehicle' => $vehicle
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $vehicle = Vehicle::findOrFail($id);

        // Check if vehicle has route sheets associated
        $hasSheets = RouteSheet::where('vehicle_id', $vehicle->id)->exists();

        if ($hasSheets) {
            // Soft delete/deactivation
            $vehicle->update(['operational_status' => 'inactivo']);

            SystemLog::create([
                'user_id' => $admin->id,
                'action' => "DESACTIVÓ_VEHÍCULO: {$vehicle->brand} {$vehicle->model} (Placa: {$vehicle->plate}) debido a comisiones asociadas",
                'affected_table' => 'vehicles',
                'record_id' => $vehicle->id,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'message' => 'El vehículo tiene viajes asociados. Se ha establecido como inactivo.',
                'vehicle' => $vehicle,
                'soft_deleted' => true
            ]);
        }

        // Hard delete
        $vehicle->delete();

        SystemLog::create([
            'user_id' => $admin->id,
            'action' => "ELIMINÓ_VEHÍCULO_FISICO: {$vehicle->brand} {$vehicle->model} (Placa: {$vehicle->plate})",
            'affected_table' => 'vehicles',
            'record_id' => $id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Vehículo eliminado físicamente de la flota con éxito.',
            'soft_deleted' => false
        ]);
    }
}
