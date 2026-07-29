# 🛠️ KIKIITICK TECHNICAL SYSTEM AUDIT

**Fecha de auditoría:** 2026-07-28
**Alcance:** Backend Laravel 13 (PHP 8.3) + Frontend Vue 3 (Vite 8, Tailwind 4) — inspección directa de código fuente en disco.
**Metodología:** Lectura íntegra de rutas, controladores, servicios, mailers, migraciones, modelos Eloquent y componentes Vue. Sin asunciones — cada afirmación de este documento está respaldada por una ruta de archivo concreta.

## Resumen Ejecutivo

KikiiTick es una plataforma de venta de boletos (estilo Ticketmaster) con dos roles operativos (`organizador`, `admin`) y un rol de consumo (`cliente`). El backend expone una API REST autenticada con **Laravel Sanctum (modo SPA/cookie, stateful)** y persiste sobre SQLite (`database/database.sqlite`) en el entorno actual. El frontend es una SPA Vue 3 con Vue Router y un sistema de mapa de asientos renderizado en SVG/Grid dinámico según la geometría del recinto (fila×columna o anillo concéntrico).

Se identificaron **2 hallazgos críticos de integridad de datos/seguridad** y **varios hallazgos de contrato API/Frontend rotos** detallados en la Sección 5. El módulo de control de acceso físico (`accesos` / QR) existe únicamente como tabla de base de datos — no tiene ningún código de aplicación asociado.

---

## 1. Database Model & Persistence (Tables, Keys, and Critical Fields)

### 1.1 Inventario de tablas

| Tabla | Migración | Propósito | Estado |
|---|---|---|---|
| `usuarios` | `2026_07_20_052607` | Tabla de usuarios real de la app (no la `users` por defecto de Laravel) | ✅ Activa |
| `users` | `0001_01_01_000000` (scaffold Laravel) | Tabla por defecto de Laravel, **no usada** por el modelo `User` (que apunta a `usuarios`) | ⚠️ Huérfana |
| `teatros` | `2026_07_20_052608` | Recintos/venues de un organizador | ✅ Activa |
| `zonas_teatro` | `2026_07_20_052609` | Zonas de precio/proximidad dentro de un recinto | ✅ Activa |
| `asientos` | `2026_07_22_200243` | Matriz física maestra de butacas/pasillos de un recinto | ✅ Activa |
| `eventos` | `2026_07_20_052610` | Eventos programados en un recinto | ✅ Activa |
| `boletos_evento` | `2026_07_20_052611` | Tarifa (`precio_base`) y stock por zona, por evento | ✅ Activa |
| `asientos_evento` | `2026_07_22_200243` | Estado dinámico (disponible/reservado/vendido) de cada butaca física para un evento concreto | ✅ Activa |
| `ventas` | `2026_07_20_052612` | Orden de compra (cabecera) | ✅ Activa |
| `detalles_venta` | `2026_07_20_052613` | Líneas de la orden de compra, por zona/tarifa | ✅ Activa |
| `accesos` | `2026_07_20_052614` | Boleto físico/QR para validación de ingreso | ⚠️ **Solo esquema — sin modelo ni lógica de aplicación** |
| `personal_access_tokens` | `2026_07_20_160027` | Tokens de Sanctum (API tokens, no usados por el flujo web actual) | ✅ Activa (infraestructura) |
| `cache`, `jobs`, `sessions`, `password_reset_tokens` | scaffold Laravel | Infraestructura estándar del framework | ✅ Activa |

### 1.2 Campos clave y relaciones por tabla

**`usuarios`** (`App\Models\User`, `$table='usuarios'`, `SoftDeletes`, `$timestamps=false`)
| Campo | Tipo | Nota |
|---|---|---|
| `correo` | string, unique | Login identifier |
| `contrasena` | string | Hash bcrypt (cast `hashed`); `getAuthPassword()` sobreescrito |
| `rol` | enum(`admin`,`organizador`,`cliente`) default `cliente` | |
| `estatus_organizador` | enum(`ninguno`,`pendiente`,`aprobado`,`rechazado`) | Gate para permisos de `TeatroController` |
| `codigo_verificacion` | string(6) nullable | OTP de verificación / reseteo de password (reutilizado para ambos flujos) |
| `codigo_expira_en` | timestamp nullable | TTL de 10 min sobre el OTP |
| `deleted_at` | softDelete | Borrado lógico usado por `AdminController::destroy` |

