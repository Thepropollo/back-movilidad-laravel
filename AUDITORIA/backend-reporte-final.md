# Reporte final de auditoría del backend

Fecha: 2026-09-29. Rama revisada: `audit/backend`. Alcance: repositorio backend; no se inspeccionó ni modificó `frontend/`. La auditoría verificó código, rutas, permisos, migraciones, dependencias, tests y arranque local. El informe no certifica comportamiento bajo carga ni configuración de un proveedor de producción.

## Veredicto

**NO LISTO para producción.** La suite de backend (48 tests, 644 aserciones), las migraciones desde cero y el smoke test HTTP pasan en infraestructura PostgreSQL desechable. Sin embargo, ninguno de los ocho recorridos funcionales solicitados está cubierto integralmente de inicio a fin; quedan controles de propiedad/PII, unicidad e idempotencia persistente, cálculos económicos, auditoría, límites de reportes y recuperación de contraseña. También faltan decisiones institucionales y la selección del entorno de despliegue.

Este veredicto se refiere al backend y a la evidencia disponible en esta rama. No implica que la instalación `.env` (`tesis`) haya sido utilizada: no se conectó a ella ni se ejecutaron allí migraciones o semillas.

## Resumen ejecutivo

El proyecto usa PHP 8.5.9, Laravel 13.34.0, Eloquent, PostgreSQL/MySQL/SQLite según configuración, Composer y npm/Vite. Laravel reporta 113 rutas (104 API, 9 web/sistema); el inventario método/ruta/controlador/middleware está en [endpoints.md](endpoints.md), la matriz rol × endpoint en [matriz-permisos.md](matriz-permisos.md), el esquema observado en [mapa-backend.md](mapa-backend.md), y las transiciones en [maquina-de-estados.md](maquina-de-estados.md).

Se corrigieron o mitigaron problemas de creación de cuentas privilegiadas de demostración en producción, revocación de sesiones y roles, asignación masiva durante registro, permisos administrativos, acceso/serialización documental, asignación concurrente, evaluaciones, inspecciones, paradas, combustible, taller y exposición de PII de participantes/mapas. Se actualizaron dependencias dentro de sus mismas versiones mayores, se añadió lockfile npm y referencia OpenAPI pública, y se documentó un despliegue neutral. Los hallazgos y su evidencia están detallados en [backend-hallazgos.md](backend-hallazgos.md).

La plataforma identifica Secretaría/jefatura de transporte como ejecutora de las funciones administrativas; no existe un rol administrador separado. Se añadió el comando interactivo `app:bootstrap-secretaria`, sin credenciales predeterminadas. El alcance del responsable de facultad es limitado a solicitudes propias/de su facultad y reportes habilitados; no autoriza trámites.

## Verificación ejecutada

| Comprobación | Resultado | Evidencia |
|---|---|---|
| Rama Git | Pasa | Rama activa `audit/backend`; no se hizo push ni merge. |
| Suite backend | Pasa | 48 tests, 644 aserciones, 0 fallos tras `e22c766` en la base desechable `backend_audit_final_test` (`127.0.0.1:55433`), con `APP_ENV=testing` y variables explícitas. |
| Autorización por rol sobre HTTP | Pasa para ruta administrativa | `AdminRouteRoleAccessTest`: anónimo 401; docente, responsable de facultad, vicerrector, conductor, mecánico y estudiante 403; Secretaría 200; combinación sin Secretaría 403 y con Secretaría 200. No prueba todas las rutas para todos los roles. |
| Migraciones | Pasa | `php artisan migrate --force` completó todas las migraciones en la base vacía desechable `backend_audit_migrate`. No se ejecutaron semillas. |
| HTTP local | Pasa, smoke test | Con app apuntada a la base desechable: `/up` 200, `/docs` 302, `/scalar` 200, `/openapi.json` 200. Se detuvo el servidor tras la comprobación. |
| Laravel / API | Pasa | Laravel 13.34.0; 113 rutas, incluidas 104 bajo `/api`. OpenAPI contiene 84 paths y 107 operaciones API que coinciden por método/ruta con las rutas inventariadas. |
| Composer | Pasa | `composer validate --no-check-publish` válido; `composer audit --locked --no-dev` informa 0 advisories tras la actualización. |
| npm | Pasa | `npm ci`; `npm run build` genera assets; `npm audit` informa 0 vulnerabilidades. Se retiró el fetch remoto de fuente del build. |
| Sintaxis PHP | Pasa | `php -l` sobre 190 archivos PHP inspeccionados sin errores. La ejecución posterior de la suite también compiló el código de tests. |
| Pint | No cumple | `vendor/bin/pint --test` reporta 12 archivos fuera de formato: `routes/console.php`, `EnsureUserIsActiveTest.php`, `EnsureUserHasRoleTest.php`, `ScalarDocumentationTest.php`, `DeliveryReceptionTransitionsTest.php`, `RouteSheetStopController.php`, `FleetManageController.php`, `InstitutionalDocumentController.php`, `SolicitudStoreController.php`, `CreateRouteSheetAction.php`, `CloseWorkOrderAction.php`, `ReportVehicleIssueAction.php`. No se aplicó autoformato masivo. |
| Docker | No verificado | `Dockerfile.example` está marcado como propuesta; no se construyó ni ejecutó como contenedor. |
| Escaneo de secretos | Parcial | Un escaneo heurístico de parches Git no detectó patrones configurados para llaves privadas, tokens conocidos ni credenciales literales; `.env` no se mostró ni se usó. No hay `gitleaks`/`trufflehog` instalado; no es una certificación exhaustiva del historial. |

