<?php

namespace Domain\Requests\Actions;

use Domain\Requests\DataTransferObjects\MobilizationRequestData;
use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\Support\RequestWorkflow;

class CreateMobilizationRequestAction
{
    public function execute(MobilizationRequestData $data): MobilizationRequest
    {
        $request = MobilizationRequest::create([
            'requester_id' => $data->requester_id,
            'mobilization_type' => $data->mobilization_type,
            'origin' => $data->origin,
            'destination' => $data->destination,
            'destination_address' => $data->destination_address,
            'destination_latitude' => $data->destination_latitude,
            'destination_longitude' => $data->destination_longitude,
            'travel_reason' => $data->travel_reason,
            'departure_date' => $data->departure_date,
            'departure_time' => $data->departure_time,
            'return_date' => $data->return_date,
            'return_time' => $data->return_time,
            'estimated_days' => $data->estimated_days,
            'projected_cost' => $data->projected_cost,
            'status' => $data->status,
            'confirmation_deadline' => now()->addDays(2),
            'occupant_count' => $data->occupant_count,
            'communication_number' => $data->communication_number,
            'activity_type' => $data->activity_type,
            'academic_program' => $data->academic_program,
            'public_servants_count' => $data->public_servants_count,
        ]);

        RequestWorkflow::record(
            $request,
            $request->status,
            'SOLICITUD_CREADA',
            $data->requester_id,
            'Solicitud registrada por el docente.',
            null
        );

        return $request;
    }
}
