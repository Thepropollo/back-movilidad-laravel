# Reconocimiento del backend

Inspección estática realizada el 2026-09-29 en la rama `audit/backend`. No se ejecutaron migraciones ni semillas sobre la base configurada en `.env`.

## Stack, instalación y despliegue

- PHP: el repositorio requiere `^8.3`; el runtime inspeccionado es PHP 8.5.9.
- Framework: Laravel 13.34.0 (`composer.lock`; PHP runtime PHP 8.5.9).
- ORM y migraciones: Eloquent y migraciones de Laravel.
- Base de datos: la configuración predeterminada permite SQLite; el `.env` presente declara PostgreSQL en `127.0.0.1`, base `tesis`, entorno `local`. El servidor local no estaba escuchando en el puerto 5432. No se imprimieron ni copiaron credenciales y no se conectó a esa base.
- Pruebas: el PHP inspeccionado tiene `pdo_pgsql`, pero no `pdo_sqlite`. `phpunit.xml` en el árbol de trabajo apunta a PostgreSQL local `tesis_test`; esa instancia no se usó. Para evitar tocar `.env` (`tesis`) ni esa configuración, se crearon clústeres y bases PostgreSQL desechables bajo `/tmp` con nombres `backend_audit_migrate` y `backend_audit_final_test`; las variables se pasaron explícitamente a cada comando. Suite final tras `e22c766`: 48 tests y 644 aserciones pasan. Migraciones desde cero en `backend_audit_migrate`: pasan. No se ejecutaron semillas.
- Dependencias PHP: Composer con `composer.lock`. Dependencias JS: npm con `package-lock.json`; `npm ci` y `npm run build` pasaron.
- Despliegue: proveedor y proxy sin decidir. No hay compose ni artefacto probado; se añadió `Dockerfile.example` solo como propuesta, más guía provider-neutral. Laravel ofrece `/up` como healthcheck; el servidor local respondió 200.
- Primer usuario privilegiado: el catálogo identifica a Secretaría como rol con funciones administrativas; no existe un rol administrador independiente. `php artisan app:bootstrap-secretaria` solicita los datos y la contraseña de forma interactiva, y solo crea la primera cuenta de Secretaría. El seeder de demostración se bloquea en producción; no debe usarse como bootstrap.
- Contrato API: OpenAPI 3.1 y Scalar están versionados; 84 paths y 107 operaciones coinciden por método/ruta con las operaciones API de `route:list`. Los endpoints de documentación son públicos y Scalar referencia jsDelivr. Las pruebas validan estructura, rutas y contratos señalados, pero no todos los campos y reglas de las 104 rutas. `route:list` registró 113 rutas (104 registros API y 9 web/sistema); ver [endpoints.md](endpoints.md).
- Estructura: `src/App/Http/Controllers/` contiene controladores; `src/Domain/` contiene modelos y acciones; `routes/` define API/web; `database/migrations/`, `factories/` y `seeders/` definen persistencia y datos.

## Verificación de instalación, calidad y arranque

| Comprobación inicial | Resultado | Evidencia |
|---|---|---|
| Laravel / rutas | Pasa | `php artisan --version` informa Laravel Framework 13.34.0; `php artisan route:list --json` produce 113 rutas (104 bajo `/api`). |
| Validación Composer | Pasa | `composer validate --no-check-publish`: `./composer.json is valid`. |
| Sintaxis PHP | Pasa | `find src routes database -type f -name '*.php' -print0 \| xargs -0 -n1 php -l`: todos los archivos inspeccionados informaron `No syntax errors detected`. |
| Tests | Pasa con base desechable | `APP_ENV=testing`, PostgreSQL `backend_audit_final_test` en `127.0.0.1:55433`: 48 tests, 644 aserciones, cero fallos. Se pasó la conexión explícitamente; `.env` (`tesis`) y `phpunit.xml` (`tesis_test`) no fueron usados. |
| Autorización HTTP por rol | Pasa para una ruta administrativa | `AdminRouteRoleAccessTest`: petición anónima 401; seis roles sin Secretaría 403; Secretaría 200; combinaciones multirol con/sin Secretaría 200/403. No es una matriz dinámica de todas las rutas. |
| Build JS | Pasa | `npm ci --ignore-scripts --no-audit --no-fund` instala desde lockfile y `npm run build` genera manifest y assets sin descargar fuentes externas. |
| Auditoría Composer | Pasa al cierre | La auditoría inicial detectó 18 advisories; se actualizaron paquetes dentro de sus mismas versiones mayores. `composer audit --locked --no-dev` al cierre informa cero advisories. |
| Arranque HTTP | Pasa (smoke test local) | Laravel se arrancó con configuración explícita de la BD desechable. `GET /up` 200; `/docs` redirige 302; `/scalar` 200; `/openapi.json` 200 y anuncia OpenAPI 3.1 con 84 paths. No se probó detrás de un proxy/TLS de producción. |
| Migración desde cero | Pasa en PostgreSQL desechable | Clúster local temporal, base nueva `backend_audit_migrate`: `php artisan migrate --force` completó todas las migraciones sin error. No se ejecutaron semillas. |
| Linter/formatter | No cumple | `vendor/bin/pint --test` reporta 12 archivos que no cumplen formato; se dejó sin autoformatear para evitar difundir cambios de estilo dentro de módulos funcionales en esta auditoría de seguridad. |
| Imagen Docker | No verificado | `Dockerfile.example` está marcado como propuesta y no se construyó ni se ejecutó en contenedor. |