**`teatros`** (`App\Models\Teatro`) — FK `usuario_id → usuarios` (cascade). Campos de geometría: `filas_totales` (int, default 10, máx. 26 por regla de negocio A-Z), `asientos_por_fila` (int, default 20), `pasillos_slots` (json, cast `array` — índices de columna reservados como pasillo), `posicion_escenario` (`arriba`|`centro`, determina el algoritmo de render en el frontend).

**`zonas_teatro`** (`App\Models\ZonaTeatro`) — FK `teatro_id → teatros` (cascade). `nivel_proximidad` enum(`0x`..`4x`), `fila_inicio`/`fila_fin` (string) definen el rango de filas de la zona, `capacidad_asientos` (calculada al momento de crear la zona, no es un límite duro), `es_numerada` boolean.

**`asientos`** (`App\Models\Asiento`) — FK `teatro_id → teatros` (cascade), FK `zona_teatro_id → zonas_teatro` (nullable, cascade). `codigo` (ej. `A1`), `slot_index` (posición física incluyendo pasillos), `tipo` enum(`asiento`,`pasillo`). **Unique constraint** `(teatro_id, fila, numero)`.

**`asientos_evento`** (`App\Models\AsientoEvento`) — FK `evento_id → eventos` (cascade), FK `asiento_id → asientos` (cascade). **Unique** `(evento_id, asiento_id)`.
| Campo | Nota |
|---|---|
| `estado` | enum(`disponible`,`reservado`,`bloqueado`,`vendido`) default `disponible` |
| `reservado_por_usuario_id` | 🔴 FK apunta a `users` (tabla huérfana), **no** a `usuarios` — ver Hallazgo Crítico #1 en Sección 5 |
| `reservado_hasta` | timestamp nullable — lock temporal de 10 min usado como mecanismo de concurrencia optimista/pesimista en `CompraController` |

**`eventos`** (`App\Models\Evento`) — FK `teatro_id → teatros` (cascade). `comision_fija_empresa` (decimal 10,2, cast `decimal:2`) — comisión fija de KikiiTick por boleto. `estatus` enum(`borrador`,`activo`,`finalizado`).

**`boletos_evento`** (`App\Models\BoletoEvento`) — FK `evento_id → eventos`, FK `zona_teatro_id → zonas_teatro` (ambas cascade). `precio_base` (decimal), `stock_disponible` (int, decrementado en `CompraController::procesarCompra`).

**`ventas`** (`App\Models\Venta`) — FK `usuario_id → usuarios`. `monto_neto`, `total_comisiones`, `monto_total` (decimales), `estatus_pago` enum(`pendiente`,`pagado`,`fallido`) — **nunca se usa `pendiente`/`fallido` en código actual**, siempre se crea directo en `pagado` (no hay integración real de pasarela de pago).

**`detalles_venta`** (`App\Models\DetalleVenta`) — FK `venta_id → ventas`, FK `boleto_evento_id → boletos_evento` (ambas cascade). Desglose por zona/tarifa de una venta.

**`accesos`** — ⚠️ Sin modelo Eloquent. Campos definidos en migración: `venta_id`, `boleto_evento_id`, `tipo_boleto`, `clave_evento`, `numero_control`, `hash_seguridad`, `token_qr` (unique), `seccion_pasillo`, `fila_palco`, `numero_asiento`, `estatus` enum(`pendiente`,`validado`,`revocado`), `escaneado_at`. Diseñada para emisión de boleto físico/QR y control de acceso el día del evento — **no implementada**.

---

## 2. Domain Services & Complex Logic (SeatGeneratorService, Mails, etc.)

### 2.1 `App\Services\SeatGeneratorService`

**`generarAsientosParaTeatro(Teatro $teatro): void`** — algoritmo **cartesiano (fila × columna)**, no radial. Se ejecuta dentro de una transacción:

