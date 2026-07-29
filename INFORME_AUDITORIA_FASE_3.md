# INFORME DE AUDITORÍA NIVEL 4 (CÓDIGO Y CLASES) - KIKIITICK v2.5

**Alcance:** revisión exhaustiva a nivel de código real (C4 — Code/Class level) sobre el estado actual del repositorio, sin cambios de código aplicados en esta ronda (auditoría pura). Toda evidencia cita archivo y número de línea real, verificado con `grep`/lectura directa en el momento de este informe — no se reutilizan conclusiones de informes anteriores sin re-verificar.

---

## 1. RESUMEN EJECUTIVO Y DICTAMEN NIVEL 4

- **Puntuación Global de Cumplimiento: 63%**
- **Estado de Autorización: 🟢 LUZ VERDE PARA MÓDULO 5** (con deuda técnica documentada, sin bloqueadores estructurales)

**Nota de metodología:** esta puntuación **no es la misma** que el 71% del informe de remediación anterior, y esa diferencia es intencional, no un error. El 71% medía solo la matriz de 17 reglas de negocio (RN-01 a RN-17). Este informe Nivel 4 audita, además, cuatro dimensiones de calidad de código (tamaño de controladores, atomicidad de transacciones, fuga de excepciones, higiene de frontend) que **no se auditaron con este rigor antes** y que revelan deuda real no capturada por la matriz RN. El 63% es un promedio ponderado de 5 categorías (ver desglose abajo). El veredicto es 🟢 porque **ninguno de los hallazgos nuevos es un bloqueador funcional** — son deuda de mantenibilidad y endurecimiento, no fallas que rompan dinero, asientos o integridad transaccional.

| Categoría auditada | Puntuación | Detalle |
|---|---|---|
| Matriz RN-01 a RN-17 | 71% | Sin cambios desde la última verificación (código no modificado en esta ronda) |
| Regresión Prioridad Cero (`BoletoEvento`) | 100% | Confirmado resuelto, ver §3.A |
| Rol 1 — Arquitectura (tamaño, transacciones, N+1, `Acceso`) | 62.5% | Controladores gordos siguen creciendo, ver §3.A |
| Rol 3 — Seguridad (IDOR, Sanctum, fuga de excepciones) | 50% | Fuga de excepciones real y no mitigada, ver §3.D |
| Rol 4 — Clean Code Frontend | 33.3% | `console.error` residual y `fetch()` sin migrar en 5 vistas, ver §3.C |
| **Promedio global** | **≈ 63%** | (71+100+62.5+50+33.3)/5 |

### Resumen de hallazgos por severidad

| Severidad | Cantidad | Ejemplos |
|---|---|---|
| 🔴 Bloqueador crítico | **0** | — |
| 🟠 Deuda técnica seria | 4 | `procesarCompra` de 143 líneas; fuga de excepciones sin sanear; `TeatroController@update` no atómico con la regeneración de asientos; 5 vistas Vue aún en `fetch()` |
| 🟡 Code smell | 3 | 7 `console.error` residuales fuera de `EventCard.vue`; hash de `Acceso` sin HMAC; comentario "RN-01" en vez de "RN-16" en `TeatroController.php` |

---

## 2. MATRIZ DE CUMPLIMIENTO DEFINITIVA (RN-01 a RN-17)

