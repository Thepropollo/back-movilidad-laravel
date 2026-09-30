<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Illuminate\Http\Request;

class RouteMapController extends Controller
{
    public function show(Request $request, int $id)
    {
        $sheet = RouteSheet::with([
            'request.requester',
            'vehicle',
            'driver.user',
            'stops',
        ])->findOrFail($id);

        if (! $this->canViewSheet($request->user(), $sheet)) {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        return response()->json([
            'route_sheet_id' => $sheet->id,
            'trip_status' => $sheet->trip_status,
            'driver_response' => $sheet->driver_response,
            'origin' => $sheet->request?->origin,
            'destination' => $sheet->request?->destination,
            'destination_address' => $sheet->request?->destination_address,
            'destination_latitude' => $sheet->request?->destination_latitude,
            'destination_longitude' => $sheet->request?->destination_longitude,
            'departure_date' => optional($sheet->request?->departure_date)?->toDateString(),
            'return_date' => optional($sheet->request?->return_date)?->toDateString(),
            'vehicle' => $sheet->vehicle ? [
                'id' => $sheet->vehicle->id,
                'plate' => $sheet->vehicle->plate,
                'brand' => $sheet->vehicle->brand,
                'model' => $sheet->vehicle->model,
            ] : null,
            'driver' => $sheet->driver?->user ? [
                'id' => $sheet->driver->user->id,
                'first_name' => $sheet->driver->user->first_name,
                'last_name' => $sheet->driver->user->last_name,
            ] : null,
            'stops' => $sheet->stops->map(fn ($s) => [
                'id' => $s->id,
                'sequence' => $s->sequence,
                'location' => $s->location ?: $s->visited_canton,
                'latitude' => $s->latitude,
                'longitude' => $s->longitude,
                'odometer_km' => $s->odometer_km,
                'arrival_time' => $s->arrival_time,
                'notes' => $s->notes,
            ]),
        ]);
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = RouteSheet::with(['request', 'vehicle', 'driver.user', 'stops'])
            ->orderByDesc('id');

        if ($user->hasRole(['secretaria', 'jefe_transporte', 'vicerrector', 'rector'])) {
            // visión global
        } elseif ($user->hasRole(['conductor', 'chofer'])) {
            $driver = Driver::where('user_id', $user->id)->first();
            if (! $driver) {
                return response()->json([]);
            }
            $query->where('driver_id', $driver->id);
        } elseif ($user->hasRole(['docente', 'solicitante']) && ! $user->hasRole(['responsable_facultad'])) {
            $query->whereHas('request', fn ($q) => $q->where('requester_id', $user->id));
        } elseif ($user->hasRole(['responsable_facultad'])) {
            $faculty = $user->faculty_institution;
            $query->whereHas('request.requester', fn ($q) => $q->where('faculty_institution', $faculty));
        } elseif ($user->hasRole(['estudiante', 'pasajero'])) {
            $query->whereHas('request.passengers', fn ($q) => $q->where('user_id', $user->id));
        } else {
            return response()->json([]);
        }

        $rows = $query->paginate($this->resolvePerPage($request));
        $rows->setCollection($rows->getCollection()->map(function (RouteSheet $sheet) {
            return [
                'id' => $sheet->id,
                'origin' => $sheet->request?->origin,
                'destination' => $sheet->request?->destination,
                'trip_status' => $sheet->trip_status,
                'driver' => trim(($sheet->driver?->user?->first_name.' '.$sheet->driver?->user?->last_name) ?: ''),
                'vehicle' => $sheet->vehicle?->plate,
                'stops_count' => $sheet->stops->count(),
                'has_geo' => $sheet->stops->whereNotNull('latitude')->count() > 0
                    || ($sheet->request?->destination_latitude !== null
                        && $sheet->request?->destination_longitude !== null),
            ];
        }));

        return response()->json($rows);
    }

    private function canViewSheet($user, RouteSheet $sheet): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(['secretaria', 'jefe_transporte', 'vicerrector', 'rector'])) {
            return true;
        }

        if (optional($sheet->driver)->user_id === $user->id) {
            return true;
        }

        if ($sheet->request?->requester_id === $user->id) {
            return true;
        }

        if ($sheet->request && $sheet->request->passengers()->where('user_id', $user->id)->exists()) {
            return true;
        }

        if ($user->hasRole(['responsable_facultad'])) {
            return $sheet->request?->requester?->faculty_institution === $user->faculty_institution;
        }

        return false;
    }
}