1. **Borra** todos los asientos existentes del recinto (`$teatro->asientos()->delete()`).
2. Itera `filas_totales` (usa letras `A`-`Z`, fallback `F1`, `F2`... si excede 26).
3. Por cada fila, itera `asientos_por_fila + count(pasillos_slots)` "slots". Un slot es pasillo si su índice (1-based) está en `pasillos_slots`; en ese caso se le asigna `numero = -slot` (negativo, para evitar colisión con la unique key `teatro_id+fila+numero`) y `codigo = 'PASILLO'`. Si no es pasillo, incrementa un contador de asiento independiente (`numeroAsiento`).
4. Inserta todo en un solo `Asiento::insert()` masivo (bulk insert, eficiente).
5. `zona_teatro_id` siempre se crea `null` — la asignación a zona ocurre después, vía `TeatroController::storeZona` (por rango de filas).

Se invoca desde: `TeatroController::store`, `TeatroController::update` y `AuthController::registerOrganizador`.

🔴 **Riesgo crítico de integridad de datos**: como `asientos_evento.asiento_id` tiene `cascadeOnDelete()`, y `TeatroController::update` vuelve a llamar a `generarAsientosParaTeatro` (que **borra y recrea todos los asientos**) cada vez que un organizador edita la configuración de filas/pasillos del recinto, **cualquier registro histórico de reservas o ventas (`asientos_evento` con estado `vendido`/`reservado`) para eventos ya realizados en ese recinto se elimina en cascada**. No hay ninguna guarda que impida regenerar la matriz si el recinto ya tiene eventos con ventas asociadas. Ver Hallazgo Crítico #2 en Sección 5.

**`inicializarAsientosEvento(Evento $evento): void`** — crea (`firstOrCreate`) una fila `asientos_evento` en estado `disponible` por cada asiento físico (`tipo='asiento'`) del recinto del evento.

⚠️ **Código muerto**: esta función **no es invocada desde ningún controlador** (`grep` confirma cero referencias fuera de su propia definición). En la práctica el sistema funciona igual porque `EventoController::getMapaEvento` usa un fallback (`estadoAsientosMap->get($id, 'disponible')`) y `CompraController::reservarAsientos` usa `updateOrCreate`, así que la ausencia de pre-inicialización no rompe el flujo — pero la función existe sin integrar, sugiriendo una refactorización incompleta.

### 2.2 `App\Mail\CodigoVerificacionMail`

`Mailable` simple. Constructor promueve `public string $codigo` (accesible automáticamente en la vista Blade `emails.codigo_verificacion` por convención de Laravel). Reutilizado para **tres flujos distintos**: registro de cliente, registro de organizador y recuperación de contraseña — el mismo template sirve para los tres casos sin diferenciar el contexto en el asunto/cuerpo más allá de "Tu código de verificación".

### 2.3 `App\Mail\OrganizadorAprobadoMail`

Recibe la instancia completa de `User $usuario` (no solo campos primitivos — expone el modelo entero, incluyendo `estatus_organizador`, a la vista). Vista `emails.organizador_aprobado`. Método `attachments()` sobreescrito devolviendo `[]` — no-op redundante (comportamiento por defecto de `Mailable`), candidato a limpieza menor.

Ambos templates existen en `resources/views/emails/` (`codigo_verificacion.blade.php`, `organizador_aprobado.blade.php`). El transporte configurado en `.env` es SMTP vía **Mailtrap sandbox** (`MAIL_MAILER=smtp`, `sandbox.smtp.mailtrap.io`) — es decir, **ningún correo llega a bandejas reales en el estado actual**, apto solo para pruebas.

---

## 3. API Catalog & HTTP Routes

> ⚠️ Nota de arquitectura: `routes/api.php` está **vacío**. Todas las rutas de API viven en `routes/web.php` bajo `Route::prefix('api')`, por lo que **todas** llevan el middleware `web` (sesión + CSRF cookie) en vez del stack `api` — esto es consistente con el uso de Sanctum en modo SPA/stateful (cookie-based), pero es una desviación de la convención estándar de Laravel que vale la pena documentar explícitamente para cualquier desarrollador nuevo.

### 3.1 Autenticación y cuenta (`AuthController`)