| RN | Estado | Archivo : Línea (evidencia exacta) |
|---|---|---|
| RN-01 (geometría) | 🟡 PARCIAL | Sin cambios — fuera de alcance de remediación previa |
| RN-02 (aprobación organizador) | 🟢 PASA | `EventoController.php:54-58` — guard verificado en `store()` |
| RN-03 (precios escalonados) | 🟡 PARCIAL | Sin cambios |
| RN-04 (OTP obligatorio) | 🟡 PARCIAL | Login ya bloquea; publicación de eventos sin 2FA |
| RN-05 (ticket hash/QR) | 🟢 PASA | `CompraController.php:204-221` (`Acceso::create` por asiento) + `app/Models/Acceso.php` completo |
| RN-06 (reserva temporal) | 🟡 PARCIAL | `CompraController.php:77` sigue en `addMinutes(10)`, contradice 5 min de `CLAUDE.md` |
| RN-07 (Sanctum roles) | 🟡 PARCIAL | `routes/web.php:47` (admin protegido) vs `routes/web.php:61` (organizador solo `auth:sanctum`, sin ability explícita) |
| RN-08 (lock concurrencia) | 🟢 PASA | `CompraController.php:139` (`->lockForUpdate()` en la lectura de `asientosValidos`) |
| RN-09 (mapa interactivo) | 🟢 PASA | `EventoController.php:266-349` sin cambios, re-verificado |
| RN-10 (campos legacy) | 🟡 PARCIAL | `codigo_demo` eliminado (confirmado, 0 coincidencias) pero `fetch()` persiste en 5 vistas — ver §3.C |
| RN-11 (estado PENDING) | 🟢 PASA | `CompraController.php:188` (`'estatus_pago' => 'pendiente'`) |
| RN-12 (aforo físico) | 🟡 PARCIAL | Sin cambios |
| RN-13 (guardas de estatus) | 🟢 PASA | `CompraController.php:37-44` (reservar) y `:126-129` (comprar) |
| RN-14 (bloqueo de borrado) | 🟢 PASA | `EventoController.php:161-172` — 409 verificado |
| RN-15 (checklist publicación) | 🟡 PARCIAL | Sin checklist preventivo; mitigado en `CompraController.php:159-165` (rechaza venta sin precio) |
| RN-16 (inmutabilidad matriz) | 🟢 PASA | `TeatroController.php:108-118` — intacto, re-verificado |
| RN-17 (auditoría interna) | 🔴 FALLA | Sin cambios — 0 coincidencias de `Observer`/`activity_log` en `app/` |

**Conteo:** 8 PASA · 8 PARCIAL · 1 FALLA → 71% (idéntico al informe anterior, código de negocio sin cambios).

---

## 3. AUDITORÍA DETALLADA DE CÓDIGO (NIVEL 4)

### A. Controladores y Capa de Servicios (Laravel)

**Regresión Prioridad Cero — RESUELTA, confirmada de nuevo:**
`grep -n "firstOrFail\|findOrFail"` sobre `EventoController.php` retorna **cero coincidencias**. El único `findOrFail()` de todo el flujo de compra es `CompraController.php:124` (`Evento::findOrFail($eventoId)`), inofensivo porque `evento_id` ya fue validado por la regla `exists:eventos,id` antes de llegar ahí — no puede lanzar `ModelNotFoundException` en la práctica. La causa real original (`BoletoEvento::firstOrFail()` en `procesarCompra`) fue reemplazada por un chequeo explícito (`CompraController.php:159-165`) que rechaza la compra con mensaje claro **sin asumir $0.00** — asumir $0.00 en el momento de cobrar habría permitido boletos gratis, un regreso encubierto sobre RN-11. `/evento/{id}` (que consume `getMapaEvento`, líneas 266-349) nunca tuvo el problema — ya usaba `?->get($id, 0.00)` con fallback seguro.

**Controladores gordos — deuda real, empeorada por las últimas correcciones:**

| Método | Líneas | Umbral (30-50) | Veredicto |
|---|---|---|---|
| `CompraController::procesarCompra` | **143** (109-251) | 🔴 casi 3x el umbral | Las correcciones de RN-05/08/11/13 se agregaron todas dentro del mismo método sin extraer un `Service` — el hallazgo original del audit_report_2.md sigue sin resolverse y ahora es más grave |
| `CompraController::reservarAsientos` | 85 (24-108) | 🔴 excede | RN-13 le sumó líneas sin refactor |
| `EventoController::getMapaEvento` | 84 (266-349) | 🔴 excede | Sin cambios, ya señalado antes |
| `TeatroController::storeZona` | 90 (187-276) | 🔴 excede | Sin cambios |
| `TeatroController::update` | 69 (93-161) | 🔴 excede | Sin cambios |
| `EventoController::store` | 62 (50-111) | 🟠 excede | Creció de ~51 a 62 líneas por el guard RN-02 |
| `EventoController::guardarPrecios` | 50 (216-265) | 🟠 al límite | — |

