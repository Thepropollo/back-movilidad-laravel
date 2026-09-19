<?php

namespace Domain\Alerts\Actions;

use Carbon\Carbon;
use Domain\Alerts\Models\Alert;
use Domain\Auth\Models\DriverLicense;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\PassengerManifest;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\ServiceStation;
use Domain\Vehicles\Models\Vehicle;
use Domain\Vehicles\Models\VehicleLegalDocument;

class GenerateAlertsAction
{
    public const ALERT_LIFETIME_DAYS = 5;

    /** @var list<string> */
    private array $seenKeys = [];

    public function execute(): void
    {
        $this->seenKeys = [];
        $limit = Carbon::today()->addDays(30);

        foreach (DriverLicense::with('driver.user')->get() as $license) {
            $exp = Carbon::parse($license->expiration_date);
            if ($exp->lte($limit)) {
                $name = trim(($license->driver?->user?->first_name ?? '').' '.($license->driver?->user?->last_name ?? ''));
                $this->upsert(
                    key: 'licencia:'.$license->id,
                    type: 'licencia',
                    severity: $exp->isPast() ? 'alta' : 'media',
                    title: 'Licencia por vencer',
                    message: $name.' · vence '.$exp->toDateString(),
                    route: '/app/secretaria/flota/conductores',
                    entityId: $license->id,
                    audience: 'secretaria',
                    detail: [
                        'conductor' => $name,
                        'vence' => $exp->toDateString(),
                        'accion' => $exp->isPast() ? 'Renovar de inmediato' : 'Programar renovación',
                    ],
                );
            }
        }

        foreach (VehicleLegalDocument::with('vehicle')->get() as $doc) {
            if (! $doc->expiration_date) {
                continue;
            }
            $exp = Carbon::parse($doc->expiration_date);
            if ($exp->lte($limit)) {
                $this->upsert(
                    key: 'matricula:'.$doc->id,
                    type: 'matricula',
                    severity: $exp->isPast() ? 'alta' : 'media',
                    title: 'Documento de vehículo por vencer',
                    message: ($doc->vehicle?->plate ?? '').' · '.$doc->document_type,
                    route: '/app/secretaria/flota/vehiculos',
                    entityId: $doc->id,
                    audience: 'secretaria',
                    detail: [
                        'vehiculo' => $doc->vehicle?->plate,
                        'documento' => $doc->document_type,
                        'vence' => $exp->toDateString(),
                        'accion' => 'Actualizar documentación',
                    ],
                );
            }
        }

        foreach (Vehicle::all() as $vehicle) {
            if ($vehicle->current_mileage >= $vehicle->next_oil_change_mileage) {
                $this->upsert(
                    key: 'mantenimiento:'.$vehicle->id,
                    type: 'mantenimiento',
                    severity: 'alta',
                    title: 'Aceite vencido',
                    message: $vehicle->plate.' llegó al kilometraje de cambio',
                    route: '/app/secretaria/taller',
                    entityId: $vehicle->id,
                    audience: 'operacion',
                    detail: [
                        'vehiculo' => $vehicle->plate,
                        'km_actual' => (string) $vehicle->current_mileage,
                        'proximo_cambio' => (string) $vehicle->next_oil_change_mileage,
                        'accion' => 'No asignar. Enviar a cambio de aceite',
                    ],
                );
            }
        }

        foreach (ServiceStation::where('active_agreement', true)->get() as $station) {
            if ($station->monthly_quota_liters && $station->monthly_quota_liters > 0) {
                $remaining = (float) $station->monthly_quota_liters - (float) $station->consumed_liters;
                $threshold = (float) $station->monthly_quota_liters * 0.2;
                if ($remaining <= $threshold) {
                    $this->upsert(
                        key: 'cupo:'.$station->id,
                        type: 'cupo_combustible',
                        severity: 'media',
                        title: 'Cupo de combustible bajo',
                        message: $station->commercial_name.' · quedan '.$remaining.' L',
                        route: '/app/secretaria/gasolineras',
                        entityId: $station->id,
                        audience: 'secretaria',
                        detail: [
                            'estacion' => $station->commercial_name,
                            'restante' => $remaining.' L',
                            'accion' => 'Revisar cupo y despachos',
                        ],
                    );
                }
            }
        }

        foreach (MobilizationRequest::with('requester')->where('status', 'pendiente_secretaria')->get() as $request) {
            $this->upsert(
                key: 'auth:'.$request->id,
                type: 'viaje_autorizar',
                severity: 'alta',
                title: 'Viaje por autorizar',
                message: $this->tripHeadline($request),
                route: '/app/secretaria/autorizar',
                entityId: $request->id,
                audience: 'secretaria',
                detail: $this->requestDetail($request, 'Autorizar o rechazar en Secretaría'),
            );
        }

        foreach (MobilizationRequest::with('requester')->where('status', 'pendiente_rectorado')->get() as $request) {
            $detail = $this->requestDetail($request, 'Aprobar o rechazar viaje externo');
            $this->upsert(
                key: 'vic:'.$request->id,
                type: 'viaje_vicerrector',
                severity: 'alta',
                title: 'Viaje externo por aprobar',
                message: $this->tripHeadline($request),
                route: '/app/vicerrector/pendientes',
                entityId: $request->id,
                audience: 'vicerrector',
                detail: $detail,
            );
            $this->upsert(
                key: 'vic-sec:'.$request->id,
                type: 'viaje_vicerrector',
                severity: 'media',
                title: 'Externa en Vicerrectorado',
                message: $this->tripHeadline($request),
                route: '/app/secretaria/solicitudes',
                entityId: $request->id,
                audience: 'secretaria',
                detail: $this->requestDetail($request, 'Esperar visto bueno de Vicerrectorado'),
            );
        }

        foreach (
            MobilizationRequest::with('requester')
                ->whereIn('status', ['autorizada_secretaria', 'aprobado_rectorado'])
                ->whereDoesntHave('routeSheet')
                ->get() as $request
        ) {
            $this->upsert(
                key: 'asignar:'.$request->id,
                type: 'viaje_asignar',
                severity: 'alta',
                title: 'Viaje listo para asignar unidad',
                message: $this->tripHeadline($request),
                route: '/app/secretaria/asignar',
                entityId: $request->id,
                audience: 'secretaria',
                detail: $this->requestDetail($request, 'Asignar conductor y vehículo'),
            );
        }

        foreach (
            RouteSheet::with(['request.requester', 'vehicle', 'driver.user'])
                ->where('driver_response', 'pendiente')
                ->get() as $sheet
        ) {
            $driverUserId = $sheet->driver?->user_id;
            $this->upsert(
                key: 'viaje-cond:'.$sheet->id,
                type: 'viaje_asignado',
                severity: 'alta',
                title: 'Nuevo viaje asignado',
                message: $this->tripHeadline($sheet->request),
                route: '/app/conductor/viajes',
                entityId: $sheet->id,
                audience: 'conductor',
                userId: $driverUserId,
                detail: $this->sheetDetail($sheet, 'Aceptar o rechazar el viaje'),
            );
            $this->upsert(
                key: 'viaje-sec:'.$sheet->id,
                type: 'viaje_espera_conductor',
                severity: 'media',
                title: 'Esperando respuesta del conductor',
                message: $this->tripHeadline($sheet->request),
                route: '/app/secretaria/reasignar',
                entityId: $sheet->id,
                audience: 'secretaria',
                detail: $this->sheetDetail($sheet, 'Si rechaza, reasignar unidad'),
            );
            if ($sheet->request?->requester_id) {
                $this->upsert(
                    key: 'viaje-doc:'.$sheet->id,
                    type: 'viaje_asignado_solicitante',
                    severity: 'media',
                    title: 'Su viaje ya tiene unidad',
                    message: $this->tripHeadline($sheet->request),
                    route: '/app/docente/seguimiento',
                    entityId: $sheet->id,
                    audience: 'docente',
                    userId: $sheet->request->requester_id,
                    detail: $this->sheetDetail($sheet, 'Revise conductor, horario y documentos'),
                );
            }
        }

        foreach (RouteSheet::with(['request.requester', 'vehicle', 'driver.user'])->where('driver_response', 'rechazado')->get() as $sheet) {
            $this->upsert(
                key: 'rechazo:'.$sheet->id,
                type: 'rechazo_conductor',
                severity: 'alta',
                title: 'Conductor rechazó el viaje',
                message: $this->tripHeadline($sheet->request).' · hoja #'.$sheet->id,
                route: '/app/secretaria/reasignar',
                entityId: $sheet->id,
                audience: 'secretaria',
                detail: $this->sheetDetail($sheet, 'Reasignar conductor. Motivo: '.($sheet->driver_reject_reason ?: 'no indicado')),
            );
        }

        foreach (
            RouteSheet::with(['request.requester', 'vehicle', 'driver.user'])
                ->where('driver_response', 'aceptado')
                ->whereDoesntHave('deliveryActs')
                ->get() as $sheet
        ) {
            $this->upsert(
                key: 'acta:'.$sheet->id,
                type: 'viaje_acta',
                severity: 'alta',
                title: 'Acta de salida pendiente',
                message: $this->tripHeadline($sheet->request),
                route: '/app/mecanico/inspeccion',
                entityId: $sheet->id,
                audience: 'mecanico',
                detail: $this->sheetDetail($sheet, 'Levantar acta de entrega antes de la salida'),
            );
        }

        foreach (
            PassengerManifest::with('request.requester')
                ->where('invitation_status', 'invitado')
                ->get() as $invite
        ) {
            $this->upsert(
                key: 'inv:'.$invite->id,
                type: 'viaje_invitacion',
                severity: 'media',
                title: 'Invitación a un viaje',
                message: $this->tripHeadline($invite->request),
                route: '/app/estudiante/invitaciones',
                entityId: $invite->id,
                audience: 'estudiante',
                userId: $invite->user_id,
                detail: $this->requestDetail($invite->request, 'Confirmar o rechazar participación'),
            );
        }

        $this->prune();
    }

