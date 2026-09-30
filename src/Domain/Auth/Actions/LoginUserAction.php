<?php

namespace Domain\Auth\Actions;

use Domain\Auth\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    public function execute(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! $user->is_active || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        if ($user->role_id && $user->roles()->count() === 0) {
            $user->roles()->syncWithoutDetaching([$user->role_id]);
        }

        return [
            'user' => $user->toAuthArray(),
            'access_token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}
