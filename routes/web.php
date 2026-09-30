<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/docs', function () {
    return redirect('/scalar');
});

Route::get('/openapi.json', function () {
    return response(file_get_contents(storage_path('api-docs/openapi.json')), 200, [
        'Content-Type' => 'application/json',
    ]);
});

Route::get('/openapi.yaml', function () {
    return response(file_get_contents(base_path('openapi.yaml')), 200, [
        'Content-Type' => 'application/x-yaml',
    ]);
});
