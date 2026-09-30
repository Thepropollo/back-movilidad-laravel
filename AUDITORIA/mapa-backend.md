# Reconocimiento del backend

Inspección estática realizada el 2026-09-29 en la rama `audit/backend`. No se ejecutaron migraciones ni semillas sobre la base configurada en `.env`.

## Stack, instalación y despliegue

- PHP: el repositorio requiere `^8.3`; el runtime inspeccionado es PHP 8.5.9.
- Framework: Laravel 13.17.0 (`composer.lock`).
- ORM y migraciones: Eloquent y migraciones de Laravel.
- Base de datos: la configuración predeterminada permite SQLite; el `.env` presente declara PostgreSQL en `127.0.0.1`, base `tesis`, entorno `local`. El servidor local no estaba escuchando en el puerto 5432. No se imprimieron ni copiaron credenciales y no se conectó a esa base.
- Pruebas: `phpunit.xml` configura SQLite `:memory:`. Este PHP tiene `pdo_pgsql`, pero no `pdo_sqlite`; la suite falla antes de ejecutar migraciones.
- Dependencias PHP: Composer con `composer.lock`. Dependencias JS: npm; `package.json` declara Vite/Tailwind/Concurrently, pero no hay `package-lock.json` y `node_modules` no existe.
- Despliegue: no hay Dockerfile, compose, manifiesto de proveedor ni guía de despliegue. El proveedor queda por definir. Laravel ofrece `/up` como healthcheck.
- Contrato API: no existe OpenAPI/Swagger. Las 109 rutas registradas (104 bajo `/api`) están en [endpoints.md](endpoints.md), obtenidas de `php artisan route:list --json`.
- Estructura: `src/App/Http/Controllers/` contiene controladores; `src/Domain/` contiene modelos y acciones; `routes/` define API/web; `database/migrations/`, `factories/` y `seeders/` definen persistencia y datos.

## Verificación de instalación, calidad y arranque

| Comprobación inicial | Resultado | Evidencia |
|---|---|---|
| Laravel / rutas | Pasa | `php artisan --version` informó Laravel Framework 13.17.0; `php artisan route:list --json` produjo 109 rutas. |
| Validación Composer | Pasa | `composer validate --no-check-publish`: `./composer.json is valid`. |
| Sintaxis PHP | Pasa | `find src routes database -type f -name '*.php' -print0 \| xargs -0 -n1 php -l`: todos los archivos inspeccionados informaron `No syntax errors detected`. |
| Tests | Falla por entorno | `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test`: 18 tests, 2 pasan y 16 errores `could not find driver` por falta de PDO SQLite, antes de migraciones. No se leyó ni modificó una base con datos. |
| Build JS | Falla por instalación ausente | `npm run build`: `vite: command not found`; no hay dependencias instaladas ni lock npm. |
| Auditoría Composer | Falla de seguridad | `composer audit --locked --no-dev`: 18 advisories en 4 paquetes; 1 en Laravel, 6 Guzzle, 10 CommonMark, 1 Flysystem. Incluye avisos altos. Versiones bloqueadas: Laravel 13.17.0, Guzzle 7.12.3, CommonMark 2.8.2 y Flysystem 3.35.1. |
| Arranque HTTP | No verificado | El sandbox impide abrir sockets TCP/Unix; no se pudo iniciar PostgreSQL ni se pudo hacer una petición HTTP local. El comando de rutas sí pudo arrancar Laravel en CLI. |
| Instalación limpia / migración desde cero | No verificado | El vendor está presente; la suite falla por falta de driver SQLite y no hay instancia temporal de PostgreSQL accesible. |

## Persistencia: tablas y relaciones observadas en migraciones/modelos

- Identidad: `users` referencia un rol primario; `role_user` expresa roles múltiples; `roles` cataloga los roles. `personal_access_tokens` guarda tokens Sanctum. `password_reset_tokens` existe, pero no tiene flujo HTTP de recuperación. `sessions` es infraestructura Laravel.
- Transporte: `drivers` pertenece a `users`; `driver_licenses` y `daily_attendances` pertenecen a conductor. `vehicles` contiene unidades y `vehicle_legal_documents` sus documentos.
- Solicitudes/viajes: `mobilization_requests` pertenece al solicitante y guarda aprobadores; `route_sheets` enlaza una solicitud (única), vehículo y conductor. Una solicitud tiene `passenger_manifests`, `request_status_histories`, documentos y una hoja. Las hojas enlazan paradas, actas, combustible, evaluaciones y compensación.
- Inspecciones: `delivery_reception_acts` enlaza hoja y usuario que inspecciona; `act_checklist_details` enlaza acta/componente; `act_tire_states` enlaza acta.
- Operación: `service_stations` tiene `fuel_orders`; `issue_logs` enlaza vehículo, conductor informante y opcionalmente hoja; `workshop_work_orders` enlaza vehículo, novedad y usuarios responsable/supervisor; `work_order_supply_provisions` enlaza órdenes e insumos.
- Finanzas y soporte: `rate_configurations`, `driver_compensations` (una por hoja), `trip_evaluations`, `system_logs`, `alerts`, `alert_reads`, `generated_documents` y `document_signatures`.
- Infraestructura Laravel: `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.
- Las migraciones usan claves foráneas en la mayor parte de relaciones y decimales para tarifas, pagos y combustible. No hay restricción única visible para participante por solicitud ni evaluación por pasajero/viaje; el alta de vales no tiene unicidad por hoja. No se ejecutó `migrate:fresh` por la falta de motor de prueba disponible.

## Servicios externos

- No se encontró uso de `Http::`, Guzzle, notificaciones externas ni clientes AWS en los módulos revisados.
- `config/services.php` define credenciales opcionales para Postmark, Resend, SES y Slack, pero el código no las consume.
- `config/filesystems.php` ofrece un disco S3 opcional, pero no está instalada la dependencia del adaptador AWS; el disco usado por documentos es `local` privado con enlaces de descarga autorizados.
- La aplicación almacena coordenadas de paradas/destinos; no se encontró integración activa con Google Maps, geocodificación o proveedor de rutas.
- El correo del ejemplo usa `MAIL_MAILER=log`; no hay flujos de correo verificados.

## Datos personales almacenados

La base almacena nombres, apellidos, cédula, correo, facultad/unidad, cargo y hash de contraseña (`users`); tipo y vigencia de licencia, puntos, contrato y disponibilidad (`drivers`, `driver_licenses`); asistencia; origen, destino, dirección/coordenadas, motivo, fechas, pasajeros, costos y responsables de aprobación (`mobilization_requests`, `passenger_manifests`); ubicación GPS y notas de paradas; combustible y pagos; novedades y datos de taller; importes y URL de comprobantes de compensación; documentos firmados, huella/hash, firma manuscrita como imagen, cédula dentro del payload firmado e IP de firma; IP y texto de acciones de auditoría. Las respuestas Eloquent completas pueden exponer más campos de los necesarios; ver BE-05 y BE-14.

## Protección y configuración observada

`APP_DEBUG=false` aparece en `.env.example`, CORS lee una lista configurable, Sanctum emite tokens con expiración configurada a 480 minutos, login/registro tienen `throttle:10,1`, las contraseñas usan hash Laravel y `SecurityHeaders` se agrega globalmente. El registro está apagado por defecto en producción. No se encontró configuración propia que fuerce HTTPS; el despliegue deberá terminar TLS en proxy/servidor y confiar correctamente sus cabeceras. No se encontró estrategia de respaldo/restauración ni política de rotación de llaves.
