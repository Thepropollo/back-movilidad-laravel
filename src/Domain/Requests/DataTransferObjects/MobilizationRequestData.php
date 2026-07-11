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
        public string $travel_reason,
        public string $departure_date,
        public string $return_date,
        public int $estimated_days,
        public float $projected_cost,
        public string $status = 'pendiente'
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
            travel_reason: $request->input('travel_reason'),
            departure_date: $request->input('departure_date'),
            return_date: $request->input('return_date'),
            estimated_days: $estimatedDays,
            projected_cost: $projectedCost,
            status: $request->input('mobilization_type') === 'externa' ? 'pendiente_rectorado' : 'pendiente'
        );
    }
}
