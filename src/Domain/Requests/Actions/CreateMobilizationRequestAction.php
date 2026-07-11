<?php

namespace Domain\Requests\Actions;

use Domain\Requests\Models\MobilizationRequest;
use Domain\Requests\DataTransferObjects\MobilizationRequestData;

class CreateMobilizationRequestAction
{
    /**
     * Ejecuta el almacenamiento de la solicitud de movilización.
     */
    public function execute(MobilizationRequestData $data): MobilizationRequest
    {
        return MobilizationRequest::create([
            'requester_id' => $data->requester_id,
            'mobilization_type' => $data->mobilization_type,
            'origin' => $data->origin,
            'destination' => $data->destination,
            'travel_reason' => $data->travel_reason,
            'departure_date' => $data->departure_date,
            'return_date' => $data->return_date,
            'estimated_days' => $data->estimated_days,
            'projected_cost' => $data->projected_cost,
            'status' => $data->status
        ]);
    }
}
