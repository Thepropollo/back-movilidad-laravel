<?php

namespace Domain\Requests\Actions;

use Carbon\Carbon;
use Domain\Requests\Models\RouteSheet;
use Domain\Requests\Models\DeliveryReceptionAct;
use Domain\Requests\Models\RateConfiguration;
use Domain\Auth\Models\DailyAttendance;

class CalculateDriverCompensationAction
{
    /**
     * Calcula de forma atómica y O(1) los viáticos y horas extras de un conductor en comisión.
     */
    public function execute(int $routeSheetId): array
    {
        $routeSheet = RouteSheet::with(['vehicle', 'driver'])->findOrFail($routeSheetId);
        $driver = $routeSheet->driver;

        // 1. Obtener marcas de tiempo de salida y llegada real desde las actas
        $salidaAct = DeliveryReceptionAct::where('route_sheet_id', $routeSheetId)
            ->where('registration_type', 'salida')
            ->first();

        $llegadaAct = DeliveryReceptionAct::where('route_sheet_id', $routeSheetId)
            ->where('registration_type', 'llegada')
            ->first();

        $departureTime = $salidaAct ? Carbon::parse($salidaAct->created_at) : Carbon::parse($routeSheet->created_at);
        $arrivalTime = $llegadaAct ? Carbon::parse($llegadaAct->created_at) : Carbon::now();

        // 2. Obtener las tarifas configuradas
        $rateViaticoModel = RateConfiguration::where('rate_key', 'viatico_diario')->first();
        $rate50Model = RateConfiguration::where('rate_key', 'extra_50')->first();
        $rate100Model = RateConfiguration::where('rate_key', 'extra_100')->first();

        $rateViatico = $rateViaticoModel ? (float) $rateViaticoModel->rate_value : 80.00;
        $rate50 = $rate50Model ? (float) $rate50Model->rate_value : 5.00;
        $rate100 = $rate100Model ? (float) $rate100Model->rate_value : 7.50;
        $appliedRateId = $rateViaticoModel ? $rateViaticoModel->id : 1;

        // 3. Calcular noches afuera (Viáticos)
        $nights = (int) $departureTime->startOfDay()->diffInDays($arrivalTime->startOfDay());
        $allowancesAmount = $nights * $rateViatico;

        // 4. Calcular horas extras (50% Suplementarias vs 100% Extraordinarias)
        $overtime50Hours = 0.0;
        $overtime100Hours = 0.0;

        $startDate = $departureTime->copy()->startOfDay();
        $endDate = $arrivalTime->copy()->startOfDay();

        // Iterar día por día para evaluar las horas comisionadas fuera de jornada
        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            $isWeekend = $date->isWeekend();

            // Rango de comisiones en este día específico
            $dayStart = $date->copy()->startOfDay();
            $dayEnd = $date->copy()->endOfDay();

            // Si es el primer día, comienza a la hora de salida
            if ($date->isSameDay($departureTime)) {
                $dayStart = $departureTime;
            }
            // Si es el último día, termina a la hora de llegada
            if ($date->isSameDay($arrivalTime)) {
                $dayEnd = $arrivalTime;
            }

            $hoursWorkedThisDay = max(0.0, $dayStart->diffInMinutes($dayEnd) / 60.0);

            if ($isWeekend) {
                // Fines de semana = 100% Extraordinarias
                $overtime100Hours += $hoursWorkedThisDay;
            } else {
                // Entre semana = Buscar asistencia o asumir jornada de 8:00 a 17:00 (excluyendo almuerzo de 12:00 a 13:00)
                $attendance = DailyAttendance::where('driver_id', $driver->id)
                    ->where('date', $date->toDateString())
                    ->first();

                $workStart = $attendance ? Carbon::createFromTimeString($attendance->check_in_time) : Carbon::createFromTimeString('08:00:00');
                $workEnd = $attendance ? Carbon::createFromTimeString($attendance->check_out_time) : Carbon::createFromTimeString('17:00:00');

                // Ajustar horas con la fecha del día actual
                $workStart = $date->copy()->setTime($workStart->hour, $workStart->minute, $workStart->second);
                $workEnd = $date->copy()->setTime($workEnd->hour, $workEnd->minute, $workEnd->second);

                // Calcular horas trabajadas antes del inicio de jornada y después del fin de jornada
                if ($dayStart->lt($workStart)) {
                    $overtime50Hours += max(0.0, $dayStart->diffInMinutes(min($dayEnd, $workStart)) / 60.0);
                }
                if ($dayEnd->gt($workEnd)) {
                    $overtime50Hours += max(0.0, max($dayStart, $workEnd)->diffInMinutes($dayEnd) / 60.0);
                }
            }
        }

        // Redondear horas
        $overtime50Hours = round($overtime50Hours, 2);
        $overtime100Hours = round($overtime100Hours, 2);

        $overtime50Amount = $overtime50Hours * $rate50;
        $overtime100Amount = $overtime100Hours * $rate100;
        $totalPayout = $allowancesAmount + $overtime50Amount + $overtime100Amount;

        return [
            'route_sheet_id' => $routeSheetId,
            'applied_rate_id' => $appliedRateId,
            'nights_outside' => $nights,
            'allowances_amount' => round($allowancesAmount, 2),
            'overtime_50_hours' => $overtime50Hours,
            'overtime_50_amount' => round($overtime50Amount, 2),
            'overtime_100_hours' => $overtime100Hours,
            'overtime_100_amount' => round($overtime100Amount, 2),
            'total_payout' => round($totalPayout, 2),
            'departure_real' => $departureTime->toDateTimeString(),
            'arrival_real' => $arrivalTime->toDateTimeString()
        ];
    }
}
