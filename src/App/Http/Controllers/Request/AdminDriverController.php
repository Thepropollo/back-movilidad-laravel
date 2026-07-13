<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Domain\Auth\Models\Driver;
use Domain\Auth\Models\DriverLicense;
use Domain\Auth\Models\User;
use Domain\Auth\Models\SystemLog;
use Domain\Requests\Models\RouteSheet;

class AdminDriverController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $drivers = Driver::with(['user', 'licenses'])->get();
        return response()->json($drivers);
    }

    public function store(Request $request)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id|unique:drivers,user_id',
            'contract_type' => 'required|string|in:nombramiento,contrato,LOSEP,CODIGO_TRABAJO',
            'is_available' => 'required|boolean',
            'license_type' => 'required|string|max:5',
            'current_points' => 'required|integer|min:0|max:30',
            'expiration_date' => 'required|date',
        ], [
            'user_id.unique' => 'Este usuario ya está registrado como chofer.',
            'contract_type.in' => 'El tipo de contrato no es válido.',
        ]);

        // Verify the user actually has the 'chofer' role
        $user = User::findOrFail($request->input('user_id'));
        if ($user->role->name !== 'chofer') {
            return response()->json(['message' => 'El usuario seleccionado debe tener el rol de chofer.'], 422);
        }

        $driver = null;

        DB::transaction(function () use ($request, &$driver, $admin) {
            $driver = Driver::create([
                'user_id' => $request->input('user_id'),
                'contract_type' => $request->input('contract_type'),
                'is_available' => $request->input('is_available'),
            ]);

            DriverLicense::create([
                'driver_id' => $driver->id,
                'license_type' => $request->input('license_type'),
                'current_points' => $request->input('current_points'),
                'expiration_date' => $request->input('expiration_date'),
            ]);

            SystemLog::create([
                'user_id' => $admin->id,
                'action' => "REGISTRÓ_CHOFER: {$driver->user->first_name} {$driver->user->last_name} (Licencia Tipo {$request->input('license_type')})",
                'affected_table' => 'drivers',
                'record_id' => $driver->id,
                'ip_address' => $request->ip()
            ]);
        });

        return response()->json([
            'message' => 'Chofer registrado con éxito.',
            'driver' => $driver->load(['user', 'licenses'])
        ], 210);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $driver = Driver::findOrFail($id);

        $request->validate([
            'contract_type' => 'required|string|in:nombramiento,contrato,LOSEP,CODIGO_TRABAJO',
            'is_available' => 'required|boolean',
            'license_type' => 'required|string|max:5',
            'current_points' => 'required|integer|min:0|max:30',
            'expiration_date' => 'required|date',
        ]);

        DB::transaction(function () use ($request, $driver, $admin) {
            $driver->update([
                'contract_type' => $request->input('contract_type'),
                'is_available' => $request->input('is_available'),
            ]);

            // Update or create license
            $license = $driver->licenses()->first();
            if ($license) {
                $license->update([
                    'license_type' => $request->input('license_type'),
                    'current_points' => $request->input('current_points'),
                    'expiration_date' => $request->input('expiration_date'),
                ]);
            } else {
                DriverLicense::create([
                    'driver_id' => $driver->id,
                    'license_type' => $request->input('license_type'),
                    'current_points' => $request->input('current_points'),
                    'expiration_date' => $request->input('expiration_date'),
                ]);
            }

            SystemLog::create([
                'user_id' => $admin->id,
                'action' => "ACTUALIZÓ_CHOFER: {$driver->user->first_name} {$driver->user->last_name} (Licencia Tipo {$request->input('license_type')})",
                'affected_table' => 'drivers',
                'record_id' => $driver->id,
                'ip_address' => $request->ip()
            ]);
        });

        return response()->json([
            'message' => 'Chofer actualizado con éxito.',
            'driver' => $driver->load(['user', 'licenses'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $driver = Driver::findOrFail($id);

        // Check if driver has route sheets associated
        $hasSheets = RouteSheet::where('driver_id', $driver->id)->exists();

        if ($hasSheets) {
            // Soft deactivation (is_available = false)
            $driver->update(['is_available' => false]);

            SystemLog::create([
                'user_id' => $admin->id,
                'action' => "DESACTIVÓ_CHOFER: {$driver->user->first_name} {$driver->user->last_name} debido a viajes asignados",
                'affected_table' => 'drivers',
                'record_id' => $driver->id,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'message' => 'El chofer tiene viajes asociados en hojas de ruta. Se ha establecido como no disponible.',
                'driver' => $driver->load(['user', 'licenses']),
                'soft_deleted' => true
            ]);
        }

        // Hard delete
        DB::transaction(function () use ($driver, $admin, $id) {
            $driver->licenses()->delete();
            $driver->delete();

            SystemLog::create([
                'user_id' => $admin->id,
                'action' => "ELIMINÓ_CHOFER_FISICO: {$driver->user->first_name} {$driver->user->last_name}",
                'affected_table' => 'drivers',
                'record_id' => $id,
                'ip_address' => $request->ip()
            ]);
        });

        return response()->json([
            'message' => 'Chofer eliminado físicamente con éxito.',
            'soft_deleted' => false
        ]);
    }
}
