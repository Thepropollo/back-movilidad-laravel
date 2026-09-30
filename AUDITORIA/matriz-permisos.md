# Matriz de permisos por rol y endpoint

Fuente: definición real de `routes/api.php`, middleware compilado por `php artisan route:list --json` y comprobaciones internas de los controladores indicadas debajo. Fecha: 2026-09-29.

**Leyenda:** `R` = pasa middleware de rol de ruta; `A*` = cualquier usuario autenticado llega al controlador, que puede aplicar propiedad/rol interno; `—` = middleware de rol lo deniega; `P` = ruta pública sin autenticación. La celda no implica que el acceso a cualquier registro esté permitido: alcance de recurso requiere revisar el controlador. Alias legacy se canonizan según `RoleCatalog`.

| Método | Endpoint | Controlador | Secretaría | Docente | Responsable facultad | Vicerrector | Conductor | Mecánico | Estudiante |
|---|---|---|---:|---:|---:|---:|---:|---:|---:|
| `POST` | `/api/actas-entrega` | `Request\\DeliveryReceptionActStoreController` | R | — | — | — | — | R | — |
| `POST` | `/api/actas-recepcion-llegada` | `Request\\DeliveryReceptionActArrivalController` | R | — | — | — | — | R | — |
| `GET, HEAD` | `/api/admin/choferes` | `Request\\AdminDriverController@index` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/admin/choferes` | `Request\\AdminDriverController@store` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/admin/choferes/{chofere}` | `Request\\AdminDriverController@show` | A* | A* | A* | A* | A* | A* | A* |
| `PUT, PATCH` | `/api/admin/choferes/{chofere}` | `Request\\AdminDriverController@update` | A* | A* | A* | A* | A* | A* | A* |
| `DELETE` | `/api/admin/choferes/{chofere}` | `Request\\AdminDriverController@destroy` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/admin/estaciones` | `Request\\AdminServiceStationController@index` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/admin/estaciones` | `Request\\AdminServiceStationController@store` | A* | A* | A* | A* | A* | A* | A* |
| `PUT` | `/api/admin/estaciones/{id}` | `Request\\AdminServiceStationController@update` | A* | A* | A* | A* | A* | A* | A* |
| `PATCH` | `/api/admin/estaciones/{id}/toggle-convenio` | `Request\\AdminServiceStationController@toggleConvenio` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/admin/roles` | `Request\\AdminRoleController` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/admin/tarifas` | `Request\\RateConfigurationListController` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/admin/tarifas` | `Request\\RateConfigurationStoreController` | A* | A* | A* | A* | A* | A* | A* |
| `PUT` | `/api/admin/tarifas/{id}` | `Request\\RateConfigurationUpdateController` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/admin/usuarios` | `Request\\AdminUserController@index` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/admin/usuarios` | `Request\\AdminUserController@store` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/admin/usuarios/{usuario}` | `Request\\AdminUserController@show` | A* | A* | A* | A* | A* | A* | A* |
| `PUT, PATCH` | `/api/admin/usuarios/{usuario}` | `Request\\AdminUserController@update` | A* | A* | A* | A* | A* | A* | A* |
| `DELETE` | `/api/admin/usuarios/{usuario}` | `Request\\AdminUserController@destroy` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/admin/vehiculos` | `Request\\AdminVehicleController@index` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/admin/vehiculos` | `Request\\AdminVehicleController@store` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/admin/vehiculos/{vehiculo}` | `Request\\AdminVehicleController@show` | A* | A* | A* | A* | A* | A* | A* |
| `PUT, PATCH` | `/api/admin/vehiculos/{vehiculo}` | `Request\\AdminVehicleController@update` | A* | A* | A* | A* | A* | A* | A* |
| `DELETE` | `/api/admin/vehiculos/{vehiculo}` | `Request\\AdminVehicleController@destroy` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/agenda` | `Request\\AgendaController` | R | — | — | — | — | — | — |
| `GET, HEAD` | `/api/alertas` | `Request\\AlertsController@index` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/alertas/{id}/leida` | `Request\\AlertsController@markRead` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/compensaciones/pendientes` | `Request\\DriverCompensationListController` | R | — | — | — | — | — | — |
| `POST` | `/api/compensaciones/{hoja_ruta_id}/aprobar` | `Request\\DriverCompensationAprobarController` | R | — | — | — | — | — | — |
| `GET, HEAD` | `/api/compensaciones/{hoja_ruta_id}/calcular` | `Request\\DriverCompensationCalculateController` | R | — | — | — | — | — | — |
| `POST` | `/api/compensaciones/{hoja_ruta_id}/liquidar` | `Request\\DriverCompensationLiquidarController` | R | — | — | — | — | — | — |
| `PATCH` | `/api/compensaciones/{id}/confirmar` | `Request\\DriverCompensationMineController@confirm` | — | — | — | — | R | — | — |
| `GET, HEAD` | `/api/dashboard/metrics` | `Request\\DashboardMetricsController` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/documentos` | `Request\\InstitutionalDocumentController@index` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/documentos/adjuntar` | `Request\\InstitutionalDocumentController@upload` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/documentos/catalogo` | `Request\\InstitutionalDocumentController@catalog` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/documentos/generar` | `Request\\InstitutionalDocumentController@generate` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/documentos/{id}/archivo` | `Request\\InstitutionalDocumentController@download` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/documentos/{id}/firmar` | `Request\\InstitutionalDocumentController@sign` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/documentos/{id}/verificar` | `Request\\InstitutionalDocumentController@verify` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/drivers` | `Request\\FleetManageController@storeDriver` | R | — | — | — | — | — | — |
| `GET, HEAD` | `/api/drivers` | `Request\\DriverListController` | R | — | — | — | — | — | — |
| `PATCH` | `/api/drivers/{id}` | `Request\\FleetManageController@updateDriver` | R | — | — | — | — | — | — |
| `POST` | `/api/estaciones-servicio` | `Request\\ServiceStationManageController@store` | R | — | — | — | — | — | — |
| `GET, HEAD` | `/api/estaciones-servicio` | `Request\\ServiceStationListController` | R | — | — | — | R | — | — |
| `PATCH` | `/api/estaciones-servicio/{id}` | `Request\\ServiceStationManageController@update` | R | — | — | — | — | — | — |
| `PATCH` | `/api/estaciones-servicio/{id}/toggle` | `Request\\ServiceStationToggleController` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/estudiantes` | `Request\\ParticipantController@searchStudents` | — | R | R | — | — | — | — |
| `POST` | `/api/evaluaciones` | `Request\\TripEvaluationStoreController` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/hojas-ruta` | `Request\\RouteSheetStoreController` | R | — | — | — | — | — | — |
| `GET, HEAD` | `/api/hojas-ruta/{id}/paradas` | `Request\\RouteSheetStopController@index` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/hojas-ruta/{id}/paradas` | `Request\\RouteSheetStopController@store` | A* | A* | A* | A* | A* | A* | A* |
| `PATCH` | `/api/hojas-ruta/{id}/reasignar` | `Request\\ReassignRouteSheetController` | R | — | — | — | — | — | — |
| `PATCH` | `/api/hojas-ruta/{id}/responder` | `Request\\DriverRespondController` | — | — | — | — | R | — | — |
| `GET, HEAD` | `/api/inspecciones/componentes` | `Request\\ChecklistComponentsController` | R | — | — | — | — | R | — |
| `GET, HEAD` | `/api/inspecciones/pendientes` | `Request\\PendingRouteSheetsController` | R | — | — | — | — | R | — |
| `GET, HEAD` | `/api/insumos` | `Workshop\\SupplyInventoryListController` | R | — | — | — | — | R | — |
| `POST` | `/api/login` | `Auth\\LoginController` | P | P | P | P | P | P | P |
| `POST` | `/api/logout` | `Auth\\LogoutController` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/logs-sistema` | `Request\\SystemLogListController` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/mapas/viajes` | `Request\\RouteMapController@index` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/mapas/viajes/{id}` | `Request\\RouteMapController@show` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/me` | `Auth\\MeController` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/mecanicos` | `Workshop\\MechanicListController` | R | — | — | — | — | R | — |
| `GET, HEAD` | `/api/mi-vehiculo` | `Request\\MyVehicleController` | — | — | — | — | R | — | — |
| `GET, HEAD` | `/api/mis-comisiones-pendientes-liquidar` | `Request\\TeacherRouteSheetsController` | — | R | R | — | — | — | — |
| `GET, HEAD` | `/api/mis-compensaciones` | `Request\\DriverCompensationMineController@index` | — | — | — | — | R | — | — |
| `GET, HEAD` | `/api/mis-evaluaciones-pendientes` | `Request\\PendingEvaluationsController` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/mis-invitaciones` | `Request\\ParticipantController@myInvitations` | — | — | — | — | — | — | R |
| `GET, HEAD` | `/api/mis-ordenes-combustible` | `Request\\DriverFuelOrdersController` | — | — | — | — | R | — | — |
| `GET, HEAD` | `/api/mis-viajes` | `Request\\DriverTripsController` | R | — | — | — | R | — | — |
| `POST` | `/api/novedades` | `Workshop\\IssueLogStoreController` | — | — | — | — | R | — | — |
| `GET, HEAD` | `/api/novedades` | `Workshop\\IssueLogListController` | R | — | — | — | — | R | — |
| `POST` | `/api/ordenes-combustible` | `Request\\FuelOrderStoreController` | R | — | — | — | — | — | — |
| `GET, HEAD` | `/api/ordenes-combustible/{codigo_orden}` | `Request\\FuelOrderShowController` | R | — | — | — | — | — | — |
| `PATCH` | `/api/ordenes-combustible/{codigo_orden}/despachar` | `Request\\FuelOrderDespacharController` | R | — | — | — | — | — | — |
| `POST` | `/api/ordenes-taller` | `Workshop\\CreateWorkOrderController` | R | — | — | — | — | R | — |
| `GET, HEAD` | `/api/ordenes-taller` | `Workshop\\MechanicWorkOrdersController` | R | — | — | — | — | R | — |
| `PATCH` | `/api/ordenes-taller/{id}/cerrar` | `Workshop\\CloseWorkOrderController` | R | — | — | — | — | R | — |
| `PATCH` | `/api/participantes/{id}/responder` | `Request\\ParticipantController@respond` | — | — | — | — | — | — | R |
| `POST` | `/api/register` | `Auth\\RegisterController` | P | P | P | P | P | P | P |
| `GET, HEAD` | `/api/reportes/aceite` | `Request\\ReportsController@aceite` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/reportes/facultades` | `Request\\AdminFacultyReportController` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/reportes/flota` | `Request\\ReportsController@flota` | R | — | — | — | — | — | — |
| `GET, HEAD` | `/api/reportes/kpis` | `Request\\AdminKpiController` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/reportes/mensual` | `Request\\ReportsController@mensual` | R | R | R | R | — | — | — |
| `GET, HEAD` | `/api/reportes/novedades` | `Request\\ReportsController@novedades` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/reportes/solicitudes` | `Request\\ReportsController@solicitudes` | R | R | R | R | — | — | — |
| `GET, HEAD` | `/api/reportes/viajes` | `Request\\ReportsController@viajes` | R | R | R | R | — | — | — |
| `POST` | `/api/solicitudes` | `Request\\SolicitudStoreController` | — | R | R | — | — | — | — |
| `GET, HEAD` | `/api/solicitudes` | `Request\\SolicitudListController` | A* | A* | A* | A* | A* | A* | A* |
| `PATCH` | `/api/solicitudes/{id}/aprobar-rectorado` | `Request\\AprobarRectoradoController` | — | — | — | R | — | — | — |
| `PATCH` | `/api/solicitudes/{id}/autorizar-secretaria` | `Request\\AutorizarSecretariaController` | R | — | — | — | — | — | — |
| `GET, HEAD` | `/api/solicitudes/{id}/flujo` | `Request\\SolicitudFlujoController` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/solicitudes/{id}/participantes` | `Request\\ParticipantController@store` | — | R | R | — | — | — | — |
| `GET, HEAD` | `/api/solicitudes/{id}/participantes` | `Request\\ParticipantController@index` | A* | A* | A* | A* | A* | A* | A* |
| `GET, HEAD` | `/api/tarifas` | `Request\\RateConfigurationListController` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/tarifas` | `Request\\RateConfigurationStoreController` | A* | A* | A* | A* | A* | A* | A* |
| `PUT` | `/api/tarifas/{id}` | `Request\\RateConfigurationUpdateController` | A* | A* | A* | A* | A* | A* | A* |
| `POST` | `/api/vehicles` | `Request\\FleetManageController@storeVehicle` | R | — | — | — | — | — | — |
| `GET, HEAD` | `/api/vehicles` | `Request\\VehicleListController` | R | — | — | — | — | — | — |
| `PATCH` | `/api/vehicles/{id}` | `Request\\FleetManageController@updateVehicle` | R | — | — | — | — | — | — |
| `PATCH` | `/api/vehicles/{id}/documentos` | `Request\\FleetManageController@updateVehicleDocuments` | R | — | — | — | — | — | — |

## Restricciones adicionales confirmadas en código

- `/api/admin/usuarios`, `/api/admin/vehiculos`, `/api/admin/choferes`, `/api/admin/estaciones`, `/api/admin/roles` y rutas administrativas de KPIs/auditoría solo añaden `auth:sanctum` en la ruta. Los controladores hacen comprobaciones internas, que no son uniformes; varios exigen el nombre legacy `jefe_transporte` directamente. Tras migrar ese usuario a `secretaria`, esas comparaciones rechazan a Secretaría. BE-04 documenta el defecto.
- Solicitudes, flujo, participantes, documentos, mapas, paradas, evaluaciones, tarifas, paneles y alertas tienen `A*` en middleware, por lo que todos los roles autenticados alcanzan el controlador. La inspección encontró autorización/filtrado de propiedad desigual; los IDs no deben considerarse protegidos por esta tabla. Casos comprobados se enumeran en BE-05, BE-09, BE-10, BE-12 y BE-14.
- `role:docente,solicitante,responsable_facultad` concede por unión de roles (OR), no exige rol primario. `User::hasRole()` canoniza alias. Un usuario con varios roles obtiene la unión de esos permisos en rutas con middleware.
- La ruta de verificación documental no es pública: está dentro del grupo `auth:sanctum`. `/up` es el healthcheck Laravel sin auth; las rutas `storage` son del framework y no están bajo `/api`.

La matriz describe controles de ruta observados, no una política recomendada. Acceso por cada rol y objeto requiere pruebas de integración con una base aislada; actualmente no verificable porque falta PDO SQLite y no se pudo iniciar PostgreSQL local.