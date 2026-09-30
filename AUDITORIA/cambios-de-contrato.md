# Cambios de contrato de API

Registro para coordinación con la auditoría del frontend.

| Fecha | Endpoint/campo | Cambio | Motivo | Estado |
|---|---|---|---|---|
| 2026-09-29 | — | Ningún contrato ha cambiado en la línea base de auditoría. | Revisión previa a las correcciones. | — |
| 2026-09-29 | Todas las rutas autenticadas `/api/*` y `POST /api/login` | Login rechaza cuentas inactivas con el mismo mensaje genérico de credenciales inválidas; tokens existentes de cuentas inactivas reciben HTTP 403 y JSON `{status: "error", message: "La cuenta no está activa."}`. | Impedir que una cuenta desactivada siga usando sesiones/tokens. | Backend corregido; falta prueba HTTP real con DB local y revisar el consumidor frontend. |
| 2026-09-29 | `PUT/PATCH /api/admin/usuarios/{id}` | Cuando Secretaría cambia el rol primario o activa/desactiva una cuenta, los tokens previos de ese usuario se revocan. El campo `role_id` reemplaza el rol primario y conserva roles secundarios. | Evitar permisos residuales por pivote desactualizado y sesiones vigentes tras cambios de acceso. | Backend corregido; revisar en la auditoría del frontend cómo se informa al usuario afectado que debe iniciar sesión otra vez. |