| Método | Endpoint | Controller@Método | Throttle | Descripción | Estado |
|---|---|---|---|---|---|
| POST | `/api/registro` | `AuthController@register` | 5/min | Crea usuario `cliente` (o rol explícito), genera OTP 6 dígitos, envía correo | ✅ Implementado |
| POST | `/api/registro-organizador` | `AuthController@registerOrganizador` | 5/min | Crea usuario + `Teatro` inicial en una sola transacción implícita, genera matriz de asientos vía `SeatGeneratorService`, envía OTP | ✅ Implementado |
| POST | `/api/login` | `AuthController@login` | 5/min | Valida credenciales contra `usuarios.contrasena` (Hash::check), inicia sesión (`Auth::login` + regenerate) | ✅ Implementado — ⚠️ contrato de respuesta roto con frontend (ver §5) |
| POST | `/api/verificar-codigo` | `AuthController@verificarCodigo` | 5/min | Valida OTP + expiración, marca `correo_verificado_at`, autologin | ✅ Implementado |
| POST | `/api/enviar-codigo` | `AuthController@enviarCodigo` | 3/min | Reenvía OTP | ✅ Implementado |
| POST | `/api/forgot-password` | `AuthController@forgotPassword` | 3/min | Genera OTP para reseteo (reutiliza el mismo campo que verificación de cuenta) | ✅ Implementado |
| POST | `/api/reset-password` | `AuthController@resetPassword` | 5/min | Valida OTP + `confirmed`, actualiza `contrasena` | ✅ Implementado |
| POST | `/api/logout` | `AuthController@logout` | auth:sanctum | Invalida sesión y regenera token CSRF | ✅ Implementado |
| GET | `/api/user` | Closure (routes/web.php:59) | auth:sanctum | Devuelve `$request->user()` crudo | ✅ Implementado (no delega a controlador — inconsistencia menor de estilo) |

### 3.2 Administración (`AdminController`) — todas bajo throttle 60/min, **sin middleware `auth:sanctum` ni verificación de rol admin a nivel de ruta**

| Método | Endpoint | Controller@Método | Descripción | Estado |
|---|---|---|---|---|
| GET | `/api/admin/usuarios` | `@index` | Lista usuarios (id, nombre, correo, rol, created_at) | ✅ Implementado — 🔴 sin protección de acceso (ver §5) |
| PUT | `/api/admin/usuarios/{id}/rol` | `@cambiarRol` | Cambia rol de un usuario | ✅ Implementado — 🔴 sin protección |
| DELETE | `/api/admin/usuarios/{id}` | `@destroy` | Soft delete de usuario | ✅ Implementado — 🔴 sin protección |
| PUT | `/api/admin/usuarios/{id}/contrasena` | `@cambiarContrasena` | Reset de password con regex de complejidad | ✅ Implementado — 🔴 sin protección |
| GET | `/api/admin/solicitudes-organizador` | `@getSolicitudesOrganizador` | Lista usuarios con `estatus_organizador='pendiente'` + su(s) teatro(s) | ✅ Implementado — 🔴 sin protección |
| PUT | `/api/admin/organizador/{id}/aprobar` | `@aprobarOrganizador` | Promueve a `organizador`, envía correo | ✅ Implementado — 🔴 sin protección |
| PUT | `/api/admin/organizador/{id}/rechazar` | `@rechazarOrganizador` | Marca `estatus_organizador='rechazado'` | ✅ Implementado — 🔴 sin protección |

### 3.3 Recintos y zonas (`TeatroController`) — `auth:sanctum`

| Método | Endpoint | Controller@Método | Descripción | Estado |
|---|---|---|---|---|
| GET | `/api/organizador/teatros` | `@index` | Lista recintos del organizador (o todos si `admin`) | ✅ Implementado |
| POST | `/api/organizador/teatros` | `@store` | Crea recinto + valida aforo vs. matriz + genera asientos | ✅ Implementado |
| PUT | `/api/organizador/teatros/{id}` | `@update` | Actualiza recinto y **regenera matriz completa de asientos** | ✅ Implementado — 🔴 ver riesgo de cascada en §2.1/§5 |
| DELETE | `/api/organizador/teatros/{id}` | `@destroy` | Elimina recinto si no tiene eventos vinculados | ✅ Implementado |
| POST | `/api/organizador/teatros/{id}/zonas` | `@storeZona` | Crea zona sobre un rango de filas libres, reasigna `zona_teatro_id` en `asientos` | ✅ Implementado |
| DELETE | `/api/organizador/zonas/{id}` | `@destroyZona` | Elimina zona si no tiene `boletos_evento`, libera filas | ✅ Implementado |