La ejecución de pruebas y migraciones usó un clúster PostgreSQL temporal bajo `/tmp`, con bases nuevas y nombres verificados. `.env` (`tesis`) y la base `tesis_test` configurada en el `phpunit.xml` del árbol de trabajo no fueron destino de estas operaciones. Las modificaciones locales preexistentes de `.gitignore`, `bootstrap/app.php` y `phpunit.xml` se conservaron sin incluirlas en los commits de auditoría.

## Hallazgos corregidos y commits

| Corrección | Commits |
|---|---|
| Bloqueo del seeder de demostración en producción y bootstrap interactivo de la primera Secretaría | `db84834`, `ce19c94` |
| Rechazo de autenticación de cuentas inactivas; sincronización de roles y revocación de tokens al cambiar permisos | `15d5d44`, `49a6de5` |
| Rutas administrativas con rol canónico; registro público con rol mínimo y campos privilegiados rechazados | `51ab5ce`, `346e628` |
| Alcance y serialización de documentos institucionales; adjuntos con nombre/MIME/tamaño/cantidad restringidos y comprobación SHA-256 | `60c96ac` |
| Transiciones de taller, asignación y reasignación con bloqueos y validación de choques; corrección de reparación tras inspección fallida | `bed4816`, `ec7d90f`, `51aef63` |
| Evaluaciones limitadas a participantes aceptados después de llegada; actas, novedades y paradas con actor/propiedad/estado comprobados | `e6dc2e6`, `5e7f1cc`, `a368aa8`, `0aa5b40` |
| Emisión y despacho de vales de combustible transaccionales; respuestas de participante/mapa minimizadas; protección de historial al desactivar conductor | `6e619bb`, `229f67a`, `a1ad324` |
| Dependencias Composer actualizadas dentro de las versiones mayores y npm lockfile/build sin fetch remoto | `254adfd`, `996bd4c` |
| Guía neutral de despliegue y OpenAPI/Scalar, con referencia de firma corregida | `25d0baf`, `516685f`, `ca05228` |
| Regresiones de administración/agenda estabilizadas; prueba HTTP administrativa por rol y suite final PostgreSQL | `0ec8517`, `e22c766` |

