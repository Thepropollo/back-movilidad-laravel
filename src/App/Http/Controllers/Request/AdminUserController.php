<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Domain\Auth\Models\User;
use Domain\Auth\Models\SystemLog;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\DeliveryReceptionAct;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $search = $request->query('search');
        $faculty = $request->query('faculty');

        $query = User::with('role')->orderBy('last_name', 'asc');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('national_id', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($faculty) {
            $query->where('faculty_institution', $faculty);
        }

        if ($request->query('paginate', 'true') === 'false') {
            return response()->json($query->get());
        }

        return response()->json($query->paginate(10));
    }

    public function store(Request $request)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $request->validate([
            'national_id' => 'required|string|size:10|unique:users,national_id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|string|min:6',
            'faculty_institution' => 'required|string|max:150',
            'role_id' => 'required|exists:roles,id',
        ], [
            'national_id.unique' => 'La cédula ingresada ya está registrada.',
            'national_id.size' => 'La cédula debe tener exactamente 10 caracteres.',
            'email.unique' => 'El correo electrónico ya está registrado.',
            'password.min' => 'La contraseña debe tener al menos 6 caracteres.',
        ]);

        $user = User::create([
            'national_id' => $request->input('national_id'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
            'faculty_institution' => $request->input('faculty_institution'),
            'role_id' => $request->input('role_id'),
            'is_active' => true,
        ]);

        SystemLog::create([
            'user_id' => $admin->id,
            'action' => "CREÓ_USUARIO: {$user->first_name} {$user->last_name} ({$user->email})",
            'affected_table' => 'users',
            'record_id' => $user->id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Usuario creado con éxito.',
            'user' => $user->load('role')
        ], 210);
    }

    public function update(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $user = User::findOrFail($id);

        $request->validate([
            'national_id' => "required|string|size:10|unique:users,national_id,{$id}",
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => "required|email|max:100|unique:users,email,{$id}",
            'password' => 'nullable|string|min:6',
            'faculty_institution' => 'required|string|max:150',
            'role_id' => 'required|exists:roles,id',
            'is_active' => 'required|boolean',
        ], [
            'national_id.unique' => 'La cédula ingresada ya está registrada.',
            'email.unique' => 'El correo electrónico ya está registrado.',
        ]);

        $data = [
            'national_id' => $request->input('national_id'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'email' => $request->input('email'),
            'faculty_institution' => $request->input('faculty_institution'),
            'role_id' => $request->input('role_id'),
            'is_active' => $request->input('is_active'),
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        $user->update($data);

        SystemLog::create([
            'user_id' => $admin->id,
            'action' => "ACTUALIZÓ_USUARIO: {$user->first_name} {$user->last_name} ({$user->email})",
            'affected_table' => 'users',
            'record_id' => $user->id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Usuario actualizado con éxito.',
            'user' => $user->load('role')
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $admin = $request->user();
        if (!$admin || $admin->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $user = User::findOrFail($id);

        // Check if user has associated records (solicitudes, hojas de ruta, actas, etc.)
        $hasRequests = MobilizationRequest::where('requester_id', $user->id)
            ->orWhere('rectorate_approver_id', $user->id)
            ->exists();

        $hasRouteSheets = RouteSheet::where('driver_id', $user->id)
            ->orWhere('transport_chief_id', $user->id)
            ->exists();

        $hasActs = DeliveryReceptionAct::where('mechanic_or_guard_id', $user->id)->exists();

        if ($hasRequests || $hasRouteSheets || $hasActs) {
            // Soft deactivation (is_active = false)
            $user->update(['is_active' => false]);

            SystemLog::create([
                'user_id' => $admin->id,
                'action' => "DESACTIVÓ_USUARIO: {$user->first_name} {$user->last_name} (Desactivado por tener historial de comisiones/firmas)",
                'affected_table' => 'users',
                'record_id' => $user->id,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'message' => 'El usuario tiene solicitudes o viajes asociados. Se ha desactivado en lugar de eliminar.',
                'user' => $user->load('role'),
                'soft_deleted' => true
            ]);
        }

        // Hard delete
        $user->delete();

        SystemLog::create([
            'user_id' => $admin->id,
            'action' => "ELIMINÓ_USUARIO_FISICO: {$user->first_name} {$user->last_name} ({$user->email})",
            'affected_table' => 'users',
            'record_id' => $id,
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'message' => 'Usuario eliminado físicamente con éxito.',
            'soft_deleted' => false
        ]);
    }
}
