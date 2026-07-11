<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Domain\Auth\Actions\RegisterUserAction;
use Domain\Auth\DataTransferObjects\UserData;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class RegisterController extends Controller
{
    /**
     * Controlador invocable para registrar un nuevo usuario.
     */
    public function __invoke(Request $request, RegisterUserAction $action): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'national_id' => 'required|string|max:10|unique:users,national_id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|string|email|max:100|unique:users,email',
            'password' => 'required|string|min:6',
            'faculty_institution' => 'required|string|max:150',
            'role_id' => 'nullable|exists:roles,id',
            'role_name' => 'nullable|string|exists:roles,name'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $dto = UserData::fromRequest($request);
        $user = $action->execute($dto);
        
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'message' => 'Usuario registrado exitosamente',
            'data' => [
                'user' => $user->load('role'),
                'access_token' => $token,
                'token_type' => 'Bearer'
            ]
        ], 201);
    }
}