### 3.4 Eventos y precios (`EventoController`) — `auth:sanctum` salvo `/mapa`

| Método | Endpoint | Controller@Método | Descripción | Estado |
|---|---|---|---|---|
| GET | `/api/organizador/eventos` | `@index` | Eventos de los recintos del organizador autenticado, con zonas/tarifas | ✅ Implementado |
| POST | `/api/organizador/eventos` | `@store` | Crea evento (valida propiedad del `teatro_id`), soporta upload de imagen o URL | ✅ Implementado |
| PUT | `/api/organizador/eventos/{id}` | `@update` | Actualiza evento | ✅ Implementado |
| DELETE | `/api/organizador/eventos/{id}` | `@destroy` | Elimina evento | ✅ Implementado |
| GET | `/api/organizador/eventos/{id}/precios` | `@getPrecios` | Lista zonas del recinto con su tarifa/stock actual para ese evento | ✅ Implementado |
| POST | `/api/organizador/eventos/{id}/precios` | `@guardarPrecios` | Crea/actualiza `boletos_evento` por zona (upsert) | ✅ Implementado |
| GET | `/api/eventos/{id}/mapa` | `@getMapaEvento` | **(Público, sin auth)** Devuelve evento + teatro + zonas + matriz completa de asientos con estado en tiempo real | ✅ Implementado |
| GET | `/api/eventos` | Closure (routes/web.php:34) | Lista eventos con `estatus='activo'`, join con `teatros` | ✅ Implementado (query cruda con `DB::table`, no usa el modelo `Evento`/scopes — inconsistencia de estilo) |

### 3.5 Compra de boletos (`CompraController`) — `auth:sanctum`

| Método | Endpoint | Controller@Método | Descripción | Estado |
|---|---|---|---|---|
| POST | `/api/boletos/reservar` | `@reservarAsientos` | Bloqueo temporal (10 min) de asientos con `lockForUpdate()`, limpieza de reservas expiradas inline | ✅ Implementado |
| POST | `/api/boletos/comprar` | `@procesarCompra` | Valida reserva vigente del usuario, crea `Venta`+`DetalleVenta`, decrementa stock, marca asientos `vendido` | ✅ Implementado — ⚠️ sin pasarela de pago real (siempre `estatus_pago='pagado'`), sin emisión de `accesos`/QR |

### 3.6 Infraestructura

| Método | Endpoint | Descripción |
|---|---|---|
| GET/HEAD | `/sanctum/csrf-cookie` | Endpoint estándar de Sanctum para bootstrap CSRF |
| GET/HEAD, PUT | `/storage/{path}` | Symlink de storage público (imágenes de eventos) |
| GET/HEAD | `/up` | Health check de Laravel |
| GET/HEAD | `/{any?}` | Catch-all → `view('welcome')`, sirve el shell de la SPA Vue |

---

## 4. Frontend Structure & Modules (SPA Routes, Views, Composables)

### 4.1 Rutas SPA (`resources/js/router/index.js`)

Todas las rutas cuelgan de un único layout raíz `MainLayout.vue` (Navbar + `<router-view>` + implícito sin Footer montado — `Footer.vue` existe como componente pero **no está importado/usado en `MainLayout.vue`**, componente huérfano).

| Path | Name | Componente | Guard |
|---|---|---|---|
| `/` | `Home` | `Views/Home.vue` | público |
| `/evento/:id` | `EventoDetail` | `Views/EventoDetail.vue` | público |
| `/login` | `Login` | `Views/Login.vue` | `guestOnly` |
| `/registro` | `Register` | `Views/Register.vue` | `guestOnly` |
| `/registro-organizador` | `RegisterOrganizador` | `Views/RegisterOrganizadorView.vue` | `guestOnly` |
| `/verificar-codigo` | `VerificarCodigo` | `Views/VerifyCode.vue` | `guestOnly` |
| `/forgot-password` | `forgot-password` | `Views/ForgotPasswordView.vue` | `guestOnly` |
| `/reset-password` | `reset-password` | `Views/ResetPasswordView.vue` | `guestOnly` |
| `/perfil` | `Perfil` | `Views/Perfil.vue` | `requiresAuth` |
| `/organizador` | `Organizador` | `Views/Organizador.vue` | `requiresAuth`, roles `[organizador, admin]` |
| `/admin/usuarios` | `AdminUsuarios` | `Views/AdminUsuarios.vue` | `requiresAuth`, roles `[admin]` |

