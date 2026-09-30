# Despliegue estándar (propuesta neutral)

El proveedor y la topología siguen sin definirse. Esta guía usa PostgreSQL y variables de entorno estándar, y no presupone AWS, Render, VPS ni otro servicio concreto. No se ejecutaron migraciones sobre una instalación nueva durante esta auditoría porque no hubo un motor local desechable disponible.

## Preparación de una release

En CI o en una máquina de build limpia:

```sh
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
php artisan test
composer audit --locked --no-dev
npm audit
```

El runtime de pruebas necesita `pdo_sqlite`; los tests que dependen de bloqueos y concurrencia también deben correr contra PostgreSQL desechable. El despliegue debe guardar el `composer.lock` y `package-lock.json` de la misma revisión que el artefacto.

## Configuración y arranque

1. Aprovisiona PostgreSQL, un directorio/volumen privado y un gestor de secretos. Define `APP_ENV=production`, `APP_DEBUG=false`, una `APP_KEY` única, `DB_*`, `APP_URL`, `CORS_ALLOWED_ORIGINS`, `SANCTUM_TOKEN_EXPIRATION`, `SESSION_SECURE_COOKIE=true`, `LOG_LEVEL=warning`, correo y almacenamiento. No copies `.env` en la imagen.
2. Termina HTTPS en el proxy y configura confianza de cabeceras solo para los proxies reales. La app agrega cabeceras de seguridad, pero el TLS, los timeouts y la política del proxy dependen del entorno elegido.
3. Ejecuta las migraciones como una tarea de release tras verificar el nombre y host de la base objetivo. Usa `php artisan migrate --force`; no uses `migrate:fresh` ni ejecutes el seeder de demostración en producción. Conserva el backup y el procedimiento de rollback antes de una migración que cambie datos.
4. En el primer despliegue, ejecuta `php artisan app:bootstrap-secretaria` de forma interactiva en una consola protegida. El comando solo permite crear la primera cuenta privilegiada y no usa credenciales por defecto.
5. Arranca PHP-FPM detrás de un servidor HTTP configurado, un worker para `QUEUE_CONNECTION=database` y el scheduler si se habilitan tareas programadas. Reinicia workers en cada release y expón `/up` como healthcheck.
6. Verifica login, rol, documento privado, cola, logs, CORS y una restauración de respaldo en un entorno de aceptación antes de dirigir tráfico real.

El [Dockerfile.example](../Dockerfile.example) es una propuesta de imagen PHP-FPM más assets. No contiene servidor HTTP, proxy TLS, PostgreSQL, secretos ni volumen; no fue construido ni ejecutado en esta auditoría y requiere adaptación/pruebas en el destino elegido.

## Respaldo y restauración PostgreSQL

Programa un `pg_dump` cifrado y externo a la instancia, con retención definida por la institución y acceso restringido. Mantén claves fuera de comandos, repositorio y logs, y protege también el almacenamiento privado que contiene documentos adjuntos. Una copia de la base sin su almacenamiento puede dejar documentos huérfanos.

Ejemplo de backup usando variables inyectadas de forma segura por el entorno (no se incluyen contraseñas):

```sh
pg_dump --format=custom --no-owner --file="$BACKUP_FILE" "$PGDATABASE"
```

Restaura periódicamente en una base nueva y aislada, nunca sobre la base activa durante una prueba:

```sh
createdb "$RESTORE_DATABASE"
pg_restore --exit-on-error --no-owner --dbname="$RESTORE_DATABASE" "$BACKUP_FILE"
```

Registra fecha, revisión, resultado de integridad y duración de cada ensayo; mide el tiempo de recuperación. La frecuencia, retención y objetivos RPO/RTO requieren política institucional y no se fijaron en el código.

## Límites conocidos

- Proveedor, dominio, orígenes CORS, proxy confiable, almacenamiento durable y servicio de correo: requieren decisión.
- Este backend no contiene una receta de despliegue probada ni un entorno reproducible de PostgreSQL desechable dentro de la auditoría.
- El directorio `storage/app/private` debe persistir fuera del contenedor o trasladarse a un disco privado con adaptador configurado; el disco S3 opcional del esqueleto no está instalado ni verificado.
- `APP_KEY` y claves de firma, si se define una firma criptográfica real, deben administrarse y rotarse con un procedimiento compatible con documentos históricos.
