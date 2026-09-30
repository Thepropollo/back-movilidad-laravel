<?php

namespace Domain\Requests\Actions;

use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\RouteSheetStop;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateRouteSheetStopAction
{
    /** @param array<string, mixed> $data */
    public function execute(int $routeSheetId, int $userId, array $data): RouteSheetStop
    {
        return DB::transaction(function () use ($routeSheetId, $userId, $data) {
            $routeSheet = RouteSheet::query()->lockForUpdate()->findOrFail($routeSheetId);
            $driver = Driver::query()->where('user_id', $userId)->first();

            if (! $driver || (int) $routeSheet->driver_id !== (int) $driver->id) {
                throw new AuthorizationException('La hoja de ruta no corresponde a este conductor.');
            }

            if ($routeSheet->driver_response !== 'aceptado' || $routeSheet->trip_status !== 'en_ruta') {
                throw ValidationException::withMessages([
                    'route_sheet_id' => ['Solo se registran paradas en una asignación aceptada y en curso.'],
                ]);
            }

            if (isset($data['odometer_km'])) {
                $lastStopMileage = $routeSheet->stops()
                    ->whereNotNull('odometer_km')
                    ->orderByDesc('sequence')
                    ->orderByDesc('id')
                    ->value('odometer_km');
                $minimumMileage = max(
                    (int) ($routeSheet->initial_mileage ?? 0),
                    (int) ($lastStopMileage ?? 0)
                );

                if ((int) $data['odometer_km'] < $minimumMileage) {
                    throw ValidationException::withMessages([
                        'odometer_km' => ['El kilometraje debe ser igual o mayor al último registro del viaje.'],
                    ]);
                }
            }

            $sequence = (int) $routeSheet->stops()->max('sequence') + 1;
            $stop = RouteSheetStop::create([
                'route_sheet_id' => $routeSheet->id,
                'sequence' => $sequence,
                'location' => $data['location'],
                'visited_canton' => $data['location'],
                'departure_at' => $data['departure_at'] ?? null,
                'arrival_time' => $data['arrival_time'] ?? now(),
                'odometer_km' => $data['odometer_km'] ?? null,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            if (isset($data['odometer_km'])) {
                $routeSheet->update(['final_mileage' => (int) $data['odometer_km']]);
            }

            return $stop;
        });
    }
}