El guard global (`router.beforeEach`) llama a `fetchUser()` una sola vez (controlado por `loading.value`) y evalúa `meta.requiresAuth`, `meta.guestOnly` y `meta.roles` contra `useAuth().userRole`.

⚠️ **Archivo huérfano**: `Views/VerificarCodigoView.vue` existe en disco pero está **completamente vacío** y no es importado por el router (el router usa `VerifyCode.vue`, un archivo distinto con contenido real). Basura de una refactorización previa.

### 4.2 Composables (`resources/js/composables/`)

**`useAuth.js`** — estado global compartido (fuera del setup, a nivel de módulo: `user`/`loading` como `ref` singleton, patrón de store minimalista sin Pinia/Vuex).
- `fetchUser()` — `GET /api/user`, tolera 401 como "no autenticado".
- `logout()` — `POST /api/logout`, luego `window.location.href = '/login'` (recarga completa, no navegación SPA — intencional para limpiar estado en memoria).
- `isAuthenticated`, `userRole` (computed, con fallback `user.rol || user.role || 'cliente'` — el `|| user.role` es defensivo pero innecesario ya que el backend siempre usa `rol` en español).

### 4.3 Componentes (`resources/js/Components/`)

| Componente | Uso |
|---|---|
| `Navbar.vue` | Header global con menú desplegable condicionado por `userRole` (enlaces a Perfil/Admin/Organizador), logout |
| `EventCard.vue` | Tarjeta de evento en grillas/carrusel de `Home.vue`, navega a `EventoDetail` |
| `Footer.vue` | Footer estático — **no está montado en ningún layout actualmente** |
| `EmptyState.vue` | Estado vacío genérico (CTA "¿Eres organizador?") — **no se encontró ningún `import`/uso de este componente en las Views inspeccionadas**; `Home.vue` implementa su propio estado vacío inline en vez de reutilizarlo |

### 4.4 Vistas principales (`resources/js/Views/`)

- **`Home.vue`** — catálogo público: búsqueda por texto + filtro por categoría (client-side sobre `GET /api/eventos`), carrusel horizontal con scroll nativo + toggle a grid completo.
- **`EventoDetail.vue`** (el componente más complejo del frontend, ~630 líneas) — mapa interactivo de asientos:
  - Consume `GET /api/eventos/{id}/mapa`.
  - **Dos algoritmos de render** condicionados por `teatro.posicion_escenario`:
    - `'centro'` → **SVG de arena/ring 360°**: `calcularPosicionArena()` distribuye asientos en el perímetro de un rectángulo concéntrico por fila (crecimiento simétrico `baseSize + filaIdx*gap*2`), proyectando la posición como si fuera un anillo cuadrangular alrededor de un "RING" central.
    - `'arriba'` (default) → **Grid cartesiano tradicional** con filas HTML en flexbox, escenario fijo arriba.
  - Selección de asientos client-side (`asientosSeleccionados`), cálculo de subtotal + comisión (`evento.comision_fija_empresa * cantidad`) + total.
  - Flujo de reserva → `POST /api/boletos/reservar` → temporizador local de 600s (`setInterval`, **no sincronizado con el `reservado_hasta` real del servidor** — si la pestaña se mantiene abierta más tiempo del esperado por drift de reloj/red, el timer del cliente puede desincronizarse del backend) → `POST /api/boletos/comprar`.