**Transacciones atómicas — mayormente correcto, con una brecha real:**
`DB::transaction()` está presente en: `EventoController.php:234` (guardarPrecios), `CompraController.php:47,123` (ambos métodos), `TeatroController.php:255,299` (storeZona/destroyZona), `SeatGeneratorService.php:18`. **Brecha encontrada:** `TeatroController::update()` ejecuta `$teatro->update([...])` en la línea **143** y luego `SeatGeneratorService::generarAsientosParaTeatro($teatro)` en la línea **154** — dos operaciones de escritura multi-tabla **fuera de una transacción compartida**. Si la actualización del `Teatro` tiene éxito pero la regeneración de asientos falla a mitad de camino, el recinto queda con dimensiones nuevas pero matriz de asientos vieja — un estado inconsistente. (El guard RN-16 ya impide que esto ocurra cuando hay ventas, pero el problema de atomicidad existe independientemente de eso, para recintos sin ventas.)

**N+1 — sin hallazgos nuevos:** `Evento::with(['teatro.zonas', 'boletosEvento.zonaTeatro'])` (`EventoController.php:21`), `Evento::with([...])` multi-relación (`:268`), `Teatro::with(['zonas','asientos'])` (`TeatroController.php:37,192,304`) — eager loading correcto en todos los listados. Las consultas por zona dentro del `foreach` de `procesarCompra` (una consulta por zona, no por asiento) son aceptables dado que el número de zonas por venta es siempre pequeño (típicamente 1-6).

**`app/Models/Acceso.php` — íntegro, con una recomendación de endurecimiento:**
`$fillable` cubre las 12 columnas reales de la migración `2026_07_20_052614_create_accesos_table.php`. Claves foráneas correctas: `venta_id → ventas` y `boleto_evento_id → boletos_evento`, ambas con `cascadeOnDelete()` (líneas 13-14 de la migración) — coherente con el guard RN-14 que ya impide borrar eventos con ventas antes de que esta cascada se dispare. **Hallazgo menor:** el hash en `CompraController.php:214` usa `hash('sha256', $ventaId . '-' . $asientoId . '-' . config('app.key'))` — funciona y usa `APP_KEY` como sal secreta, pero la construcción idiomática y más robusta para hashing con clave sería `hash_hmac('sha256', ...)`. No es una vulnerabilidad explotable hoy (el valor nunca se expone en texto plano fuera del sistema), pero es la práctica recomendada antes de que este hash se use para validar boletos físicamente en un torniquete/scanner real.

### B. Modelos, Migraciones e Integridad Referencial

- La corrección de la FK de `asientos_evento.reservado_por_usuario_id` (migración `2026_07_29_050000`) sigue aplicada — confirmado que no fue revertida.
- Todos los modelos (`User`, `Teatro`, `Evento`, `BoletoEvento`, `AsientoEvento`, `Asiento`, `ZonaTeatro`, `Venta`, `DetalleVenta`, `Acceso`) usan `$fillable` explícito — ninguno usa `$guarded = []`. Sin riesgo de asignación masiva.
- Comentario menor de consistencia: `TeatroController.php:108` sigue etiquetado `// 🛡️ RN-01: ...` (numeración de `CLAUDE.md`) en vez de `RN-16` (numeración de este chárter de auditoría) — mismo código, dos numeraciones distintas de reglas coexistiendo en el mismo comentario a través de las sesiones. No es un bug, es una inconsistencia documental que vale la pena unificar.

### C. Componentes Vue.js y Manejo de Estado Frontend

- **`codigo_demo`**: 0 coincidencias en todo `resources/js/` — limpieza confirmada, se mantiene.
- **`console.log`/`console.error`/`debugger` — hallazgo real, zero-tolerance violado:** la limpieza previa solo cubrió `EventCard.vue`. Un escaneo completo de `resources/js/` en esta ronda encontró **7 llamadas `console.error` residuales, no reportadas ni corregidas antes**:
  - `resources/js/Views/Home.vue:171`
  - `resources/js/Views/AdminUsuarios.vue:439, 454, 481, 506, 533, 563`
  - `resources/js/composables/useAuth.js:33`

  Todas están dentro de bloques `catch` como logging de depuración, no bloques vacíos — pero bajo el criterio explícito de tolerancia cero de esta auditoría, siguen siendo hallazgos a remediar.
- **Uniformidad HTTP (`fetch()` vs `axios`) — incompleta:** confirmado que **5 vistas siguen en `fetch()` crudo**: `Home.vue`, `Register.vue`, `RegisterOrganizadorView.vue`, `VerifyCode.vue`, `AdminUsuarios.vue`. Solo `Login.vue`, `EventoDetail.vue`, `Organizador.vue`, `ForgotPasswordView.vue`, `ResetPasswordView.vue` y `useAuth.js` usan `axios`. La estandarización de la Fase 1 nunca se completó al 100%.

