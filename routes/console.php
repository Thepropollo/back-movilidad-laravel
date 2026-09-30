<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Domain\Auth\Actions\CreateInitialSecretariatUserAction;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:bootstrap-secretaria', function (CreateInitialSecretariatUserAction $action) {
    $data = [
        'national_id' => trim((string) $this->ask('Cédula de la persona responsable')),
        'first_name' => trim((string) $this->ask('Nombres')),
        'last_name' => trim((string) $this->ask('Apellidos')),
        'email' => trim((string) $this->ask('Correo institucional')),
        'faculty_institution' => trim((string) $this->ask('Unidad institucional')),
    ];

    $password = (string) $this->secret('Contraseña inicial (no se mostrará)');
    $passwordConfirmation = (string) $this->secret('Repita la contraseña');
    $data['password'] = $password;

    $validator = Validator::make($data + ['password_confirmation' => $passwordConfirmation], [
        'national_id' => ['required', 'digits:10', 'unique:users,national_id'],
        'first_name' => ['required', 'string', 'max:100'],
        'last_name' => ['required', 'string', 'max:100'],
        'email' => ['required', 'email', 'max:100', 'unique:users,email'],
        'faculty_institution' => ['required', 'string', 'max:150'],
        'password' => ['required', 'string', Password::min(12)->letters()->numbers(), 'same:password_confirmation'],
    ]);

    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return Command::FAILURE;
    }

    try {
        $action->execute($data);
    } catch (Throwable) {
        $this->error('No se pudo crear la cuenta inicial. Verifique que las migraciones estén aplicadas y que no exista ya una cuenta de Secretaría.');

        return Command::FAILURE;
    }

    $this->info('Se creó la cuenta inicial de Secretaría. Guarde la contraseña en un gestor seguro.');

    return Command::SUCCESS;
})->purpose('Crear de forma interactiva la primera cuenta privilegiada de Secretaría');
