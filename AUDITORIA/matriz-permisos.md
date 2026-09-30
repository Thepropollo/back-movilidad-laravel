# Matriz de permisos por rol y endpoint

Fuente: 104 rutas bajo `/api` y middleware compilado por `php artisan route:list --json`, con revisión adicional de restricciones internas en controladores. El inventario de las 9 rutas web/sistema está en [endpoints.md](endpoints.md). Fecha: 2026-09-29.

**Leyenda:** R = el rol puede usar la operación tras aplicar middleware y comprobaciones de rol internas; A* = cualquier usuario autenticado alcanza el controlador, donde puede recibir denegación o un alcance limitado; — = el rol se deniega por middleware o controlador; P = ruta pública sin autenticación. Cada ruta autenticada también exige cuenta activa. La celda no implica acceso a cualquier registro: el alcance de recurso requiere revisar el controlador. Alias legacy se canonizan según RoleCatalog.

| Método | Endpoint | Controlador | Secretaría | Docente | Responsable facultad | Vicerrector | Conductor | Mecánico | Estudiante |
|---|---|---|---:|---:|---:|---:|---:|---:|---:|
| POST | /api/actas-entrega | Request\DeliveryReceptionActStoreController | R | — | — | — | — | R | — |
| POST | /api/actas-recepcion-llegada | Request\DeliveryReceptionActArrivalController | R | — | — | — | — | R | — |
| GET, HEAD | /api/admin/choferes | Request\AdminDriverController@index | R | — | — | — | — | — | — |
| POST | /api/admin/choferes | Request\AdminDriverController@store | R | — | — | — | — | — | — |
| GET, HEAD | /api/admin/choferes/{chofere} | Request\AdminDriverController@show | R | — | — | — | — | — | — |
| PUT, PATCH | /api/admin/choferes/{chofere} | Request\AdminDriverController@update | R | — | — | — | — | — | — |
| DELETE | /api/admin/choferes/{chofere} | Request\AdminDriverController@destroy | R | — | — | — | — | — | — |
| GET, HEAD | /api/admin/estaciones | Request\AdminServiceStationController@index | R | — | — | — | — | — | — |
| POST | /api/admin/estaciones | Request\AdminServiceStationController@store | R | — | — | — | — | — | — |
| PUT | /api/admin/estaciones/{id} | Request\AdminServiceStationController@update | R | — | — | — | — | — | — |
| PATCH | /api/admin/estaciones/{id}/toggle-convenio | Request\AdminServiceStationController@toggleConvenio | R | — | — | — | — | — | — |
| GET, HEAD | /api/admin/roles | Request\AdminRoleController | R | — | — | — | — | — | — |
| GET, HEAD | /api/admin/tarifas | Request\RateConfigurationListController | R | — | — | — | — | — | — |
| POST | /api/admin/tarifas | Request\RateConfigurationStoreController | R | — | — | — | — | — | — |
| PUT | /api/admin/tarifas/{id} | Request\RateConfigurationUpdateController | R | — | — | — | — | — | — |
| GET, HEAD | /api/admin/usuarios | Request\AdminUserController@index | R | — | — | — | — | — | — |
| POST | /api/admin/usuarios | Request\AdminUserController@store | R | — | — | — | — | — | — |
| GET, HEAD | /api/admin/usuarios/{usuario} | Request\AdminUserController@show | R | — | — | — | — | — | — |
| PUT, PATCH | /api/admin/usuarios/{usuario} | Request\AdminUserController@update | R | — | — | — | — | — | — |
| DELETE | /api/admin/usuarios/{usuario} | Request\AdminUserController@destroy | R | — | — | — | — | — | — |
| GET, HEAD | /api/admin/vehiculos | Request\AdminVehicleController@index | R | — | — | — | — | — | — |
| POST | /api/admin/vehiculos | Request\AdminVehicleController@store | R | — | — | — | — | — | — |
| GET, HEAD | /api/admin/vehiculos/{vehiculo} | Request\AdminVehicleController@show | R | — | — | — | — | — | — |
| PUT, PATCH | /api/admin/vehiculos/{vehiculo} | Request\AdminVehicleController@update | R | — | — | — | — | — | — |
| DELETE | /api/admin/vehiculos/{vehiculo} | Request\AdminVehicleController@destroy | R | — | — | — | — | — | — |
| GET, HEAD | /api/agenda | Request\AgendaController | R | — | — | — | — | — | — |
| GET, HEAD | /api/alertas | Request\AlertsController@index | A* | A* | A* | A* | A* | A* | A* |
| POST | /api/alertas/{id}/leida | Request\AlertsController@markRead | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/compensaciones/pendientes | Request\DriverCompensationListController | R | — | — | — | — | — | — |
| POST | /api/compensaciones/{hoja_ruta_id}/aprobar | Request\DriverCompensationAprobarController | R | — | — | — | — | — | — |
| GET, HEAD | /api/compensaciones/{hoja_ruta_id}/calcular | Request\DriverCompensationCalculateController | R | — | — | — | — | — | — |
| POST | /api/compensaciones/{hoja_ruta_id}/liquidar | Request\DriverCompensationLiquidarController | R | — | — | — | — | — | — |
| PATCH | /api/compensaciones/{id}/confirmar | Request\DriverCompensationMineController@confirm | — | — | — | — | R | — | — |
| GET, HEAD | /api/dashboard/metrics | Request\DashboardMetricsController | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/documentos | Request\InstitutionalDocumentController@index | A* | A* | A* | A* | A* | A* | A* |
| POST | /api/documentos/adjuntar | Request\InstitutionalDocumentController@upload | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/documentos/catalogo | Request\InstitutionalDocumentController@catalog | A* | A* | A* | A* | A* | A* | A* |
| POST | /api/documentos/generar | Request\InstitutionalDocumentController@generate | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/documentos/{id}/archivo | Request\InstitutionalDocumentController@download | A* | A* | A* | A* | A* | A* | A* |
| POST | /api/documentos/{id}/firmar | Request\InstitutionalDocumentController@sign | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/documentos/{id}/verificar | Request\InstitutionalDocumentController@verify | A* | A* | A* | A* | A* | A* | A* |
| POST | /api/drivers | Request\FleetManageController@storeDriver | R | — | — | — | — | — | — |
| GET, HEAD | /api/drivers | Request\DriverListController | R | — | — | — | — | — | — |
| PATCH | /api/drivers/{id} | Request\FleetManageController@updateDriver | R | — | — | — | — | — | — |
| POST | /api/estaciones-servicio | Request\ServiceStationManageController@store | R | — | — | — | — | — | — |
| GET, HEAD | /api/estaciones-servicio | Request\ServiceStationListController | R | — | — | — | R | — | — |
| PATCH | /api/estaciones-servicio/{id} | Request\ServiceStationManageController@update | R | — | — | — | — | — | — |
| PATCH | /api/estaciones-servicio/{id}/toggle | Request\ServiceStationToggleController | R | — | — | — | — | — | — |
| GET, HEAD | /api/estudiantes | Request\ParticipantController@searchStudents | — | R | R | — | — | — | — |
| POST | /api/evaluaciones | Request\TripEvaluationStoreController | A* | A* | A* | A* | A* | A* | A* |
| POST | /api/hojas-ruta | Request\RouteSheetStoreController | R | — | — | — | — | — | — |
| GET, HEAD | /api/hojas-ruta/{id}/paradas | Request\RouteSheetStopController@index | A* | A* | A* | A* | A* | A* | A* |
| POST | /api/hojas-ruta/{id}/paradas | Request\RouteSheetStopController@store | A* | A* | A* | A* | A* | A* | A* |
| PATCH | /api/hojas-ruta/{id}/reasignar | Request\ReassignRouteSheetController | R | — | — | — | — | — | — |
| PATCH | /api/hojas-ruta/{id}/responder | Request\DriverRespondController | — | — | — | — | R | — | — |
| GET, HEAD | /api/inspecciones/componentes | Request\ChecklistComponentsController | R | — | — | — | — | R | — |
| GET, HEAD | /api/inspecciones/pendientes | Request\PendingRouteSheetsController | R | — | — | — | — | R | — |
| GET, HEAD | /api/insumos | Workshop\SupplyInventoryListController | R | — | — | — | — | R | — |
| POST | /api/login | Auth\LoginController | P | P | P | P | P | P | P |
| POST | /api/logout | Auth\LogoutController | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/logs-sistema | Request\SystemLogListController | R | — | — | — | — | — | — |
| GET, HEAD | /api/mapas/viajes | Request\RouteMapController@index | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/mapas/viajes/{id} | Request\RouteMapController@show | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/me | Auth\MeController | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/mecanicos | Workshop\MechanicListController | R | — | — | — | — | R | — |
| GET, HEAD | /api/mi-vehiculo | Request\MyVehicleController | — | — | — | — | R | — | — |
| GET, HEAD | /api/mis-comisiones-pendientes-liquidar | Request\TeacherRouteSheetsController | — | R | R | — | — | — | — |
| GET, HEAD | /api/mis-compensaciones | Request\DriverCompensationMineController@index | — | — | — | — | R | — | — |
| GET, HEAD | /api/mis-evaluaciones-pendientes | Request\PendingEvaluationsController | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/mis-invitaciones | Request\ParticipantController@myInvitations | — | — | — | — | — | — | R |
| GET, HEAD | /api/mis-ordenes-combustible | Request\DriverFuelOrdersController | — | — | — | — | R | — | — |
| GET, HEAD | /api/mis-viajes | Request\DriverTripsController | R | — | — | — | R | — | — |
| POST | /api/novedades | Workshop\IssueLogStoreController | — | — | — | — | R | — | — |
| GET, HEAD | /api/novedades | Workshop\IssueLogListController | R | — | — | — | — | R | — |
| POST | /api/ordenes-combustible | Request\FuelOrderStoreController | R | — | — | — | — | — | — |
| GET, HEAD | /api/ordenes-combustible/{codigo_orden} | Request\FuelOrderShowController | R | — | — | — | — | — | — |
| PATCH | /api/ordenes-combustible/{codigo_orden}/despachar | Request\FuelOrderDespacharController | R | — | — | — | — | — | — |
| POST | /api/ordenes-taller | Workshop\CreateWorkOrderController | R | — | — | — | — | R | — |
| GET, HEAD | /api/ordenes-taller | Workshop\MechanicWorkOrdersController | R | — | — | — | — | R | — |
| PATCH | /api/ordenes-taller/{id}/cerrar | Workshop\CloseWorkOrderController | R | — | — | — | — | R | — |
| PATCH | /api/participantes/{id}/responder | Request\ParticipantController@respond | — | — | — | — | — | — | R |
| POST | /api/register | Auth\RegisterController | P | P | P | P | P | P | P |
| GET, HEAD | /api/reportes/aceite | Request\ReportsController@aceite | R | — | — | — | — | R | — |
| GET, HEAD | /api/reportes/facultades | Request\AdminFacultyReportController | R | — | — | — | — | — | — |
| GET, HEAD | /api/reportes/flota | Request\ReportsController@flota | R | — | — | — | — | — | — |
| GET, HEAD | /api/reportes/kpis | Request\AdminKpiController | R | — | — | — | — | — | — |
| GET, HEAD | /api/reportes/mensual | Request\ReportsController@mensual | R | — | — | R | — | — | — |
| GET, HEAD | /api/reportes/novedades | Request\ReportsController@novedades | R | — | — | — | R | R | — |
| GET, HEAD | /api/reportes/solicitudes | Request\ReportsController@solicitudes | R | R | R | R | — | — | — |
| GET, HEAD | /api/reportes/viajes | Request\ReportsController@viajes | R | — | — | R | — | — | — |
| POST | /api/solicitudes | Request\SolicitudStoreController | — | R | R | — | — | — | — |
| GET, HEAD | /api/solicitudes | Request\SolicitudListController | A* | A* | A* | A* | A* | A* | A* |
| PATCH | /api/solicitudes/{id}/aprobar-rectorado | Request\AprobarRectoradoController | — | — | — | R | — | — | — |
| PATCH | /api/solicitudes/{id}/autorizar-secretaria | Request\AutorizarSecretariaController | R | — | — | — | — | — | — |
| GET, HEAD | /api/solicitudes/{id}/flujo | Request\SolicitudFlujoController | A* | A* | A* | A* | A* | A* | A* |
| POST | /api/solicitudes/{id}/participantes | Request\ParticipantController@store | — | R | R | — | — | — | — |
| GET, HEAD | /api/solicitudes/{id}/participantes | Request\ParticipantController@index | A* | A* | A* | A* | A* | A* | A* |
| GET, HEAD | /api/tarifas | Request\RateConfigurationListController | R | — | — | — | — | — | — |
| POST | /api/tarifas | Request\RateConfigurationStoreController | R | — | — | — | — | — | — |
| PUT | /api/tarifas/{id} | Request\RateConfigurationUpdateController | R | — | — | — | — | — | — |
| POST | /api/vehicles | Request\FleetManageController@storeVehicle | R | — | — | — | — | — | — |
| GET, HEAD | /api/vehicles | Request\VehicleListController | R | — | — | — | — | — | — |
| PATCH | /api/vehicles/{id} | Request\FleetManageController@updateVehicle | R | — | — | — | — | — | — |
| PATCH | /api/vehicles/{id}/documentos | Request\FleetManageController@updateVehicleDocuments | R | — | — | — | — | — | — |

