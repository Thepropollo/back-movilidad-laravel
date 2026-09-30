# Backend de movilidad universitaria

API Laravel para solicitudes de transporte, aprobaciones, asignaciones, viajes, combustible, mantenimiento, compensaciones y documentos.

## Requisitos

- PHP 8.3 o posterior, Composer 2 y extensiones `pdo_pgsql`, `mbstring`, `openssl`, `fileinfo`, `intl`, `zip`, `gd` y `bcmath`. Habilita `sodium` para firmas Ed25519; sin ella el código recurre a HMAC con `APP_KEY`, que no es una firma asimétrica ni ofrece no repudio.
- Node.js 22 o posterior y npm para compilar los recursos web.
- PostgreSQL para una instalación persistente. Las pruebas PHPUnit usan SQLite en memoria y requieren `pdo_sqlite`.

## Instalación local

```sh
composer install
npm ci
cp .env.example .env
php artisan key:generate
npm run build
```

Para desarrollo local, cambia `.env` a `APP_ENV=local`, `APP_URL=http://localhost:8000`, el origen local del frontend y `SESSION_SECURE_COOKIE=false`. Configura una base local o desechable y confirma `DB_HOST`, `DB_PORT` y `DB_DATABASE` antes de ejecutar cualquier migración. No uses una base con datos reales durante pruebas. Luego:

```sh
php artisan migrate
php artisan app:bootstrap-secretaria
php artisan serve
```

El comando de bootstrap solicita interactivamente el primer usuario de Secretaría; no hay usuario ni contraseña predeterminados. Las cuentas de demostración del seeder se bloquean en `production` y no son un procedimiento de despliegue.

## Pruebas y calidad

```sh
php artisan test
composer audit --locked --no-dev
npm audit
npm run build
```

Revisa la conexión definida en `phpunit.xml` antes de lanzar tests: debe apuntar a SQLite en memoria o PostgreSQL local desechable, nunca a producción. La suite necesita `pdo_sqlite` si usa SQLite. Los tests que ejercitan concurrencia y locking deben ejecutarse también contra una base PostgreSQL desechable antes de la puesta en marcha.

## Documentación de API

La definición OpenAPI está en [`openapi.yaml`](openapi.yaml), con versiones JSON y rutas de consulta si se habilita Scalar. Revísala frente a `php artisan route:list` y los contratos de los controladores en cada cambio. No incluyas credenciales reales ni de demostración en ejemplos publicados. Las rutas de documentación son públicas.

## Despliegue

La aplicación no está ligada a un proveedor. Configura el mismo conjunto de variables en el entorno elegido, termina TLS en un proxy confiable, restringe CORS al origen exacto del frontend, guarda documentos en almacenamiento privado persistente y usa PostgreSQL. No publiques `.env` ni credenciales en imágenes o logs.

El orden sugerido de publicación, las migraciones, el bootstrap de Secretaría, la política de respaldo/restauración y el Dockerfile ilustrativo están en [`AUDITORIA/despliegue-estandar.md`](AUDITORIA/despliegue-estandar.md) y [`Dockerfile.example`](Dockerfile.example). El Dockerfile es una propuesta que requiere build y pruebas en un entorno con Docker; no reemplaza la configuración del servidor web/FPM, TLS, secretos, almacenamiento ni procesos de cola.

Healthcheck Laravel: `GET /up`. Para operación estable configura worker de colas, reinicio ordenado de workers en cada release, límites/timeout del proxy y monitoreo de logs sin PII.
