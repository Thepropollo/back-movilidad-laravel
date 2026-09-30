<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Actions\CreateDriverAction;
use Domain\Auth\Models\Driver;
use Domain\Vehicles\Models\Vehicle;
use Domain\Vehicles\Models\VehicleLegalDocument;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class FleetManageController extends Controller
{
    public function storeDriver(Request $request, CreateDriverAction $createDriver)
    {
        if (! $request->user()->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $data = $request->validate([
            'national_id' => 'required|string|max:10|unique:users,national_id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => ['required', 'string', Password::min(12)->letters()->numbers()],
            'contract_type' => 'required|in:nombramiento,contrato',
            'license_type' => 'required|string|max:10',
            'current_points' => 'required|integer|min:0',
            'expiration_date' => 'required|date',
        ]);

        $driver = $createDriver->execute($data);

        return response()->json(['message' => 'Conductor registrado.', 'driver' => $driver], 201);
    }

    public function updateDriver(Request $request, int $id)
    {
        if (! $request->user()->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $driver = Driver::with(['user', 'licenses'])->findOrFail($id);
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'contract_type' => 'sometimes|in:nombramiento,contrato',
            'is_available' => 'sometimes|boolean',
            'license_type' => 'sometimes|string|max:10',
            'current_points' => 'sometimes|integer|min:0',
            'expiration_date' => 'sometimes|date',
        ]);

        if (isset($data['first_name']) || isset($data['last_name'])) {
            $driver->user->update(array_filter([
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
            ]));
        }

        $driver->update(array_filter([
            'contract_type' => $data['contract_type'] ?? null,
            'is_available' => array_key_exists('is_available', $data) ? $data['is_available'] : null,
        ], fn ($v) => $v !== null));

        $license = $driver->licenses()->latest('id')->first();
        if ($license) {
            $license->update(array_filter([
                'license_type' => $data['license_type'] ?? null,
                'current_points' => $data['current_points'] ?? null,
                'expiration_date' => $data['expiration_date'] ?? null,
            ], fn ($v) => $v !== null));
        }

        return response()->json(['message' => 'Conductor actualizado.', 'driver' => $driver->fresh(['user', 'licenses'])]);
    }

    public function storeVehicle(Request $request)
    {
        if (! $request->user()->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $data = $request->validate([
            'plate' => 'required|string|max:20|unique:vehicles,plate',
            'brand' => 'required|string|max:50',
            'model' => 'required|string|max:50',
            'year' => 'required|integer|min:1990',
            'color' => 'nullable|string|max:40',
            'fuel_type' => 'required|string|max:30',
            'current_mileage' => 'required|integer|min:0',
            'next_oil_change_mileage' => 'required|integer|min:0',
            'operational_status' => 'nullable|in:disponible,en_viaje,en_taller,inactivo',
            'registration_number' => 'nullable|string|max:40',
        ]);

        $vehicle = Vehicle::create([
            ...$data,
            'color' => $data['color'] ?? 'N/D',
            'operational_status' => $data['operational_status'] ?? 'disponible',
        ]);

        return response()->json(['message' => 'Vehículo registrado.', 'vehicle' => $vehicle], 201);
    }

    public function updateVehicle(Request $request, int $id)
    {
        if (! $request->user()->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $vehicle = Vehicle::findOrFail($id);
        $data = $request->validate([
            'brand' => 'sometimes|string|max:50',
            'model' => 'sometimes|string|max:50',
            'year' => 'sometimes|integer|min:1990',
            'color' => 'sometimes|string|max:40',
            'fuel_type' => 'sometimes|string|max:30',
            'current_mileage' => 'sometimes|integer|min:0',
            'next_oil_change_mileage' => 'sometimes|integer|min:0',
            'operational_status' => 'sometimes|in:disponible,en_viaje,en_taller,inactivo',
            'registration_number' => 'sometimes|nullable|string|max:40',
        ]);

        $vehicle->update($data);

        return response()->json(['message' => 'Vehículo actualizado.', 'vehicle' => $vehicle]);
    }

    public function updateVehicleDocuments(Request $request, int $id)
    {
        if (! $request->user()->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $vehicle = Vehicle::findOrFail($id);
        $data = $request->validate([
            'documents' => 'required|array',
            'documents.permiso_circulacion' => 'nullable|array',
            'documents.revision_tecnica' => 'nullable|array',
            'documents.matricula' => 'nullable|array',
            'documents.*.issue_date' => 'nullable|date',
            'documents.*.expiration_date' => 'nullable|date',
        ]);

        foreach (['permiso_circulacion', 'revision_tecnica', 'matricula'] as $type) {
            $documentData = $data['documents'][$type] ?? [];
            $expirationDate = $documentData['expiration_date'] ?? null;

            if (! $expirationDate) {
                VehicleLegalDocument::where('vehicle_id', $vehicle->id)
                    ->where('document_type', $type)
                    ->delete();
                continue;
            }

            $document = VehicleLegalDocument::where('vehicle_id', $vehicle->id)
                ->where('document_type', $type)
                ->first();

            VehicleLegalDocument::updateOrCreate(
                [
                    'vehicle_id' => $vehicle->id,
                    'document_type' => $type,
                ],
                [
                    'issue_date' => $documentData['issue_date']
                        ?? $document?->issue_date
                        ?? now()->toDateString(),
                    'expiration_date' => $expirationDate,
                ]
            );
        }

        return response()->json([
            'message' => 'Documentación del vehículo actualizada.',
            'vehicle' => $vehicle->load('legalDocuments'),
        ]);
    }
}
