<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Request\SolicitudStoreController;
use App\Http\Controllers\Request\SolicitudListController;
use App\Http\Controllers\Request\AprobarRectoradoController;
use App\Http\Controllers\Request\RouteSheetStoreController;
use App\Http\Controllers\Request\VehicleListController;
use App\Http\Controllers\Request\DriverListController;

Route::post('/register', RegisterController::class);
Route::post('/login', LoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);
    Route::get('/me', MeController::class);

    // Proceso 1: Solicitud y Aprobación de Movilización
    Route::post('/solicitudes', SolicitudStoreController::class);
    Route::get('/solicitudes', SolicitudListController::class);
    Route::patch('/solicitudes/{id}/aprobar-rectorado', AprobarRectoradoController::class);
    Route::post('/hojas-ruta', RouteSheetStoreController::class);

    // Proceso 2: Despacho, Inspección en Patio y Filtro del Taller
    Route::post('/actas-entrega', \App\Http\Controllers\Request\DeliveryReceptionActStoreController::class);
    Route::get('/inspecciones/pendientes', \App\Http\Controllers\Request\PendingRouteSheetsController::class);
    Route::get('/inspecciones/componentes', \App\Http\Controllers\Request\ChecklistComponentsController::class);
    Route::get('/novedades', \App\Http\Controllers\Workshop\IssueLogListController::class);
    Route::post('/ordenes-taller', \App\Http\Controllers\Workshop\CreateWorkOrderController::class);
    Route::patch('/ordenes-taller/{id}/cerrar', \App\Http\Controllers\Workshop\CloseWorkOrderController::class);
    Route::get('/insumos', \App\Http\Controllers\Workshop\SupplyInventoryListController::class);
    Route::get('/mecanicos', \App\Http\Controllers\Workshop\MechanicListController::class);

    // Proceso 3: Abastecimiento de Combustible a Escala Real
    Route::post('/ordenes-combustible', \App\Http\Controllers\Request\FuelOrderStoreController::class);
    Route::patch('/ordenes-combustible/{codigo_orden}/despachar', \App\Http\Controllers\Request\FuelOrderDespacharController::class);
    Route::get('/ordenes-combustible/{codigo_orden}', \App\Http\Controllers\Request\FuelOrderShowController::class);
    Route::get('/estaciones-servicio', \App\Http\Controllers\Request\ServiceStationListController::class);
    Route::get('/mis-ordenes-combustible', \App\Http\Controllers\Request\DriverFuelOrdersController::class);

    // Proceso 4: Retorno, Co-Evaluación Universitaria y Liquidación (Post-Viaje)
    Route::post('/actas-recepcion-llegada', \App\Http\Controllers\Request\DeliveryReceptionActArrivalController::class);
    Route::post('/evaluaciones', \App\Http\Controllers\Request\TripEvaluationStoreController::class);
    Route::get('/mis-evaluaciones-pendientes', \App\Http\Controllers\Request\PendingEvaluationsController::class);
    Route::get('/mis-comisiones-pendientes-liquidar', \App\Http\Controllers\Request\TeacherRouteSheetsController::class);
    Route::get('/compensaciones/pendientes', \App\Http\Controllers\Request\DriverCompensationListController::class);
    Route::get('/compensaciones/{hoja_ruta_id}/calcular', \App\Http\Controllers\Request\DriverCompensationCalculateController::class);
    Route::post('/compensaciones/{hoja_ruta_id}/liquidar', \App\Http\Controllers\Request\DriverCompensationLiquidarController::class);
    Route::post('/compensaciones/{hoja_ruta_id}/aprobar', \App\Http\Controllers\Request\DriverCompensationAprobarController::class);

    // Auxiliares para asignación de recursos
    Route::get('/vehicles', VehicleListController::class);
    Route::get('/drivers', DriverListController::class);
});
