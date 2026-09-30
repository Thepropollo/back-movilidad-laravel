<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Domain\Auth\Actions\RegisterUserAction;
use Domain\Auth\DataTransferObjects\UserData;
use Domain\Auth\Support\RoleCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    public function __invoke(Request $request, RegisterUserAction $action): JsonResponse
    {
        $allowRegister = filter_var(
            env('ALLOW_PUBLIC_REGISTER', ! app()->environment('production')),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $allowRegister) {
            return response()->json([
                'status' => 'error',
                'message' => 'El registro público está deshabilitado. Use el acceso institucional o solicite alta a Secretaría.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'national_id' => 'required|string|max:10|unique:users,national_id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:100|unique:users,email',
            'password' => ['required', 'string', Password::min(12)->letters()->numbers()],
            'faculty_institution' => 'required|string|max:150',
            'id' => 'prohibited',
            'role_id' => 'prohibited',
            'role_name' => 'prohibited',
            'roles' => 'prohibited',
            'is_active' => 'prohibited',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $validator->errors(),
            ], 422);
        }

        $payload = $request->all();
        $payload['role_name'] = RoleCatalog::publicRegistrationRole();

        $dto = UserData::fromRequest(new Request($payload));
        $user = $action->execute($dto);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Usuario registrado exitosamente',
            'data' => [
                'user' => $user->toAuthArray(),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 201);
    }
}
