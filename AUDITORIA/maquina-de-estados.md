# Máquina de estados observada

Diagramas derivados de controladores/acciones actuales. Reflejan caminos implementados, incluidos estados sin transición; no prueban funcionamiento de extremo a extremo.

## Solicitud y viaje

```mermaid
stateDiagram-v2
  [*] --> pendiente_secretaria: crear solicitud
  pendiente_secretaria --> autorizada_secretaria: Secretaría aprueba interna
  pendiente_secretaria --> pendiente_rectorado: Secretaría aprueba externa
  pendiente_secretaria --> rechazada: Secretaría rechaza
  pendiente_rectorado --> aprobado_rectorado: Vicerrectorado aprueba
  pendiente_rectorado --> rechazada: Vicerrectorado rechaza
  autorizada_secretaria --> aprobada: asignar hoja de ruta
  aprobado_rectorado --> aprobada: asignar hoja de ruta
  pendiente --> aprobada: asignación legado interna
  aprobada --> aprobada: respuesta del conductor / actas
  aprobada --> rechazada: no hay transición implementada
  aprobada --> finalizada: no se encontró transición de estado de solicitud
```

La hoja de ruta nace como `trip_status=programado`, `driver_response=pendiente`. Aceptar pone `driver_response=aceptado`, conductor no disponible y vehículo `en_viaje`; rechazar marca `rechazado`. Secretaría puede reasignar si la respuesta es `rechazado` o `pendiente`, reiniciándola a pendiente. Un acta de salida sin fallas pasa a `en_ruta`; con fallas marca vehículo `en_taller` y crea novedad, pero mantiene la hoja `programado`. Acta de llegada pasa a `pendiente_feedback`; el vehículo se libera. La aprobación financiera cambia la hoja a `finalizado` y libera conductor/vehículo. La evaluación por sí sola no finaliza la hoja. La solicitud externa requiere Secretaría y luego Vicerrectorado (`AutorizarSecretariaController`, `AprobarRectoradoController`, tests existentes).

## Asignación y respuesta del conductor

```mermaid
stateDiagram-v2
  [*] --> pendiente: hoja creada
  pendiente --> aceptado: conductor asignado acepta
  pendiente --> rechazado: conductor asignado rechaza
  rechazado --> pendiente: Secretaría reasigna
  pendiente --> pendiente: Secretaría reasigna
  aceptado --> pendiente: no permitido por endpoint reasignar
  aceptado --> viaje: acta de salida válida
  viaje --> pendiente_feedback: llegada
  pendiente_feedback --> finalizado: aprobación de compensación
```

No hay reserva transaccional ni comparación de intervalos de vehículo/conductor al crear o reasignar. La API permite comprobar banderas de disponibilidad, no cruces de horario ni capacidad de pasajeros.

## Invitación de participante

```mermaid
stateDiagram-v2
  [*] --> invitado: solicitante invita
  invitado --> aceptado: invitado acepta
  invitado --> rechazado: invitado rechaza
  aceptado --> aceptado: respuestas posteriores rechazadas por estado
  rechazado --> rechazado: respuestas posteriores rechazadas por estado
```

El endpoint verifica que quien responde sea el usuario invitado. No verifica estado/cierre de solicitud ni plazas disponibles al responder. La base no tiene índice único solicitud/usuario.

## Orden de combustible

```mermaid
stateDiagram-v2
  [*] --> emitida: Secretaría emite vale
  emitida --> despachada: despacho dentro del monto de galones
  despachada --> despachada: repetición rechazada por estado
  emitida --> anulada: no se encontró endpoint/transición
```

## Novedad y orden de taller

```mermaid
stateDiagram-v2
  state "Novedad" as issue {
    [*] --> pendiente
    pendiente --> en_revision: crear orden vinculada
    en_revision --> solventado: cerrar orden
  }
  state "Orden" as work {
    [*] --> abierta: exit_date=null
    abierta --> cerrada: descontar insumos y fijar exit_date
  }
```

La orden no tiene campo de estado; abierta/cerrada se infiere de `exit_date`. Crear orden no marca el vehículo `en_taller`; cerrar lo marca `disponible`.

## Compensación/liquidación

```mermaid
stateDiagram-v2
  [*] --> calculo: calcular, sin persistir
  calculo --> pendiente_comprobante: liquidar/guardar comprobante
  pendiente_comprobante --> verificado_movilidad: Secretaría aprueba
  pendiente_comprobante --> en_disputa: conductor disputa
  verificado_movilidad --> confirmado_conductor: conductor confirma
  en_disputa --> confirmado_conductor: conductor puede confirmar
  confirmado_conductor --> pendiente_comprobante: liquidar puede sobrescribir
```

La tabla `driver_compensations` soporta un registro por hoja; por lo implementado es un flujo denominado compensación/liquidación. Secretaría calcula, registra comprobante y aprueba; el conductor confirma o disputa. No se persiste quién calculó ni quién aprobó. No se impide que el mismo usuario haga varios pasos.

## Documento

```mermaid
stateDiagram-v2
  [*] --> generado: crear PDF
  [*] --> adjuntado: subir PDF/JPG/PNG
  generado --> firmado_parcial: firma uno o más slots
  adjuntado --> firmado_parcial: firma uno o más slots
  firmado_parcial --> firmado: todos los slots requeridos tienen firma
  generado --> verificado: verificar payload de firmas
  adjuntado --> verificado: verificar payload de firmas
```

No hay estado documental persistido: el estado mostrado se deriva de `source` y las filas de firma. La verificación no recalcula el hash del archivo actual, por lo que el diagrama “verificado” describe solo la firma del payload almacenado.
