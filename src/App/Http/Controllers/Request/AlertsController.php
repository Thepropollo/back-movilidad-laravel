<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Domain\Alerts\Actions\GenerateAlertsAction;
use Domain\Alerts\Models\Alert;
use Domain\Auth\Support\RoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertsController extends Controller
{
    public function index(Request $request, GenerateAlertsAction $generate): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $generate->execute();

        $audiences = $this->audiencesFor($user->roleNames());

        $alerts = Alert::query()
            ->where('created_at', '>=', now()->subDays(GenerateAlertsAction::ALERT_LIFETIME_DAYS))
            ->where(function ($query) use ($user, $audiences) {
                $query->where('user_id', $user->id)
                    ->orWhere(function ($open) use ($audiences) {
                        $open->whereNull('user_id')->whereIn('audience_role', $audiences);
                    });
            })
            ->orderByRaw("case severity when 'alta' then 0 when 'media' then 1 else 2 end")
            ->orderByDesc('created_at')
            ->get();

        $readIds = $alerts->isEmpty()
            ? []
            : DB::table('alert_reads')
                ->where('user_id', $user->id)
                ->whereIn('alert_id', $alerts->pluck('id'))
                ->pluck('alert_id')
                ->all();

        $readSet = array_flip(array_map('intval', $readIds));

        $payload = $alerts->map(fn (Alert $alert) => [
            'id' => $alert->id,
            'type' => $alert->type,
            'severity' => $alert->severity,
            'title' => $alert->title,
            'message' => $alert->message,
            'detail' => $alert->detail ?: [],
            'route' => $alert->route,
            'created_at' => $alert->created_at?->toIso8601String(),
            'read' => isset($readSet[$alert->id]),
        ])->values();

        $unread = $payload->where('read', false);
        $importantUnread = $unread->where('severity', 'alta');

        return response()->json([
            'alerts' => $payload,
            'unread_count' => $unread->count(),
            'important_unread_count' => $importantUnread->count(),
        ]);
    }

    public function markRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autorizado.'], 401);
        }

        $alert = Alert::find($id);

        if (! $alert) {
            return response()->json(['message' => 'Alerta no encontrada.'], 404);
        }

        $alert->readers()->syncWithoutDetaching([$user->id => ['read_at' => now()]]);

        return response()->json(['message' => 'Alerta marcada como leída.']);
    }

    /**
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function audiencesFor(array $roles): array
    {
        $canonical = RoleCatalog::canonicalizeMany($roles);
        $audiences = [];
        if (in_array('secretaria', $canonical, true)) {
            $audiences[] = 'secretaria';
            $audiences[] = 'operacion';
        }
        if (in_array('mecanico', $canonical, true)) {
            $audiences[] = 'mecanico';
            $audiences[] = 'operacion';
        }
        if (in_array('conductor', $canonical, true)) {
            $audiences[] = 'conductor';
        }
        if (in_array('docente', $canonical, true) || in_array('responsable_facultad', $canonical, true)) {
            $audiences[] = 'docente';
        }
        if (in_array('vicerrector', $canonical, true)) {
            $audiences[] = 'vicerrector';
        }
        if (in_array('estudiante', $canonical, true)) {
            $audiences[] = 'estudiante';
        }

        return array_values(array_unique($audiences));
    }
}
