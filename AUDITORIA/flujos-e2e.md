# Resultado de flujos de extremo a extremo

Ejecución inicial: `env APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= php artisan test`. Resultado real: 18 tests, 2 pasan y 16 terminan antes de migrar con `could not find driver` (falta `pdo_sqlite`). No se usó la base `tesis` del `.env`, no se ejecutaron semillas ni migraciones sobre una base persistente. El sandbox también bloqueó iniciar una base temporal por sockets. Los flujos marcados No verificado no se consideran aprobados.

| Flujo | Pasos esperados/código revisado | Resultado | Evidencia | Commit |
|---|---|---|---|---|
| 1. Registro e inicio de sesión | Registro con rol permitido → login → token → `/me` → logout; cuenta inactiva y rotación de token | No verificado | Hay tests de cuentas demo, pero fallan antes de BD. Login y registro limitan a 10/min; no hay recuperación/cambio de contraseña ni verificación de correo. | N/A |
| 2. Solicitud, aprobaciones, asignación y rechazo/reasignación | Solicitud interna/externa → Secretaría → Vicerrectorado si externa → hoja → respuesta conductor → reasignación | No verificado | `DemoAccountsAndWorkflowTest` contiene casos interno y externo; ejecución bloqueada. Código confirma Secretaría seguida de Vicerrectorado para externa y endpoint de reasignación para conductor rechazado. | N/A |
| 3. Invitación y evaluación | Invitar estudiante → responder → viaje finalizado → evaluar una vez | No verificado | Existe test de invitación/respuesta, bloqueado antes de BD. Código no exige viaje finalizado al evaluar. | N/A |
| 4. Inspección, viaje, paradas, llegada, evaluación y cierre | Acta salida → viaje → paradas → acta llegada → evaluación → cierre | No verificado | Hay test parcial de viaje interno; no ejecuta llegada/cierre. La evaluación no cierra la hoja; la aprobación financiera la finaliza. | N/A |
| 5. Combustible | Emitir orden válida → despacho único → consulta/consumo | No verificado | Hay controlador y acciones, sin test de integración de emisión/despacho en el repositorio inspeccionado. Sin transacción/índice único. | N/A |
| 6. Novedad y mantenimiento | Novedad → orden abierta → vehículo no asignable → insumos/cierre → disponibilidad | No verificado | Hay test de creación de orden, bloqueado antes de BD. La creación no cambia estado del vehículo. | N/A |
| 7. Compensación/liquidación | Calcular fórmula → guardar comprobante → revisión → aprobación → confirmación/disputa | No verificado | No hay test de integración del ciclo económico ni casos borde. El cálculo usa `float`; la revisión/aprobación corresponde al grupo de Secretaría. | N/A |
| 8. Documentos, firma y auditoría | Generar/subir → permisos de lectura/descarga → firmar → detectar modificación → reportes/logs | No verificado | Tests existentes comprueban generación PDF y dos slots de firma en código, pero no se pudieron ejecutar. La verificación no contrasta el hash del PDF descargado. | N/A |

Los tests existentes con cobertura parcial se encuentran en `tests/Feature/DemoAccountsAndWorkflowTest.php` y `tests/Feature/InstitutionalReportsAndDashboardTest.php`. No hay evidencia de pruebas HTTP por cada rol ni de los ocho flujos completos.