Las correcciones con cobertura parcial, su categoría OWASP y evidencia por archivo/línea están registradas en [backend-hallazgos.md](backend-hallazgos.md). Las clasificaciones usan [OWASP Top 10:2025](https://top10.owasp.org/2025/) y [OWASP API Security Top 10:2023](https://owasp.org/projects/api-security-project).

## Flujos funcionales

El estado **No verificado** indica que la prueba no recorre el flujo completo solicitado en una sola secuencia, aunque sí pasen tests de integración parciales sobre PostgreSQL desechable.

| Flujo | Resultado | Evidencia y límite principal |
|---|---|---|
| 1. Registro, login y acceso por roles | No verificado | Tests verifican alta con rol estudiante, campos prohibidos, login/cuenta inactiva y algunos cambios de rol. No hay recorrido registro→login→logout ni recuperación/cambio de contraseña/verificación de correo. |
| 2. Solicitud, aprobaciones, asignación, rechazo/reasignación | No verificado | Hay tests parciales de solicitudes interna/externa, asignación y unidad de intervalos. No se cubren en un solo E2E rechazo del conductor, reasignación y concurrencia; falta capacidad de vehículo. |
| 3. Invitación, respuesta, viaje y evaluación | No verificado | Tests de acceso a evaluación, identidad del invitado y minimización pasan; no hay prueba integral de invitación→respuesta→viaje→evaluación. No hay unicidad DB ni capacidad completa. |
| 4. Inspección, salida, paradas, llegada y cierre | No verificado | Tests parciales de transiciones y paradas pasan, al igual que reparación/reinspección tras salida fallida. Falta recorrido integral de ejecución/cierre y aclarar checklist de llegada. |
| 5. Orden y despacho de combustible | No verificado | `FuelOrderLifecycleTest` verifica emisión/despacho/repetición; falta consulta/consumo acumulado. Fórmula de distancia/unidad y actualización mensual requieren decisión. |
| 6. Novedad, taller, mantenimiento y disponibilidad | No verificado | Pasa autorización de novedad y la regresión de reparación de inspección defectuosa; falta ciclo normal completo y test concurrente. |
| 7. Cálculo, liquidación, aprobación y confirmación de compensación | No verificado | No existe test integral ni casos borde. El código usa `float`; no está decidida fórmula decimal, revisor económico independiente ni separación de funciones. |
| 8. Documento, adjunto, firma, verificación, reportes y auditoría | No verificado | Pasan pruebas de respuesta documental, reportes/dashboard y OpenAPI. No se probaron conjuntamente PDF/archivo/firma/verificación/permisos. Firma es metadato de aplicación, no una firma legal embebida/certificada; reportes y auditoría tienen controles incompletos. |

La bitácora detallada de pasos, tests y commits está en [flujos-e2e.md](flujos-e2e.md).

## Pendientes y riesgos residuales

- **Acceso por objeto/PII (OWASP API1/API3):** ciertos listados Eloquent de solicitudes, viajes y combustible, además de documentos PDF, aún incluyen más identificación/contacto de la necesaria. La matriz estática de permisos no sustituye una prueba negativa HTTP por cada rol y combinación multirol. Rutas generales de alertas/mapas/solicitudes requieren revisar el alcance por objeto extremo a extremo.
- **Invitaciones y evaluaciones (API1/API6):** falta restricción única DB y política completa de duplicados, cupo y cierre. No se añadió migración de unicidad sin auditar posibles duplicados de instalaciones existentes.
- **Dinero y separación de funciones:** los cálculos de compensación usan `float`; la fórmula, redondeo y revisor independiente no están fijados. Las autorizaciones no impiden a una persona aprobar su propio trámite.
- **Auditoría (A09:2025):** algunas entradas contienen nombre/correo y no hay valores antes/después estructurados para todas las acciones sensibles, aunque la API no expone escritura/borrado de logs y el listado está limitado.
- **Reportes y recursos (API4):** faltan límites y validaciones uniformes de filtros/exportaciones; algunos listados son sin paginar.
- **Sesiones:** login/registro usan límite de intentos y tokens Sanctum expiran; no se encontraron flujos de reset/cambio de contraseña o correo verificado.
- **Firma/documentos:** el runtime auditado carece de Sodium, por lo que la firma de aplicación cae a HMAC con `APP_KEY`. Se compara el hash del archivo, pero la firma no está embebida ni certificada por autoridad; rotación/historial de claves no se probó. El almacenamiento privado y las URLs firmadas de `/storage/{path}` no se probaron con archivo real.
- **Despliegue:** proveedor, dominio, CORS permitido, proxy confiable, TLS, correo, persistencia/backup de adjuntos y RPO/RTO no están elegidos. El Dockerfile de ejemplo no se construyó. Backups/restauración están documentados, no ensayados.
- **Calidad:** Pint reporta 12 archivos fuera de formato. Composer/npm audit y build sí pasan.
- **Secretos:** el escaneo heurístico no dio hallazgos, pero no se ejecutó un escáner especializado sobre todo el historial. El seeder de demostración mantiene cuentas de prueba en código y se bloquea en producción; nunca se debe sembrar en producción. Si alguna credencial de prueba coincidió con una cuenta desplegada, debe rotarse.
- **Inventario/consumidores:** hay familias de rutas administrativas y alias operativos repetidas (p. ej. vehículos, tarifas, choferes y estaciones). No se identificaron consumidores frontend desde este alcance; no se propone eliminar rutas hasta decidir su uso.

## Requiere mi decisión

1. **Autorización externa:** el backend implementa Secretaría y después Vicerrectorado. Confirmar si este doble paso es la regla institucional.
2. **Rechazo del conductor:** existe reasignación por Secretaría tras el rechazo. Confirmar si se conserva y si puede cambiar vehículo y conductor.
3. **Compensación/liquidación:** el código usa un mismo registro por hoja, con cálculo, liquidación, aprobación y confirmación del conductor. Secretaría ejecuta cálculo/liquidación/aprobación; no existe revisión económica independiente. Definir el revisor y la incompatibilidad de actores.
4. **Responsable de facultad:** el alcance real es solicitar como usuario facultativo, administrar participantes de solicitudes propias y consultar reportes permitidos de su facultad; no aprueba. Confirmar que ese alcance satisface la función del rol.
5. **Administración/bootstrap:** Secretaría/jefatura tiene las funciones CRUD administrativas; no hay rol `administrador` independiente. El primer usuario se crea mediante `php artisan app:bootstrap-secretaria`. Confirmar si este modelo institucional es correcto y quién custodiará la credencial inicial.
6. **Despliegue:** elegir proveedor/topología, dominio/orígenes CORS, proxy/TLS, correo, disco privado durable y retención/RPO/RTO. La propuesta actual no ata el sistema a proveedor.
7. **Capacidad y combustible:** indicar fuente de plazas del vehículo, unidad (litros/galones), distancia autorizada, rendimiento y periodicidad/límite de consumo.
8. **Compensación y firma:** aprobar fórmula, precisión/redondeo, revisión económica, separación de funciones y nivel de firma jurídica requerido (registro HMAC/Ed25519 frente a firma PDF/certificado institucional).
9. **Integridad y rutas:** permitir primero auditar datos existentes y luego añadir índices únicos para invitaciones/evaluaciones/vales. Confirmar consumidores de las rutas alias antes de retirar o consolidar cualquiera.
10. **Inspección de llegada:** definir si basta acta de kilometraje/combustible o se debe exigir checklist completa para cerrar viaje.

## Checklist de producción

| Control | Estado | Evidencia / condición pendiente |
|---|---|---|
| Rama aislada y sin push/merge | Cumple | Trabajo realizado en `audit/backend`. |
| Migraciones reproducibles | Cumple con reserva | Todas corrieron desde cero en PostgreSQL desechable; faltan pruebas en imagen/hosting limpio. |
| Suite automatizada | Cumple parcialmente | 48/48 pasan en PostgreSQL desechable; los ocho E2E completos faltan. |
| Composer/npm con lockfile y sin advisories | Cumple | Composer audit y npm audit reportan cero; build Vite pasa. |
| Linter/formatter | No cumple | Pint encuentra 12 archivos fuera de formato. |
| Autenticación y control de roles | Cumple parcialmente | Cuentas inactivas, rol mínimo de registro y prueba HTTP por rol sobre ruta administrativa; falta cobertura negativa por toda ruta y restablecimiento de contraseña. |
| BOLA/PII y mínimo privilegio | No cumple | Hay controles corregidos y tests parciales; faltan scopes/presenters y matriz dinámica completa. |
| Cálculos financieros y separación | No cumple | `float`, fórmula no aprobada y sin revisión independiente configurada. |
| Auditoría inmutable/completa | No cumple | API no permite CRUD de logs; la captura antes/después y minimización de PII no son uniformes. |
| Documentos privados/firma | No verificado | Pruebas de PDF/archivos/firma con storage real no ejecutadas; no es firma digital institucional embebida. |
| Errores y healthcheck | Cumple parcialmente | `/up` 200, arranque local funciona; HTTPS/proxy/timeouts de producción no verificados. |
| CORS/TLS/secretos del entorno | No verificado | La lista CORS se configura por variables; falta origen real y proxy/TLS elegidos. Escaneo de secretos solo heurístico. |
| Docker/build reproducible | No verificado | Build npm y Composer validado; imagen Docker no construida en entorno limpio. |
| Backup y restauración | No verificado | Procedimiento documentado; no se ejecutó ciclo de restauración. |
| Despliegue listo para proveedor | No cumple | Proveedor/configuración de producción siguen sin definir. |

## Evidencia asociada

- [Hallazgos con ubicación, OWASP, corrección y estado](backend-hallazgos.md)
- [Matriz por rol y endpoint](matriz-permisos.md)
- [Inventario completo de endpoints](endpoints.md)
- [Mapa técnico, tablas, PII y configuración](mapa-backend.md)
- [Máquina de estados observada](maquina-de-estados.md)
- [Resultados por flujo](flujos-e2e.md)
- [Guía neutral de despliegue y backup](despliegue-estandar.md)
- [Cambios de contrato para revisión del frontend](cambios-de-contrato.md)
