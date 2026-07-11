<?php

namespace App\Http\Controllers\Request;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Domain\Auth\Models\Driver;
use Carbon\Carbon;

class DriverListController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        if (!$user || !$user->role || $user->role->name !== 'jefe_transporte') {
            return response()->json(['message' => 'Acceso denegado.'], 403);
        }

        $drivers = Driver::with(['user', 'licenses'])->get()->map(function ($driver) {
            $activeLicense = $driver->licenses()
                ->where('current_points', '>', 0)
                ->where('expiration_date', '>=', Carbon::today())
                ->first();

            $isSelectable = $driver->is_available && $activeLicense !== null;

            $statusDetails = 'Disponible';
            $statusLabel = 'available';

            if (!$driver->is_available) {
                $statusDetails = 'En viaje / No disponible';
                $statusLabel = 'on_trip';
            } elseif (!$activeLicense) {
                $latestLicense = $driver->licenses()->first();
                if ($latestLicense) {
                    if ($latestLicense->current_points <= 0) {
                        $statusDetails = 'Inhabilitado: Licencia sin puntos';
                        $statusLabel = 'no_points';
                    } elseif (Carbon::parse($latestLicense->expiration_date)->isPast()) {
                        $statusDetails = 'Inhabilitado: Licencia caducada';
                        $statusLabel = 'expired_license';
                    } else {
                        $statusDetails = 'Inhabilitado: Sin licencia registrada';
                        $statusLabel = 'no_license';
                    }
                } else {
                    $statusDetails = 'Inhabilitado: Sin licencia registrada';
                    $statusLabel = 'no_license';
                }
            }

            return [
                'id' => $driver->id,
                'name' => $driver->user ? ($driver->user->first_name . ' ' . $driver->user->last_name) : 'Chofer sin nombre',
                'national_id' => $driver->user ? $driver->user->national_id : '',
                'contract_type' => $driver->contract_type,
                'is_available' => $driver->is_available,
                'license_type' => $activeLicense ? $activeLicense->license_type : ($driver->licenses()->first() ? $driver->licenses()->first()->license_type : 'N/A'),
                'points' => $activeLicense ? $activeLicense->current_points : ($driver->licenses()->first() ? $driver->licenses()->first()->current_points : 0),
                'expiration_date' => $activeLicense ? $activeLicense->expiration_date : ($driver->licenses()->first() ? $driver->licenses()->first()->expiration_date : 'N/A'),
                'status_label' => $statusLabel,
                'status_details' => $statusDetails,
                'is_selectable' => $isSelectable
            ];
        });

        return response()->json($drivers);
    }
}