    /**
     * @param  array<string, mixed>  $detail
     */
    private function upsert(
        string $key,
        string $type,
        string $severity,
        string $title,
        string $message,
        ?string $route,
        ?int $entityId,
        string $audience,
        ?int $userId = null,
        array $detail = [],
    ): void {
        $this->seenKeys[] = $key;
        Alert::query()->updateOrCreate(
            ['key' => $key],
            [
                'type' => $type,
                'severity' => $severity,
                'title' => $title,
                'message' => $message,
                'route' => $route,
                'entity_id' => $entityId,
                'audience_role' => $audience,
                'user_id' => $userId,
                'detail' => $detail,
            ]
        );
    }

    private function prune(): void
    {
        Alert::query()
            ->where('created_at', '<', Carbon::now()->subDays(self::ALERT_LIFETIME_DAYS))
            ->delete();

        if ($this->seenKeys === []) {
            Alert::query()->delete();

            return;
        }

        Alert::query()->whereNotIn('key', $this->seenKeys)->delete();
    }

    private function tripHeadline(?MobilizationRequest $request): string
    {
        if (! $request) {
            return 'Viaje institucional';
        }

        $when = optional($request->departure_date)?->format('d/m/Y');

        return trim($request->origin.' → '.$request->destination.($when ? ' · '.$when : ''));
    }

    /**
     * @return array<string, mixed>
     */
    private function requestDetail(?MobilizationRequest $request, string $action): array
    {
        if (! $request) {
            return ['accion' => $action];
        }

        return array_filter([
            'origen' => $request->origin,
            'destino' => $request->destination,
            'salida' => trim((optional($request->departure_date)?->format('d/m/Y') ?: '').' '.($request->departure_time ?: '')),
            'retorno' => trim((optional($request->return_date)?->format('d/m/Y') ?: '').' '.($request->return_time ?: '')),
            'solicitante' => trim(($request->requester?->first_name ?? '').' '.($request->requester?->last_name ?? '')),
            'motivo' => $request->travel_reason,
            'estado' => $request->status,
            'accion' => $action,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sheetDetail(RouteSheet $sheet, string $action): array
    {
        $base = $this->requestDetail($sheet->request, $action);
        $base['vehiculo'] = $sheet->vehicle?->plate;
        $base['conductor'] = trim(($sheet->driver?->user?->first_name ?? '').' '.($sheet->driver?->user?->last_name ?? ''));
        $base['hoja_ruta'] = '#'.$sheet->id;

        return array_filter($base);
    }
}
