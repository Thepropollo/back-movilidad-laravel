<?php

namespace Domain\Requests\Support;

use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Models\RequestStatusHistory;

final class RequestWorkflow
{
    /**
     * Línea de fases del trámite (aprobación, asignación, viaje y cierre).
     *
     * @return list<array{key: string, label: string, short: string, state: string}>
     */
    public static function phases(MobilizationRequest $request): array
    {
        $status = (string) $request->status;
        $externa = $request->mobilization_type === 'externa';
        $sheet = $request->relationLoaded('routeSheet')
            ? $request->routeSheet
            : $request->routeSheet()->first();
        $driver = $sheet?->driver_response;
        $trip = $sheet?->trip_status;
        $rejected = $status === 'rechazada';
        $rejectedAtVic = $rejected && (bool) $request->rectorate_approver_id;
        $rejectedAtSec = $rejected && ! $rejectedAtVic;

        $secretariaDone = in_array($status, [
            'pendiente_rectorado',
            'autorizada_secretaria',
            'aprobado_rectorado',
            'aprobada',
            'en_ruta',
            'finalizada',
        ], true) || $rejectedAtVic;

        $vicDone = in_array($status, ['aprobado_rectorado', 'aprobada', 'en_ruta', 'finalizada'], true);
        $readyToAssign = in_array($status, ['autorizada_secretaria', 'aprobado_rectorado', 'aprobada'], true);
        $tripClosed = in_array($trip, ['finalizado', 'pendiente_feedback'], true) || $status === 'finalizada';
        $onRoute = $trip === 'en_ruta';

        $phases = [
            self::phase('creada', 'Solicitud registrada', 'Solicitud', 'done'),
            self::phase(
                'secretaria',
                'Autorización de Secretaría',
                'Secretaría',
                $rejectedAtSec ? 'blocked' : ($secretariaDone ? 'done' : ($status === 'pendiente_secretaria' ? 'current' : 'pending'))
            ),
        ];

        if ($externa) {
            $phases[] = self::phase(
                'vicerrectorado',
                'Aprobación de Vicerrectorado',
                'Vicerrectorado',
                $rejectedAtVic ? 'blocked' : ($vicDone ? 'done' : ($status === 'pendiente_rectorado' ? 'current' : 'pending'))
            );
        }

        $phases[] = self::phase(
            'asignacion',
            'Asignación de vehículo y conductor',
            'Asignar',
            $sheet ? 'done' : ($readyToAssign && ! $rejected ? 'current' : 'pending')
        );
        $phases[] = self::phase(
            'conductor',
            'Aceptación del conductor',
            'Conductor',
            $driver === 'rechazado' ? 'blocked' : ($driver === 'aceptado' ? 'done' : ($driver === 'pendiente' ? 'current' : 'pending'))
        );
        $phases[] = self::phase(
            'salida',
            'Salida / en ruta',
            'Salida',
            ($onRoute || $tripClosed) ? 'done' : ($driver === 'aceptado' && $trip === 'programado' ? 'current' : 'pending')
        );
        $phases[] = self::phase(
            'cierre',
            'Llegada y cierre',
            'Cierre',
            in_array($trip, ['finalizado'], true) || $status === 'finalizada' ? 'done' : ($onRoute || $trip === 'pendiente_feedback' ? 'current' : 'pending')
        );

        return $phases;
    }

    /**
     * @return array<string, mixed>
     */
    public static function card(MobilizationRequest $request, string $flujoHref): array
    {
        $sep = str_contains($flujoHref, '?') ? '&' : '?';

        return [
            'id' => $request->id,
            'label' => '#'.$request->id.' · '.$request->destination,
            'meta' => $request->status,
            'href' => $flujoHref.$sep.'id='.$request->id,
            'status' => $request->status,
            'type' => $request->mobilization_type,
            'phases' => self::phases($request),
        ];
    }

    /**
     * @return array{key: string, label: string, short: string, state: string}
     */
    private static function phase(string $key, string $label, string $short, string $state): array
    {
        return compact('key', 'label', 'short', 'state');
    }

    public static function record(
        MobilizationRequest $request,
        string $toStatus,
        string $action,
        ?int $userId = null,
        ?string $observation = null,
        ?string $fromStatus = null
    ): void {
        RequestStatusHistory::create([
            'request_id' => $request->id,
            'user_id' => $userId,
            'from_status' => $fromStatus ?? $request->status,
            'to_status' => $toStatus,
            'action' => $action,
            'observation' => $observation,
        ]);
    }
}
