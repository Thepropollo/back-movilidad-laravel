<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Request\AdminDriverController;
use App\Http\Controllers\Request\AdminFacultyReportController;
use App\Http\Controllers\Request\AdminKpiController;
use App\Http\Controllers\Request\AdminRoleController;
use App\Http\Controllers\Request\AdminServiceStationController;
use App\Http\Controllers\Request\AdminUserController;
use App\Http\Controllers\Request\AdminVehicleController;
use App\Http\Controllers\Request\AgendaController;
use App\Http\Controllers\Request\AlertsController;
use App\Http\Controllers\Request\AprobarRectoradoController;
use App\Http\Controllers\Request\AutorizarSecretariaController;
use App\Http\Controllers\Request\ChecklistComponentsController;
use App\Http\Controllers\Request\DashboardMetricsController;
use App\Http\Controllers\Request\DeliveryReceptionActArrivalController;
use App\Http\Controllers\Request\DeliveryReceptionActStoreController;
use App\Http\Controllers\Request\DriverCompensationAprobarController;
use App\Http\Controllers\Request\DriverCompensationCalculateController;
use App\Http\Controllers\Request\DriverCompensationLiquidarController;
use App\Http\Controllers\Request\DriverCompensationListController;
use App\Http\Controllers\Request\DriverCompensationMineController;
use App\Http\Controllers\Request\DriverFuelOrdersController;
use App\Http\Controllers\Request\DriverListController;
use App\Http\Controllers\Request\DriverRespondController;
use App\Http\Controllers\Request\DriverTripsController;
use App\Http\Controllers\Request\FleetManageController;
use App\Http\Controllers\Request\FuelOrderDespacharController;
use App\Http\Controllers\Request\FuelOrderShowController;
use App\Http\Controllers\Request\FuelOrderStoreController;
use App\Http\Controllers\Request\InstitutionalDocumentController;
use App\Http\Controllers\Request\MyVehicleController;
use App\Http\Controllers\Request\ParticipantController;
use App\Http\Controllers\Request\PendingEvaluationsController;
use App\Http\Controllers\Request\PendingRouteSheetsController;
use App\Http\Controllers\Request\RateConfigurationListController;
use App\Http\Controllers\Request\RateConfigurationStoreController;
use App\Http\Controllers\Request\RateConfigurationUpdateController;
use App\Http\Controllers\Request\ReassignRouteSheetController;
use App\Http\Controllers\Request\ReportsController;
use App\Http\Controllers\Request\RouteMapController;
use App\Http\Controllers\Request\RouteSheetStopController;
use App\Http\Controllers\Request\RouteSheetStoreController;
use App\Http\Controllers\Request\ServiceStationListController;
use App\Http\Controllers\Request\ServiceStationManageController;
use App\Http\Controllers\Request\ServiceStationToggleController;
use App\Http\Controllers\Request\SolicitudFlujoController;
use App\Http\Controllers\Request\SolicitudListController;
use App\Http\Controllers\Request\SolicitudStoreController;
use App\Http\Controllers\Request\SystemLogListController;
use App\Http\Controllers\Request\TeacherRouteSheetsController;
use App\Http\Controllers\Request\TripEvaluationStoreController;
use App\Http\Controllers\Request\VehicleListController;
use App\Http\Controllers\Workshop\CloseWorkOrderController;
use App\Http\Controllers\Workshop\CreateWorkOrderController;
use App\Http\Controllers\Workshop\IssueLogListController;
use App\Http\Controllers\Workshop\IssueLogStoreController;
use App\Http\Controllers\Workshop\MechanicListController;
use App\Http\Controllers\Workshop\MechanicWorkOrdersController;
use App\Http\Controllers\Workshop\SupplyInventoryListController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login', LoginController::class);
    Route::post('/register', RegisterController::class);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);
    Route::get('/me', MeController::class);

    // Módulo Docentes / Solicitantes
    Route::middleware('role:docente,solicitante,responsable_facultad')->group(function () {
        Route::post('/solicitudes', SolicitudStoreController::class);
        Route::post('/solicitudes/{id}/participantes', [ParticipantController::class, 'store']);
        Route::get('/estudiantes', [ParticipantController::class, 'searchStudents']);
        Route::get('/mis-comisiones-pendientes-liquidar', TeacherRouteSheetsController::class);
    });

    // Rutas transversales de solicitudes
    Route::get('/solicitudes', SolicitudListController::class);
    Route::get('/solicitudes/{id}/flujo', SolicitudFlujoController::class);
    Route::get('/solicitudes/{id}/participantes', [ParticipantController::class, 'index']);

    Route::get('/documentos/catalogo', [InstitutionalDocumentController::class, 'catalog']);
    Route::get('/documentos', [InstitutionalDocumentController::class, 'index']);
    Route::post('/documentos/generar', [InstitutionalDocumentController::class, 'generate']);
    Route::post('/documentos/adjuntar', [InstitutionalDocumentController::class, 'upload']);
    Route::post('/documentos/{id}/firmar', [InstitutionalDocumentController::class, 'sign']);
    Route::get('/documentos/{id}/verificar', [InstitutionalDocumentController::class, 'verify']);
    Route::get('/documentos/{id}/archivo', [InstitutionalDocumentController::class, 'download']);

    // Módulo Secretaría y Jefatura de Transporte
    Route::middleware('role:secretaria,jefe_transporte')->group(function () {
        Route::patch('/solicitudes/{id}/autorizar-secretaria', AutorizarSecretariaController::class);
        Route::post('/hojas-ruta', RouteSheetStoreController::class);
        Route::patch('/hojas-ruta/{id}/reasignar', ReassignRouteSheetController::class);
        Route::get('/agenda', AgendaController::class);
        Route::post('/drivers', [FleetManageController::class, 'storeDriver']);
        Route::patch('/drivers/{id}', [FleetManageController::class, 'updateDriver']);
        Route::post('/vehicles', [FleetManageController::class, 'storeVehicle']);
        Route::patch('/vehicles/{id}', [FleetManageController::class, 'updateVehicle']);
        Route::patch('/vehicles/{id}/documentos', [FleetManageController::class, 'updateVehicleDocuments']);
        Route::post('/estaciones-servicio', [ServiceStationManageController::class, 'store']);
        Route::patch('/estaciones-servicio/{id}', [ServiceStationManageController::class, 'update']);
        Route::get('/vehicles', VehicleListController::class);
        Route::get('/drivers', DriverListController::class);
        Route::get('/compensaciones/pendientes', DriverCompensationListController::class);
        Route::get('/compensaciones/{hoja_ruta_id}/calcular', DriverCompensationCalculateController::class);
        Route::post('/compensaciones/{hoja_ruta_id}/liquidar', DriverCompensationLiquidarController::class);
        Route::post('/compensaciones/{hoja_ruta_id}/aprobar', DriverCompensationAprobarController::class);
        Route::post('/ordenes-combustible', FuelOrderStoreController::class);
        Route::patch('/ordenes-combustible/{codigo_orden}/despachar', FuelOrderDespacharController::class);
        Route::get('/ordenes-combustible/{codigo_orden}', FuelOrderShowController::class);
        Route::get('/reportes/flota', [ReportsController::class, 'flota']);
    });

    // Autoridad Superior
    Route::middleware('role:vicerrector,rector')->group(function () {
        Route::patch('/solicitudes/{id}/aprobar-rectorado', AprobarRectoradoController::class);
    });

    // Reportes compartidos
    Route::middleware('role:secretaria,jefe_transporte,vicerrector,rector,responsable_facultad,docente,solicitante')->group(function () {
        Route::get('/reportes/solicitudes', [ReportsController::class, 'solicitudes']);
        Route::get('/reportes/viajes', [ReportsController::class, 'viajes']);
        Route::get('/reportes/mensual', [ReportsController::class, 'mensual']);
    });

    // Módulo Estudiantes / Pasajeros
    Route::middleware('role:estudiante,pasajero')->group(function () {
        Route::get('/mis-invitaciones', [ParticipantController::class, 'myInvitations']);
        Route::patch('/participantes/{id}/responder', [ParticipantController::class, 'respond']);
    });

    // Módulo Conductores
    Route::middleware('role:conductor,chofer')->group(function () {
        Route::patch('/hojas-ruta/{id}/responder', DriverRespondController::class);
        Route::get('/mi-vehiculo', MyVehicleController::class);
        Route::get('/mis-compensaciones', [DriverCompensationMineController::class, 'index']);
        Route::patch('/compensaciones/{id}/confirmar', [DriverCompensationMineController::class, 'confirm']);
        Route::get('/mis-ordenes-combustible', DriverFuelOrdersController::class);
        Route::post('/novedades', IssueLogStoreController::class);
    });

    // Viajes: el conductor ve los suyos; secretaría ve todos (para reasignación)
    Route::middleware('role:conductor,chofer,secretaria,jefe_transporte')->group(function () {
        Route::get('/mis-viajes', DriverTripsController::class);
    });

    // Módulo Taller y Mantenimiento
    Route::middleware('role:mecanico,secretaria,jefe_transporte')->group(function () {
        Route::post('/actas-entrega', DeliveryReceptionActStoreController::class);
        Route::post('/actas-recepcion-llegada', DeliveryReceptionActArrivalController::class);
        Route::get('/inspecciones/pendientes', PendingRouteSheetsController::class);
        Route::get('/inspecciones/componentes', ChecklistComponentsController::class);
        Route::get('/novedades', IssueLogListController::class);
        Route::post('/ordenes-taller', CreateWorkOrderController::class);
        Route::patch('/ordenes-taller/{id}/cerrar', CloseWorkOrderController::class);
        Route::get('/ordenes-taller', MechanicWorkOrdersController::class);
        Route::get('/insumos', SupplyInventoryListController::class);
        Route::get('/mecanicos', MechanicListController::class);
    });

    Route::middleware('role:secretaria,jefe_transporte,conductor,chofer')->group(function () {
        Route::get('/estaciones-servicio', ServiceStationListController::class);
    });

    // Alertas (campana de notificaciones)
    Route::get('/alertas', [AlertsController::class, 'index']);
    Route::post('/alertas/{id}/leida', [AlertsController::class, 'markRead']);

    // Paradas, Monitoreo y Evaluaciones
    Route::get('/hojas-ruta/{id}/paradas', [RouteSheetStopController::class, 'index']);
    Route::post('/hojas-ruta/{id}/paradas', [RouteSheetStopController::class, 'store']);
    Route::get('/mapas/viajes', [RouteMapController::class, 'index']);
    Route::get('/mapas/viajes/{id}', [RouteMapController::class, 'show']);
    Route::post('/evaluaciones', TripEvaluationStoreController::class);
    Route::get('/mis-evaluaciones-pendientes', PendingEvaluationsController::class);

    // Módulo de Administración General, Reportes y Auditoría
    Route::get('/reportes/kpis', AdminKpiController::class);
    Route::get('/dashboard/metrics', DashboardMetricsController::class);
    Route::get('/reportes/facultades', AdminFacultyReportController::class);
    Route::get('/reportes/aceite', [ReportsController::class, 'aceite']);
    Route::get('/reportes/novedades', [ReportsController::class, 'novedades']);
    Route::get('/tarifas', RateConfigurationListController::class);
    Route::post('/tarifas', RateConfigurationStoreController::class);
    Route::put('/tarifas/{id}', RateConfigurationUpdateController::class);
    Route::patch('/estaciones-servicio/{id}/toggle', ServiceStationToggleController::class);
    Route::get('/logs-sistema', SystemLogListController::class);

    // Módulo de Administración Global de Recursos (CRUDs Maestros)
    Route::apiResource('admin/usuarios', AdminUserController::class);
    Route::get('admin/roles', AdminRoleController::class);
    Route::apiResource('admin/vehiculos', AdminVehicleController::class);
    Route::apiResource('admin/choferes', AdminDriverController::class);

    // CRUD Convenios de Estaciones de Servicio
    Route::get('admin/estaciones', [AdminServiceStationController::class, 'index']);
    Route::post('admin/estaciones', [AdminServiceStationController::class, 'store']);
    Route::put('admin/estaciones/{id}', [AdminServiceStationController::class, 'update']);
    Route::patch('admin/estaciones/{id}/toggle-convenio', [AdminServiceStationController::class, 'toggleConvenio']);

    // CRUD Gestión de Tarifas Institucionales
    Route::get('admin/tarifas', RateConfigurationListController::class);
    Route::post('admin/tarifas', RateConfigurationStoreController::class);
    Route::put('admin/tarifas/{id}', RateConfigurationUpdateController::class);
});
