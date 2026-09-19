<?php

namespace Domain\Requests\DataTransferObjects;

use Illuminate\Http\Request;

class MobilizationRequestData
{
    public function __construct(
        public int $requester_id,
        public string $mobilization_type,
        public string $origin,
        public string $destination,
        public ?string $destination_address,
        public ?float $destination_latitude,
        public ?float $destination_longitude,
        public string $travel_reason,
        public string $departure_date,
        public ?string $departure_time,
        public string $return_date,
        public ?string $return_time,
        public int $estimated_days,
        public float $projected_cost,
        public string $status = 'pendiente',
        public int $occupant_count = 1,
        public ?string $communication_number = null,
        public ?string $activity_type = null,
        public ?string $academic_program = null,
        public int $public_servants_count = 0,
    ) {}

    /**
     * Crear el DTO a partir de la petición HTTP, inyectando días y costos calculados en la aplicación.
     */
    public static function fromRequest(Request $request, int $requesterId, int $estimatedDays, float $projectedCost): self
    {
        return new self(
            requester_id: $requesterId,
            mobilization_type: $request->input('mobilization_type'),
            origin: $request->input('origin', 'MANTA'),
            destination: $request->input('destination'),
            destination_address: $request->input('destination_address'),
            destination_latitude: $request->input('destination_latitude'),
            destination_longitude: $request->input('destination_longitude'),
            travel_reason: $request->input('travel_reason'),
            departure_date: $request->input('departure_date'),
            departure_time: $request->input('departure_time'),
            return_date: $request->input('return_date'),
            return_time: $request->input('return_time'),
            estimated_days: $estimatedDays,
            projected_cost: $projectedCost,
            status: 'pendiente_secretaria',
            occupant_count: (int) $request->input('occupant_count', 1),
            communication_number: $request->input('communication_number'),
            activity_type: $request->input('activity_type'),
            academic_program: $request->input('academic_program'),
            public_servants_count: (int) $request->input('public_servants_count', 0),
        );
    }
}
