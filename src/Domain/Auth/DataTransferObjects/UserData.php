<?php

namespace Domain\Auth\DataTransferObjects;

use Illuminate\Http\Request;

class UserData
{
    public function __construct(
        public string $national_id,
        public string $first_name,
        public string $last_name,
        public string $email,
        public ?string $password,
        public string $faculty_institution,
        public ?int $role_id = null,
        public ?string $role_name = null
    ) {}

    /**
     * Crear un DTO a partir de una petición HTTP.
     */
    public static function fromRequest(Request $request): self
    {
        return new self(
            national_id: $request->input('national_id'),
            first_name: $request->input('first_name'),
            last_name: $request->input('last_name'),
            email: $request->input('email'),
            password: $request->input('password'),
            faculty_institution: $request->input('faculty_institution'),
            role_id: $request->input('role_id') ? (int) $request->input('role_id') : null,
            role_name: $request->input('role_name')
        );
    }
}