- **`Login.vue` / `Register.vue` / `RegisterOrganizadorView.vue`** — formularios con validación reactiva en vivo (regex de nombre solo-letras, checklist de complejidad de password). Usan `fetch` crudo (no `axios`), sin manejo de cookie CSRF explícito antes de la petición (a diferencia de `ForgotPasswordView`/`ResetPasswordView`, que sí llaman `axios.get('/sanctum/csrf-cookie')` primero) — inconsistencia de patrón entre vistas.
- **`VerifyCode.vue`** — confirma OTP, banner especial si viene `?demo=` en query string (para entorno de desarrollo).
- **`ForgotPasswordView.vue` / `ResetPasswordView.vue`** — flujo de recuperación en dos pasos, usan `axios` + interceptor de error `err.response?.data?.message`.
- **`Perfil.vue`** — vista de solo lectura del usuario autenticado (sin edición de datos).
- **`AdminUsuarios.vue`** — panel con dos pestañas: tabla de usuarios (cambio de rol inline, modal de reseteo de password, borrado lógico) y tabla de solicitudes de organizador (aprobar/rechazar). Referencias a `usuario.empresa`/`usuario.telefono` en el template de solicitudes que **no existen en el modelo `User` ni en la respuesta de `AdminController::getSolicitudesOrganizador`** — siempre se renderizarán como "No especificado".
- **`Organizador.vue`** (~1255 líneas, la vista más grande del proyecto) — panel con dos pestañas: **Mis Recintos** (CRUD de `Teatro` + modal de gestión de zonas por rango de filas) y **Mis Eventos** (CRUD de `Evento` con upload de imagen vía `FormData`, modal de precios por zona). Concentra toda la lógica de administración operativa del organizador en un solo archivo monolítico (candidato a descomposición en sub-componentes).

---

## 5. Progress Status vs. Integrator Project Roadmap

### 5.1 Hallazgos críticos (bloquean producción)

| # | Severidad | Hallazgo | Ubicación | Impacto |
|---|---|---|---|---|
| 1 | 🔴 Crítico | `asientos_evento.reservado_por_usuario_id` tiene FK `constrained('users')`, pero el modelo `User` de la aplicación usa la tabla `usuarios`. La tabla `users` es un remanente del scaffold de Laravel, vacía y no gestionada por ningún flujo de negocio. | `database/migrations/2026_07_22_200243_add_layout_and_seats_tables.php:40` | En motores que respetan integridad referencial estricta (MySQL/PostgreSQL en producción — SQLite no la fuerza por defecto), cualquier intento de reservar un asiento fallará con violación de FK, porque el `id` del usuario autenticado (de `usuarios`) casi nunca coincidirá con una fila válida en `users`. **Riesgo de romper la funcionalidad de compra completa al migrar de SQLite a MySQL/Postgres.** |
| 2 | 🔴 Crítico | `SeatGeneratorService::generarAsientosParaTeatro` borra (`delete()`) y regenera **todos** los asientos del recinto en cada `PUT /api/organizador/teatros/{id}`. Por el `cascadeOnDelete()` de `asientos_evento.asiento_id`, esto elimina en cascada el historial de reservas/ventas de asientos de **todos los eventos pasados y presentes** de ese recinto. | `app/Services/SeatGeneratorService.php:16-20`, invocado desde `app/Http/Controllers/TeatroController.php:133` | Un organizador que edite la configuración de su recinto (ej. corregir el número de pasillos) después de haber vendido boletos **destruye silenciosamente los registros de venta de asientos** sin ninguna advertencia ni bloqueo. No hay verificación de "¿tiene este recinto eventos con ventas activas?" antes de regenerar. |
| 3 | 🔴 Crítico (seguridad) | Todas las rutas de `AdminController` (gestión de usuarios, cambio de roles, aprobación de organizadores) están bajo el grupo de throttle `60,1` **sin middleware `auth:sanctum` ni verificación de rol admin**. | `routes/web.php:44-56` | Cualquier request no autenticado puede listar usuarios, cambiar roles arbitrariamente (incluido auto-promoverse a `admin` conociendo un `id`), eliminar usuarios y aprobar/rechazar organizadores. El frontend oculta el menú vía `router.meta.roles`, pero eso es solo control de UI — **la API no lo aplica**. |

### 5.2 Hallazgos altos (contrato API↔Frontend roto)