### D. Seguridad, IDOR y Excepciones API

- **IDOR:** confirmado sin regresión. `buscarEventoAutorizado()` (`EventoController.php`) y `esPropietarioOAdmin()` (`TeatroController.php`) siguen gobernando `update`, `destroy`, `getPrecios`, `guardarPrecios`, `storeZona`, `destroyZona` — un organizador no puede tocar recursos de otro.
- **Sanctum en rutas admin:** `routes/web.php:47` — `['auth:sanctum', 'admin', 'throttle:60,1']`, confirmado intacto.
- **Sanctum en rutas de organizador:** `routes/web.php:61` — solo `auth:sanctum`. El rol se sigue verificando de forma implícita dentro de cada controlador (`estatus_organizador === 'aprobado'`), no vía Policy o ability declarada en la ruta. Funciona, pero es un patrón frágil: un nuevo endpoint agregado sin recordar copiar el chequeo manual quedaría desprotegido por defecto.
- **Fuga de excepciones — hallazgo real, no mitigado:** `CompraController.php:101` y `:248` retornan `response()->json(['message' => $e->getMessage()], 422)` sobre un `catch (\Exception $e)` genérico. La mayoría de los `throw new \Exception(...)` en este archivo usan mensajes deliberadamente amigables — pero el `catch` en sí **no distingue** entre esas excepciones controladas y cualquier excepción inesperada (fallo de conexión a BD, violación de constraint, error de configuración). Si algo así ocurre, el mensaje crudo de la excepción se devuelve directo al cliente en un JSON con apariencia "estructurada" (`{"message": "..."}`), pero el contenido de ese mensaje no está saneado. Esto **no es hipotético**: durante la validación funcional de la sesión anterior se observó en vivo un mensaje `SQLSTATE[23000]: Integrity constraint violation...` producido por esta misma clase de patrón al ejecutar pruebas directas contra el controlador. Recomendación: distinguir excepciones de negocio (una clase propia, p. ej. `CompraException`) de excepciones de infraestructura, y solo exponer el mensaje de las primeras.

---

## 4. CHECKLIST FINAL DE ENTRADA AL MÓDULO 5

| # | Pre-requisito | Estado |
|---|---|---|
| 1 | Sin bloqueadores de negocio críticos (RN-02, 05, 08, 11, 13, 14) | ✅ Confirmado |
| 2 | Regresión `BoletoEvento` en `/evento/{id}` resuelta | ✅ Confirmado — causa real identificada y corregida en `CompraController`, no en `EventoController` |
| 3 | Integridad referencial de `asientos_evento` hacia `usuarios` | ✅ Confirmado, migración aplicada y no revertida |
| 4 | IDOR entre organizadores | ✅ Sin hallazgos |
| 5 | Rutas admin protegidas con Sanctum + rol | ✅ Confirmado |
| 6 | Estado de pago `PENDING` disponible para integrar pasarela real | ✅ Listo — `estatus_pago` inicia en `'pendiente'`, asientos permanecen `'reservado'` hasta confirmación |
| 7 | Modelo de emisión de boletos/QR (`Acceso`) listo para conectar a webhook de pago | ✅ Listo, con recomendación de migrar el hash a `hash_hmac()` antes de producción |
| 8 | Excepciones de la API saneadas antes de manejar pagos reales | ⚠️ **Pendiente** — recomendado resolver antes de conectar una pasarela real, ya que un error de pago mal formateado no debe filtrar detalle interno al cliente |
| 9 | Controladores de compra extraídos a una capa de `Service` | ⚠️ Pendiente — `procesarCompra` (143 líneas) debería dividirse antes de agregarle lógica de pasarela de pago encima |
| 10 | Auditoría interna de cambios sensibles (RN-17) | ⚠️ Pendiente, no bloqueante para iniciar, recomendado antes de producción |

**Veredicto: 🟢 LUZ VERDE PARA MÓDULO 5.** No hay bloqueadores funcionales. Los puntos 8 y 9 son fuertemente recomendados como parte del *setup* inicial de Módulo 5 (antes de escribir la integración con la pasarela), no como condición para empezar a diseñarlo.
