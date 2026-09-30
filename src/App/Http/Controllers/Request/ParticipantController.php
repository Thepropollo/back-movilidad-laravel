<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\Role;
use Domain\Auth\Models\User;
use Domain\Auth\Support\RoleCatalog;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\PassengerManifest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ParticipantController extends Controller
{
    public function index(Request $request, int $id)
    {
        $user = $request->user();
        $mobilization = MobilizationRequest::findOrFail($id);

        $mobilization->loadMissing('requester');

        $allowed = $user->hasRole(['secretaria', 'jefe_transporte', 'vicerrector', 'rector'])
            || $mobilization->requester_id === $user->id
            || (
                $user->hasRole(['responsable_facultad'])
                && optional($mobilization->requester)->faculty_institution === $user->faculty_institution
            );

        if (! $allowed) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $rows = PassengerManifest::with('user')
            ->where('request_id', $id)
            ->get();
        $participants = $rows->map(fn (PassengerManifest $row) => $this->presentParticipant($row));

        return response()->json([
            'participants' => $participants,
            'summary' => [
                'total' => $participants->count(),
                'aceptados' => $participants->where('invitation_status', 'aceptado')->count(),
                'rechazados' => $participants->where('invitation_status', 'rechazado')->count(),
                'pendientes' => $participants->where('invitation_status', 'invitado')->count(),
            ],
        ]);
    }

    public function store(Request $request, int $id)
    {
        $user = $request->user();
        $mobilization = MobilizationRequest::findOrFail($id);

        if ($mobilization->requester_id !== $user->id && ! $user->hasRole(['secretaria', 'jefe_transporte'])) {
            return response()->json(['message' => 'Solo el docente solicitante puede invitar.'], 403);
        }

        $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'email' => 'nullable|email',
            'national_id' => 'nullable|string|max:10',
        ]);

        $participant = null;
        if ($request->filled('user_id')) {
            $participant = User::find($request->integer('user_id'));
        } elseif ($request->filled('email')) {
            $participant = User::where('email', $request->input('email'))->first();
        } elseif ($request->filled('national_id')) {
            $participant = User::where('national_id', $request->input('national_id'))->first();
        }

        if (! $participant) {
            return response()->json(['message' => 'Estudiante no encontrado en el directorio.'], 404);
        }

        if (! $participant->hasRole([RoleCatalog::ESTUDIANTE, 'pasajero'])) {
            return response()->json(['message' => 'El usuario no tiene rol de estudiante.'], 422);
        }

        $row = PassengerManifest::firstOrCreate(
            ['request_id' => $id, 'user_id' => $participant->id],
            [
                'attended' => false,
                'invitation_status' => 'invitado',
            ]
        );

        return response()->json([
            'message' => 'Participante invitado.',
            'participant' => $this->presentParticipant($row->load('user')),
        ], 201);
    }

    public function myInvitations(Request $request)
    {
        $rows = PassengerManifest::with(['request.requester', 'request.routeSheet.vehicle', 'request.routeSheet.driver.user'])
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->get();

        return response()->json($rows->map(function (PassengerManifest $row) {
            $mobilization = $row->request;
            $sheet = $mobilization?->routeSheet;

            return [
                ...$this->presentParticipant($row),
                'request' => $mobilization ? [
                    'id' => $mobilization->id,
                    'status' => $mobilization->status,
                    'origin' => $mobilization->origin,
                    'destination' => $mobilization->destination,
                    'departure_date' => $mobilization->departure_date?->toDateString(),
                    'return_date' => $mobilization->return_date?->toDateString(),
                    'requester' => $mobilization->requester ? [
                        'first_name' => $mobilization->requester->first_name,
                        'last_name' => $mobilization->requester->last_name,
                    ] : null,
                    'route_sheet' => $sheet ? [
                        'id' => $sheet->id,
                        'trip_status' => $sheet->trip_status,
                        'vehicle' => $sheet->vehicle ? ['plate' => $sheet->vehicle->plate] : null,
                        'driver' => $sheet->driver?->user ? [
                            'first_name' => $sheet->driver->user->first_name,
                            'last_name' => $sheet->driver->user->last_name,
                        ] : null,
                    ] : null,
                ] : null,
            ];
        })->values());
    }

    public function respond(Request $request, int $id)
    {
        $row = PassengerManifest::with('request')->findOrFail($id);
        if ($row->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        if ($row->invitation_status !== 'invitado') {
            return response()->json(['message' => 'Ya respondió esta invitación.'], 400);
        }

        $action = $request->input('action', 'accept');
        if ($action === 'reject') {
            $request->validate(['reason' => 'required|string|max:500']);
            $row->update([
                'invitation_status' => 'rechazado',
                'reject_reason' => $request->input('reason'),
                'responded_at' => now(),
                'attended' => false,
            ]);
        } else {
            $row->update([
                'invitation_status' => 'aceptado',
                'responded_at' => now(),
                'attended' => true,
                'reject_reason' => null,
            ]);
        }

        return response()->json([
            'message' => 'Respuesta registrada.',
            'participant' => $this->presentParticipant($row->fresh('user')),
            'request' => $row->request ? [
                'id' => $row->request->id,
                'status' => $row->request->status,
            ] : null,
        ]);
    }

    public function searchStudents(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        $perPage = $this->resolvePerPage($request, 'app.student_search_per_page');

        $studentRoleIds = Role::query()
            ->whereIn('name', [RoleCatalog::ESTUDIANTE, 'pasajero'])
            ->pluck('id')
            ->all();

        if ($studentRoleIds === []) {
            return response()->json([]);
        }

        $students = User::query()
            ->where(function (Builder $query) use ($studentRoleIds) {
                $query
                    ->whereIn('role_id', $studentRoleIds)
                    ->orWhereHas('roles', fn (Builder $roleQuery) => $roleQuery->whereIn('roles.id', $studentRoleIds));
            })
            ->where(function (Builder $query) use ($q) {
                $query->whereLike('first_name', "{$q}%", caseSensitive: false)
                    ->orWhereLike('last_name', "{$q}%", caseSensitive: false);
            })
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->simplePaginate(
                $perPage,
                ['id', 'first_name', 'last_name', 'faculty_institution']
            );

        return response()->json($students);
    }

    /** @return array<string, mixed> */
    private function presentParticipant(PassengerManifest $row): array
    {
        $row->loadMissing('user');

        return [
            'id' => $row->id,
            'user_id' => $row->user_id,
            'first_name' => $row->user?->first_name,
            'last_name' => $row->user?->last_name,
            'invitation_status' => $row->invitation_status,
            'attended' => $row->attended,
            'reject_reason' => $row->reject_reason,
            'responded_at' => $row->responded_at,
        ];
    }
}