| # | Hallazgo | Ubicación |
|---|---|---|
| 4 | `Login.vue` lee `data.usuario` y `data.requiere_verificacion` de la respuesta de `POST /api/login`, pero `AuthController::login` devuelve la clave `user` (no `usuario`) y **nunca** incluye `requiere_verificacion`. Consecuencia: tras un login exitoso, `rol = data.usuario?.rol` es siempre `undefined`, por lo que el redirect basado en rol (`admin`→`AdminUsuarios`, `organizador`→`Organizador`) **nunca se activa** y todo usuario cae al `else` (`Home`), sin importar su rol real. | `resources/js/Views/Login.vue:103-110` vs `app/Http/Controllers/AuthController.php:120-123` |
| 5 | `Register.vue` y `RegisterOrganizadorView.vue` leen `data.codigo_demo` para mostrar el banner de "modo desarrollo" en `VerifyCode.vue`, pero ni `AuthController::register` ni `registerOrganizador` incluyen esa clave en su respuesta JSON. | `resources/js/Views/Register.vue:244`, `RegisterOrganizadorView.vue:389` vs `AuthController.php:42-45, 96-99` |
| 6 | `VerifyCode.vue` lee `data.error` en caso de fallo de `/api/verificar-codigo`, pero el controlador devuelve `message`. Funciona por casualidad de fallback de texto pero el mensaje específico del backend (código incorrecto vs. expirado) nunca se muestra al usuario. | `resources/js/Views/VerifyCode.vue:83` vs `AuthController.php:157-165` |
| 7 | `AdminUsuarios.vue` renderiza `solicitud.empresa` y `solicitud.telefono`, campos que no existen ni en la tabla `usuarios` ni en la respuesta de `getSolicitudesOrganizador`. Siempre se mostrará "No especificado". | `resources/js/Views/AdminUsuarios.vue:231-233` |

### 5.3 Roadmap del sistema — estado por módulo

| Módulo funcional | Estado | Notas |
|---|---|---|
| Registro / login / verificación OTP / recuperación de contraseña | 🟢 Completo (con bugs de contrato menores, §5.2) | Envío de correo funcional vía Mailtrap sandbox — no apto para producción sin cambiar proveedor SMTP |
| Aprobación de organizadores (workflow admin) | 🟢 Completo (con brecha de seguridad crítica, §5.1 #3) | |
| Gestión de recintos (CRUD + geometría de asientos) | 🟡 Funcional con riesgo de integridad (§5.1 #2) | |
| Gestión de zonas por rango de filas | 🟢 Completo | |
| Gestión de eventos (CRUD + upload de imagen) | 🟢 Completo | |
| Precios por zona/evento | 🟢 Completo | |
| Mapa interactivo de asientos (grid + arena 360°) | 🟢 Completo, UX pulida | Dos algoritmos de layout distintos según `posicion_escenario` |
| Reserva temporal de asientos (locking 10 min) | 🟢 Completo, con `lockForUpdate()` correcto a nivel DB | Timer visual en frontend no sincronizado con el servidor (drift menor) |
| Checkout / procesamiento de compra | 🟡 Funcional pero sin pasarela de pago real | `Venta` se crea siempre como `estatus_pago='pagado'`, no hay integración con Stripe/Conekta/PayPal ni webhook de confirmación |
| Emisión de boleto (QR / control de acceso) | 🔴 **No implementado** | Tabla `accesos` existe en el esquema; cero modelo, cero controlador, cero referencia en frontend. Es el gap más grande respecto a un roadmap de "venta de boletos" completo |
| Panel de reportes/ventas para organizador | 🔴 No implementado | No existe endpoint que exponga `ventas`/`detalles_venta` al organizador |
| Autorización granular por rol a nivel de API (más allá de Sanctum) | 🔴 Incompleto | Ver §5.1 #3 — falta un `Gate`/`Policy`/middleware de rol reutilizable; la verificación de rol está duplicada manualmente método a método (`verificarEstatusOrganizador()` en `TeatroController`, checks inline en `EventoController`) |
| Rutas API bajo namespace `api.php` estándar | 🔴 No usado | Todo vive en `web.php` bajo prefijo `/api` con middleware `web` |

### 5.4 Deuda técnica menor (no bloqueante)

- Componente `EmptyState.vue` y `Footer.vue` no están integrados en ninguna vista/layout activo.
- Archivo `Views/VerificarCodigoView.vue` vacío y sin uso — candidato a eliminación.
- Inconsistencia de manejo de errores en frontend: unas vistas usan `fetch` + `data.error`/`data.message` ad-hoc, otras usan `axios` + `err.response?.data?.message` — sin un cliente HTTP centralizado ni interceptor único.
- `SeatGeneratorService::inicializarAsientosEvento()` no referenciado desde ningún controlador (código muerto).
- Dos closures inline en `routes/web.php` (`/api/eventos`, `/api/user`) rompen la convención de "todo pasa por un controlador" que sigue el resto del proyecto.
- `OrganizadorAprobadoMail::attachments()` es un override no-op redundante.