## Rutas no API

| Ruta | Middleware de ruta | Acceso observado |
|---|---|---|
| `GET /` | web | Pública; vista bienvenida. |
| `GET /up` | healthcheck Laravel | Pública; estado de vida. |
| `GET /sanctum/csrf-cookie` | web | Pública; emite cookie CSRF para flujos stateful. |
| `GET /docs`, `/scalar`, `/openapi.json`, `/openapi.yaml` | web | Públicas; documentación de API. Las definiciones no incluyen ejemplos de credenciales reales. |
| `GET /storage/{path}`, `PUT /storage/{path}` | ninguno en `route:list` | Laravel exige URL firmada en el handler local salvo disco público; no se probó el intercambio de archivo en esta auditoría. No asumir que la ausencia de middleware de ruta permite acceso anónimo. |

## Restricciones adicionales confirmadas en código

- Las rutas administrativas de usuarios, vehículos, conductores, convenios de estaciones, roles, tarifas, KPIs y auditoría exigen el rol secretaria en middleware; los controladores aplican además la misma comprobación mediante hasRole(). El rol canónico secretaria acepta aliases a través de RoleCatalog.
- Solicitudes, flujo, participantes, documentos, mapas, paradas, evaluaciones, paneles y alertas tienen A* en middleware; todos los roles autenticados alcanzan el controlador. Las autorizaciones de propiedad son desiguales; los IDs no deben considerarse protegidos por esta tabla. Casos revisados constan en BE-05, BE-09, BE-10, BE-12 y BE-14.
- Los reportes aplican comprobaciones internas adicionales que estrechan el middleware: `reportes/aceite` acepta Secretaría/mecánico; `reportes/novedades`, Secretaría/mecánico/conductor (conductor ve las suyas); `reportes/mensual` y `reportes/viajes`, Secretaría/Vicerrectorado; `reportes/solicitudes`, Secretaría y, con alcance, docente (propias), responsable de facultad (misma facultad) y Vicerrectorado (externas). La ruta de reportes mensual permite pasar el middleware a docentes/responsables, pero el controlador los rechaza.
- El responsable de facultad puede crear solicitudes propias, consultar solicitudes/viajes de su facultad y ver informes de solicitudes dentro de ese alcance. No tiene endpoint de aprobación; invitar participantes está limitado al dueño de la solicitud o Secretaría. La facultad se compara por el campo textual `faculty_institution`, no por una FK normalizada.
- role:docente,solicitante,responsable_facultad concede por unión de roles (OR), no exige rol primario. User::hasRole() canoniza alias. Un usuario con varios roles obtiene la unión de esos permisos en rutas con middleware.
- /api/login y /api/register son públicas y limitadas a 10 por minuto. La ruta de verificación documental es autenticada. /up es el healthcheck Laravel sin auth; las rutas storage son de framework y están fuera de /api.

La matriz describe controles de ruta observados, no una política recomendada. `AdminRouteRoleAccessTest` ejecuta peticiones HTTP contra una ruta administrativa por cada rol y dos combinaciones multirol; las demás celdas se derivan de middleware/controladores y no de pruebas HTTP individuales. Los alcances dependen de comprobaciones internas del controlador y no constituyen una certificación exhaustiva de BOLA.