## Persistencia: tablas y relaciones observadas en migraciones/modelos

- Identidad: `users` referencia un rol primario; `role_user` expresa roles múltiples; `roles` cataloga los roles. `personal_access_tokens` guarda tokens Sanctum. `password_reset_tokens` existe, pero no tiene flujo HTTP de recuperación. `sessions` es infraestructura Laravel.
- Transporte: `drivers` pertenece a `users`; `driver_licenses` y `daily_attendances` pertenecen a conductor. `vehicles` contiene unidades y `vehicle_legal_documents` sus documentos.
- Solicitudes/viajes: `mobilization_requests` pertenece al solicitante y guarda aprobadores; `route_sheets` enlaza una solicitud (única), vehículo y conductor. Una solicitud tiene `passenger_manifests`, `request_status_histories`, documentos y una hoja. Las hojas enlazan paradas, actas, combustible, evaluaciones y compensación.
- Inspecciones: `delivery_reception_acts` enlaza hoja y usuario que inspecciona; `act_checklist_details` enlaza acta/componente; `act_tire_states` enlaza acta.
- Operación: `service_stations` tiene `fuel_orders`; `issue_logs` enlaza vehículo, conductor informante y opcionalmente hoja; `workshop_work_orders` enlaza vehículo, novedad y usuarios responsable/supervisor; `work_order_supply_provisions` enlaza órdenes e insumos.
- Finanzas y soporte: `rate_configurations`, `driver_compensations` (una por hoja), `trip_evaluations`, `system_logs`, `alerts`, `alert_reads`, `generated_documents` y `document_signatures`.
- Infraestructura Laravel: `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`.
- Las migraciones usan claves foráneas en la mayor parte de relaciones y decimales para tarifas, pagos y combustible. No hay restricción única visible para participante por solicitud ni evaluación por pasajero/viaje; el alta de vales tampoco tiene unicidad por hoja en base de datos. Las migraciones sí se verificaron desde cero en PostgreSQL desechable; no se usó `migrate:fresh` sobre ninguna base existente.

## Servicios externos

- No se encontró uso de `Http::`, Guzzle, notificaciones externas ni clientes AWS en los módulos revisados.
- `config/services.php` define credenciales opcionales para Postmark, Resend, SES y Slack, pero el código no las consume.
- `config/filesystems.php` ofrece un disco S3 opcional, pero no está instalada la dependencia del adaptador AWS; el disco usado por documentos es `local` privado con enlaces de descarga autorizados.
- La aplicación almacena coordenadas de paradas/destinos; no se encontró integración activa con Google Maps, geocodificación o proveedor de rutas.
- La configuración Scalar del árbol de trabajo carga recursos desde jsDelivr; el correo de ejemplo usa `MAIL_MAILER=log`; no hay flujos de correo verificados.

## Datos personales almacenados

La base almacena nombres, apellidos, cédula, correo, facultad/unidad, cargo y hash de contraseña (`users`); tipo y vigencia de licencia, puntos, contrato y disponibilidad (`drivers`, `driver_licenses`); asistencia; origen, destino, dirección/coordenadas, motivo, fechas, pasajeros, costos y responsables de aprobación (`mobilization_requests`, `passenger_manifests`); ubicación GPS y notas de paradas; combustible y pagos; novedades y datos de taller; importes y URL de comprobantes de compensación; documentos firmados, huella/hash, firma manuscrita como imagen, cédula dentro del payload firmado e IP de firma; IP y texto de acciones de auditoría. Las respuestas Eloquent completas pueden exponer más campos de los necesarios; ver BE-05 y BE-14.

## Protección y configuración observada

`APP_DEBUG=false` aparece en `.env.example`, CORS lee una lista configurable, Sanctum emite tokens con expiración configurada a 480 minutos, login/registro tienen `throttle:10,1`, las contraseñas usan hash Laravel y `SecurityHeaders` se agrega globalmente. El registro está apagado por defecto en producción. No se encontró configuración propia que fuerce HTTPS; el despliegue deberá terminar TLS en proxy/servidor y confiar correctamente sus cabeceras. No se encontró estrategia de respaldo/restauración ni política de rotación de llaves.
