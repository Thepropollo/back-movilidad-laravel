<?php

namespace Domain\Workshop\Actions;

use Domain\Auth\Models\Driver;
use Domain\Requests\Models\RouteSheet;
use Domain\Workshop\Models\IssueLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReportVehicleIssueAction
{
    public function execute(
        int $userId,
        int $vehicleId,
        ?int $routeSheetId,
        string $description,
        ?string $city,
        ?string $issueType,
        ?string $breakdownDate
    ): IssueLog {
        return DB::transaction(function () use (
            $userId,
            $vehicleId,
            $routeSheetId,
            $description,
            $city,
            $issueType,
            $breakdownDate
        ) {
            $driver = Driver::query()->where('user_id', $userId)->first();
            if (! $driver) {
                throw new AuthorizationException('La cuenta no tiene un perfil de conductor.');
            }

            if ($routeSheetId !== null) {
                $sheet = RouteSheet::query()->lockForUpdate()->find($routeSheetId);
                if (! $sheet
                    || (int) $sheet->driver_id !== (int) $driver->id
                    || (int) $sheet->vehicle_id !== $vehicleId
                    || $sheet->driver_response !== 'aceptado'
                    || ! in_array($sheet->trip_status, ['programado', 'en_ruta', 'pendiente_feedback'], true)) {
                    throw new AuthorizationException('La hoja de ruta no corresponde a una asignación vigente de este conductor y vehículo.');
                }
            } else {
                $sheet = RouteSheet::query()
                    ->where('driver_id', $driver->id)
                    ->where('vehicle_id', $vehicleId)
                    ->where('driver_response', 'aceptado')
                    ->whereIn('trip_status', ['programado', 'en_ruta', 'pendiente_feedback'])
                    ->lockForUpdate()
                    ->first();

                if (! $sheet) {
                    throw new AuthorizationException('El vehículo no corresponde a una asignación vigente de este conductor.');
                }
            }

            $formattedDescription = trim(
                ($issueType ?? 'Novedad')
                .($city ? ' en '.$city : '')
                .': '.$description
            );

            return IssueLog::create([
                'vehicle_id' => $vehicleId,
                'route_sheet_id' => $sheet->id,
                'reporting_driver_id' => $userId,
                'breakdown_date' => $breakdownDate ?? now()->toDateString(),
                'description' => $formattedDescription,
                'status' => 'pendiente',
            ]);
        });
    }
}
