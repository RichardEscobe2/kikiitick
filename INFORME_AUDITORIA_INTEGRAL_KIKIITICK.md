# INFORME CONSOLIDADO DE AUDITORÍA TÉCNICA - KIKIITICK v2.5

> Auditoría integral realizada sobre el backend (Laravel 13 / PHP 8.3 / Sanctum), la capa de arquitectura, concurrencia y base de datos, y el frontend (Vue 3 / Composition API). Cada dominio fue auditado aplicando las guías, checklists y reglas definidas en `.agents/skills/` (`laravel-owasp-security`, `laravel-security-audit`, `laravel-architecture`, `laravel-database-optimization`, `laravel-development`, `vue-best-practices`, `vue-debug-guides`) junto con las reglas de negocio (`RN-#`) definidas en `claude.md`.

## Matriz de Riesgo Global

| Dominio | Calificación | Hallazgos Alto/Crítico | Hallazgos Medio | Hallazgos Bajo/Informativo |
|---|---|---|---|---|
| **1. Seguridad Backend (OWASP & Sanctum)** | 🔴 **ALTO RIESGO** | 1 (Crítica) + 2 (Alta) | 2 | 2 |
| **2. Arquitectura, Concurrencia y Base de Datos** | 🟡 **REQUIERE ATENCIÓN** | 5 (Alto) | 4 | 3 |
| **3. Frontend & Reactividad (Vue 3)** | 🟡 **REQUIERE ATENCIÓN** | 5 (Alto) | 6 | 5 |

**Nota de hallazgo compartido:** el objeto de usuario persistido en `localStorage['usuario_kikiitick']` y nunca limpiado al cerrar sesión ([resources/js/Views/Login.vue:91](resources/js/Views/Login.vue#L91), [resources/js/composables/useAuth.js:28-42](resources/js/composables/useAuth.js#L28-L42)) aparece **tanto en el informe de Seguridad (§1, MEDIA) como en el de Frontend (§3, ALTO)** — es el mismo defecto raíz visto desde dos ángulos (exposición de datos vs. arquitectura de estado), no un hallazgo duplicado independiente. El parche es el mismo en ambos informes.

**Resumen ejecutivo global:** el proyecto tiene un único hallazgo que domina el perfil de riesgo — el escalamiento de privilegios no autenticado en `POST /api/registro` (§1, Crítica) — que por sí solo obliga a calificar el backend como Alto Riesgo, pese a que el resto de los controles de seguridad (inyección, CSRF, autenticación, subida de archivos) están sólidamente implementados. Los dominios de arquitectura/BD y frontend no presentan vulnerabilidades de seguridad, pero sí varios puntos que fallarán primero bajo concurrencia real o crecimiento de datos (condición de carrera en RN-01, índices faltantes, componente monolítico de mapa de asientos) y deben resolverse antes de escalar el volumen de usuarios concurrentes.

---

## 1. Auditoría de Seguridad Backend (OWASP & Sanctum)

> **Detección de stack:** No se detectó React/Inertia.js (no existe `HandleInertiaRequests`, no hay archivos `.tsx`/`.jsx`, no hay dependencias `inertiajs/*`) — se aplica el checklist Laravel OWASP completo (`laravel-owasp-security` + `laravel-security-audit`, `.agents/skills/`). El frontend es una SPA Vue 3 + Sanctum (cookies stateful), por lo que las verificaciones R1–R6 (pensadas para Inertia/React) se adaptaron a Vue: XSS vía `v-html`, exposición de datos en `localStorage` y paridad cliente/servidor en chequeos de rol.

### 1.1 Resumen Ejecutivo

**Calificación general: 🔴 ALTO RIESGO**

El backend cumple sólidamente la mayoría del checklist OWASP: no hay inyección SQL, no hay asignación masiva clásica, CSRF y sesiones están bien configuradas, no hay SSRF ni inyección de comandos, y la subida de archivos está correctamente validada. Sin embargo, persiste una **falla crítica de escalamiento de privilegios no autenticado** en `POST /api/registro` que permite crear una cuenta `admin` sin ninguna autorización previa — esto por sí solo eleva el riesgo global a **Alto**, independientemente de lo bien resuelto que esté el resto del checklist.

| Categoría OWASP | Veredicto |
|---|---|
| A01 Broken Access Control | 🔴 FAIL (crítico) / 🟡 parcial (organizador) |
| A02 Cryptographic Failures | ✅ PASS (con nota menor) |
| A03 Injection (SQL/Mass Assignment) | ✅ PASS |
| A05 Security Misconfiguration | 🟠 FAIL (headers) / 🟡 (cookie secure) |
| A06 Vulnerable Components | ✅ PASS |
| A07 Authentication Failures | ✅ PASS |
| A08 CSRF / Deserialization | ✅ PASS |
| A09 Logging & Monitoring | 🟠 FAIL |
| A10 SSRF / Cmd Injection / Uploads | ✅ PASS |
| XSS/exposición de datos (Vue) | 🟡 parcial (localStorage) |

### 1.2 Hallazgos Críticos / Vulnerabilidades

#### 🔴 CRÍTICA — Escalamiento de privilegios no autenticado (Admin Account Takeover)
**Archivo:** [app/Http/Controllers/AuthController.php:34-54](app/Http/Controllers/AuthController.php#L34-L54) · **OWASP:** A01:2021 Broken Access Control (también API5:2023) · **Regla violada:** RN-02

`POST /api/registro` ([routes/web.php:23](routes/web.php#L23), sin `auth:sanctum`, solo `throttle:5,1`) valida:
```php
'rol' => 'nullable|in:admin,organizador,cliente',   // línea 38
```
y lo persiste tal cual (`$rol = $validated['rol'] ?? 'cliente';` línea 41 → `User::create(['rol' => $rol, ...])` línea 50), siendo `rol` un campo `$fillable` ([app/Models/User.php:29](app/Models/User.php#L29)).

**Impacto:** cualquier atacante anónimo puede registrarse con `"rol":"admin"`, verificar el OTP recibido por correo y obtener acceso total a `/api/admin/*`, anulando por completo `EnsureUserIsAdmin`. Contraste: `registerOrganizador()` sí fuerza `rol = 'cliente'` correctamente.

#### 🟠 ALTA — Ausencia total de cabeceras de seguridad HTTP
**Archivo:** [bootstrap/app.php:14-22](bootstrap/app.php#L14-L22) · **OWASP:** A05:2021 Security Misconfiguration

No existe ningún middleware que establezca `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`, `Strict-Transport-Security`, `Referrer-Policy` ni `Permissions-Policy` — búsqueda exhaustiva en `app/`, `bootstrap/`, `config/` sin coincidencias. **Impacto:** la aplicación queda expuesta a clickjacking (sin `X-Frame-Options`/`frame-ancestors`), MIME-sniffing, y sin defensa en profundidad ante XSS.

#### 🟠 ALTA — Ausencia de auditoría de acciones administrativas
**Archivo:** [app/Http/Controllers/AdminController.php](app/Http/Controllers/AdminController.php) (`cambiarRol` 25-39, `destroy` 42-50, `cambiarContrasena` 53-76, `aprobarOrganizador`/`rechazarOrganizador` 94-129) · **OWASP:** A09:2021 Security Logging and Monitoring Failures

Ninguna acción administrativa sensible queda registrada (no existe `activity_log` ni `Observer`). Combinado con el hallazgo crítico anterior, una toma de control administrativa no dejaría rastro forense.

#### 🟡 MEDIA — Rutas `/api/organizador/*` sin middleware de rol dedicado
**Archivo:** [routes/web.php:69-86](routes/web.php#L69-L86) · **OWASP:** A01:2021 / API5:2023

Solo exigen `auth:sanctum`; el control de rol/propiedad se hace ad hoc dentro de cada método (`TeatroController.php:17`, `EventoController.php:23-27,55-58,80-82`). Verificado que hoy funciona correctamente, pero sin la red de seguridad de un middleware, es fácil que un futuro endpoint la omita.

#### 🟡 MEDIA — Cookie de sesión sin flag `Secure` configurado
**Archivo:** `.env` (falta `SESSION_SECURE_COOKIE`) → [config/session.php:172](config/session.php#L172) · **OWASP:** A05:2021 / A07:2021

`SESSION_SECURE_COOKIE` no está definido → resuelve a `null` (falsy). Aceptable en `local` sobre HTTP, pero **debe forzarse a `true` antes de cualquier despliegue en HTTPS**, ya que Laravel no lo infiere automáticamente desde `APP_ENV`. Además se confirmó `APP_DEBUG=true` / `APP_ENV=local` en el `.env` actual — debe verificarse `false`/`production` en el entorno real desplegado (si ya está así en producción, no es un hallazgo; si no, es una fuga de stack traces).

#### 🟡 MEDIA — Objeto de usuario persistido en `localStorage` y nunca limpiado al cerrar sesión
**Archivo:** [resources/js/Views/Login.vue:91](resources/js/Views/Login.vue#L91), [resources/js/composables/useAuth.js](resources/js/composables/useAuth.js) · **OWASP:** A05:2021 / equivalente a R2 (exposición de datos)

```js
localStorage.setItem('usuario_kikiitick', JSON.stringify(data.user)); // línea 91
```
Esta clave no se lee en ningún otro lugar del código (código muerto — el estado de autenticación real viene de `GET /api/user`), pero **nunca se elimina al hacer logout** (`useAuth.js` solo pone `user.value = null`). En una computadora compartida, el nombre/correo/rol del último usuario queda expuesto indefinidamente, y amplía la superficie de un eventual XSS. *(Ver también §3.2, hallazgo Alto — misma raíz, vista desde arquitectura de estado frontend.)*

#### 🟢 BAJA — Comparación no segura ante temporización del código OTP
**Archivo:** [app/Http/Controllers/AuthController.php:179,259](app/Http/Controllers/AuthController.php#L179) · **OWASP:** A02:2021

`if ($usuario->codigo_verificacion !== $request->codigo)` usa comparación estándar de PHP en lugar de `hash_equals()`. Riesgo real bajo (6 dígitos, latencia de red domina), pero es el patrón recomendado por OWASP para comparaciones de tokens.

#### 🟢 BAJA — `baseURL` de Axios hardcodeado a HTTP local
**Archivo:** [resources/js/bootstrap.js:6-7](resources/js/bootstrap.js#L6-L7) · **OWASP:** A05:2021

`axios.defaults.baseURL = 'http://localhost:8000'` combinado con `withCredentials = true`, sin usar `import.meta.env.VITE_API_URL`. Riesgo de despliegue si el bundle de producción se compila sin sobrescribir este valor. *(Ver también §3.2 — este archivo resultó ser código muerto: nunca se importa.)*

#### ✅ Controles verificados sin hallazgos (checklist completo aplicado)
- **A02/A03:** hashing con `Hash::make()`+cast `'hashed'`; sin MD5/SHA1; `APP_KEY` presente; cero inyección SQL; cero mass assignment; `$fillable` explícito en los 10 modelos.
- **A06:** `laravel/framework ^13.8` (instalado 13.20), Sanctum 4, Vue 3.5, Vite 8 — todo vigente, nada abandonado.
- **A07:** `session()->regenerate()` tras login y tras verificación OTP; `logout()` invalida sesión y rota token CSRF; `EncryptCookies` activo; `http_only=true`, `same_site=lax`; throttling en todas las rutas de auth.
- **A08:** CSRF activo en grupo `web` sin exclusiones; cero `unserialize`/`eval`/`extract($request`.
- **A10:** cero SSRF, cero inyección de comandos; único upload (`EventoController.php:350-359`) valida `image|mimes:jpeg,png,jpg,webp|max:4096` con nombre de archivo aleatorio (sin path traversal).
- **CORS:** `allowed_origins` explícito, sin comodín, con `supports_credentials: true`.
- **Frontend Vue:** cero `v-html`/`eval`/`innerHTML`; cero secretos hardcodeados; cero `console.log` de datos sensibles; chequeos de rol en el cliente correctamente respaldados en el servidor para `/api/admin/*`.
- **RN-05:** todas las FK hacia usuarios apuntan correctamente a `usuarios` (incluye una corrección histórica ya aplicada).

### 1.3 Plan de Remediación Directa

#### Parche 1 — Bloquear escalamiento de privilegios (CRÍTICO)
```diff
--- a/app/Http/Controllers/AuthController.php
+++ b/app/Http/Controllers/AuthController.php
@@ public function register(Request $request)
         $validated = $request->validate([
             'nombre'     => 'required|string|max:255',
             'correo'     => 'required|string|email|max:255|unique:usuarios,correo',
             'contrasena' => 'required|string|min:8',
-            'rol'        => 'nullable|in:admin,organizador,cliente',
         ]);

-        $rol = $validated['rol'] ?? 'cliente';
-        $estatusOrganizador = ($rol === 'organizador') ? 'pendiente' : 'ninguno';
+        $rol = 'cliente';
+        $estatusOrganizador = 'ninguno';
```
*(El registro de organizadores ya tiene su propio endpoint dedicado y seguro, `registerOrganizador()`; la promoción a `admin` debe ocurrir exclusivamente vía `AdminController::cambiarRol`, protegido por `auth:sanctum` + `admin`.)*

#### Parche 2 — Middleware de cabeceras de seguridad (ALTO)
Nuevo archivo `app/Http/Middleware/SecureHeaders.php`:
```php
<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecureHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
```
```diff
--- a/bootstrap/app.php
     ->withMiddleware(function (Middleware $middleware) {
         $middleware->statefulApi();
+        $middleware->append(\App\Http\Middleware\SecureHeaders::class);
         $middleware->alias([
             'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
         ]);
     })
```

#### Parche 3 — Auditoría de acciones administrativas (ALTO)
Crear migración/modelo `AdminActionLog` (`admin_id`, `accion`, `usuario_objetivo_id`, `detalles`, `created_at`) y registrar en cada método mutador de `AdminController`, p. ej.:
```diff
--- a/app/Http/Controllers/AdminController.php
+++ b/app/Http/Controllers/AdminController.php
@@ public function cambiarRol($id, Request $request)
         $usuario->rol = $validated['rol'];
         $usuario->save();
+
+        AdminActionLog::create([
+            'admin_id'            => $request->user()->id,
+            'accion'              => 'cambiar_rol',
+            'usuario_objetivo_id' => $usuario->id,
+            'detalles'            => json_encode(['rol_nuevo' => $validated['rol']]),
+        ]);
```
(Replicar en `destroy`, `cambiarContrasena`, `aprobarOrganizador`, `rechazarOrganizador`.)

#### Parche 4 — Middleware de rol para `/api/organizador/*` (MEDIA)
```diff
--- a/bootstrap/app.php
     $middleware->alias([
         'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
+        'organizador' => \App\Http\Middleware\EnsureUserIsOrganizador::class,
     ]);
```
```diff
--- a/routes/web.php
-Route::middleware(['auth:sanctum'])->prefix('organizador')->group(function () {
+Route::middleware(['auth:sanctum', 'organizador'])->prefix('organizador')->group(function () {
```
(Nuevo middleware `EnsureUserIsOrganizador` verificando `$request->user()->rol === 'organizador' && $request->user()->estatus_organizador === 'aprobado'`; los chequeos de propiedad por recurso individual dentro de cada controlador se mantienen.)

#### Parche 5 — Forzar cookie segura en producción (MEDIO)
```diff
--- a/.env.production (o variables de entorno del servidor)
+SESSION_SECURE_COOKIE=true
+APP_DEBUG=false
+APP_ENV=production
```

#### Parche 6 — Limpiar `localStorage` al cerrar sesión (o eliminar el guardado innecesario) (MEDIO)
```diff
--- a/resources/js/Views/Login.vue
-        localStorage.setItem('usuario_kikiitick', JSON.stringify(data.user));
+        // Estado de sesión manejado vía cookie httpOnly + GET /api/user; no persistir en localStorage.
```
```diff
--- a/resources/js/composables/useAuth.js
   function logout() {
     user.value = null;
+    localStorage.removeItem('usuario_kikiitick');
     window.location.href = '/login';
   }
```

#### Parche 7 — Comparación segura del OTP (BAJO)
```diff
--- a/app/Http/Controllers/AuthController.php
-        if ($usuario->codigo_verificacion !== $request->codigo) {
+        if (! hash_equals((string) $usuario->codigo_verificacion, (string) $request->codigo)) {
```
(Aplicar en `verificarCodigo` línea 179 y `resetPassword` línea 259.)

#### Parche 8 — `baseURL` de Axios por variable de entorno (BAJO)
```diff
--- a/resources/js/bootstrap.js
-axios.defaults.baseURL = 'http://localhost:8000';
+axios.defaults.baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';
```

---

## 2. Auditoría de Arquitectura, Concurrencia y Base de Datos

### 2.1 Resumen Ejecutivo

**Calificación general: 🟡 REQUIERE ATENCIÓN**

La capa de servicios muestra buenas prácticas puntuales (bloqueo pesimista con `lockForUpdate()`, inserción masiva en `SeatGeneratorService::generarAsientosParaTeatro`, propagación correcta de excepciones fuera de las transacciones), pero **RN-01 no está enforced dentro del servicio que ejecuta la operación destructiva**, lo que constituye una condición de carrera real (TOCTOU) sobre inventario ya vendido. A esto se suman índices compuestos ausentes en las columnas más calientes del sistema (disponibilidad de asientos) y controladores que absorben lógica de negocio que debería vivir en servicios. Nada de esto es catastrófico hoy con bajo volumen, pero son exactamente los puntos que fallan primero bajo concurrencia real (varios usuarios comprando boletos del mismo evento a la vez).

| Categoría | Veredicto |
|---|---|
| Transacciones cortas / sin efectos secundarios dentro | ✅ Cumple |
| Locking pesimista en checkout | ✅ Cumple |
| RN-01 (bloqueo de regeneración con inventario comprometido) | 🔴 Falla — no autoprotegida en el servicio |
| Retry ante deadlocks | 🔴 Falla (ambos servicios) |
| Índices en columnas de estado/disponibilidad | 🔴 Falla (`asientos_evento.estado` sin índice) |
| Integridad referencial FK → `usuarios` (RN-05) | ✅ Cumple (con una nota en el rollback) |
| Controladores delgados | 🟡 Parcial — `CompraController` es el modelo correcto; los demás no |
| Patrones N+1 de escritura | 🟡 Parcial (bulk insert bien en un servicio, mal en otro) |

### 2.2 Hallazgos Técnicos & Inconsistencias

#### 🔴 ALTO — RN-01 no autoprotegida + condición de carrera (TOCTOU)
**Archivo:** [app/Services/SeatGeneratorService.php:16-65](app/Services/SeatGeneratorService.php#L16-L65) · **Patrón violado:** regla de negocio fuera del dominio que la posee

`generarAsientosParaTeatro()` borra incondicionalmente todos los asientos del recinto:
```php
// SeatGeneratorService.php:20
$teatro->asientos()->delete();
```
Sin ningún guard interno. La única protección existe en el llamador:
```php
// TeatroController.php:108-119
$tieneInventarioComprometido = DB::table('asientos_evento')
    ->join('asientos', 'asientos_evento.asiento_id', '=', 'asientos.id')
    ->where('asientos.teatro_id', $teatro->id)
    ->whereIn('asientos_evento.estado', ['reservado', 'vendido'])
    ->exists();

if ($tieneInventarioComprometido) {
    return response()->json([...], 409);
}
```
**Riesgo concreto:** (1) cualquier otro caller (job, `tinker`, futuro endpoint) que invoque el método estático directamente destruye historial de ventas sin control alguno; (2) el check y el `delete()` no comparten transacción ni lock — entre el `exists()` del controlador y el `delete()` del servicio, una compra concurrente puede reservar un asiento que luego es borrado (race condition real bajo carga).

#### 🔴 ALTO — Índices compuestos ausentes en el hot path de disponibilidad
**Archivo:** [database/migrations/2026_07_22_200243_add_layout_and_seats_tables.php:39,41](database/migrations/2026_07_22_200243_add_layout_and_seats_tables.php#L39-L41) · **Patrón violado:** `index-composite-indexes` (laravel-database-optimization)

`asientos_evento.estado` y `asientos_evento.reservado_hasta` no tienen índice propio ni compuesto — solo existe `unique(['evento_id','asiento_id'])` (línea 44), que no cubre `estado`. Mismo patrón en `boletos_evento.stock_disponible`, `eventos.estatus`/`fecha_hora`, `ventas.estatus_pago`.

**Riesgo concreto:** la consulta más frecuente del sistema — "¿qué asientos están disponibles para este evento?" (`WHERE evento_id=? AND estado='disponible'`) — y el futuro barrido de expiración de bloqueos RN-03 (`WHERE estado='reservado' AND reservado_hasta < NOW()`) hacen table scan completo sobre `asientos_evento` a medida que crece la tabla.

#### 🔴 ALTO — Sin retry ante deadlocks en transacciones con locking pesimista
**Archivo:** [app/Services/CompraService.php:39,101](app/Services/CompraService.php#L39-L101), [app/Services/SeatGeneratorService.php:18](app/Services/SeatGeneratorService.php#L18) · **Patrón violado:** `lock-deadlock-retry`

Los tres `DB::transaction()` del sistema se invocan con un solo argumento (el closure), sin `attempts:`. Dado que `reservarAsientos()` y `procesarCompra()` usan `lockForUpdate()` explícitamente para resolver checkouts concurrentes (comentario propio en el código: *"RN-08: lockForUpdate() evita que dos requests de checkout concurrentes procesen la misma reserva dos veces"*, `CompraService.php:113-114`), un deadlock transitorio bajo alta concurrencia hoy revienta la request del usuario en vez de reintentarse automáticamente.

#### 🔴 ALTO — Controladores no delgados: lógica de negocio y transacciones fuera de la capa de servicios
**Archivos:** [TeatroController.php:187-275](app/Http/Controllers/TeatroController.php#L187-L275) (`storeZona`, 89 líneas), [EventoController.php:266-345](app/Http/Controllers/EventoController.php#L266-L345) (`getMapaEvento`, 80 líneas), más `DB::table()` crudo en `TeatroController.php:109,175,290` y `EventoController.php:163` · **Patrón violado:** "Thin controllers — delegate business logic to services" (laravel-architecture)

`storeZona()` hace autorización, validación, lógica de rango de filas, dos consultas de existencia, cálculo de aforo, y envuelve su propio `DB::transaction()` — todo inline. `CompraController` es el único de los cinco controladores que sigue el patrón correcto (constructor-inyecta el servicio, valida, delega, mapea la respuesta HTTP):
```php
// CompraController.php:12-45 — patrón correcto a replicar
public function __construct(private CompraService $compraService) {}

public function reservarAsientos(Request $request) {
    $validated = $request->validate([...]);
    try {
        $resultado = $this->compraService->reservarAsientos(...);
        return response()->json($resultado, 200);
    } catch (CompraException $e) { ... }
}
```
**Riesgo concreto:** acoplamiento — la lógica de zonas/precios no es reutilizable ni testeable de forma aislada, y cada controlador reimplementa su propia gestión de transacciones sin consistencia.

#### 🔴 ALTO — Escritura sin transacción en `registerOrganizador()`
**Archivo:** [app/Http/Controllers/AuthController.php:64-116](app/Http/Controllers/AuthController.php#L64-L116) · **Patrón violado:** atomicidad de escrituras relacionadas

```php
$usuario = User::create([...]);        // línea 86
$teatro = Teatro::create([...]);       // línea 96
SeatGeneratorService::generarAsientosParaTeatro($teatro);
```
Sin `DB::transaction()`. **Riesgo concreto:** si `Teatro::create()` o la generación de asientos falla (p. ej. violación de constraint, timeout), queda un `User` con rol `organizador` huérfano, sin recinto asociado y sin rollback.

#### 🟡 MEDIO — Patrones N+1 de escritura en `CompraService`
**Archivo:** [app/Services/CompraService.php:71-83,140-147,176-178,214-230](app/Services/CompraService.php#L71-L230) · **Patrón violado:** `eloquent-query-builder-hot-paths` / bulk insert

Cuatro sitios distintos ejecutan una consulta/escritura **por iteración** dentro de las transacciones de checkout (`updateOrCreate()` por asiento, `DetalleVenta::create()` por detalle, `BoletoEvento::where(...)->first()` por zona, `ZonaTeatro::find()` + `Acceso::create()` por asiento) — justo lo opuesto al patrón que el propio `SeatGeneratorService::generarAsientosParaTeatro` ya implementa correctamente con `Asiento::insert($asientosAInsertar)` masivo ([:60-63](app/Services/SeatGeneratorService.php#L60-L63)). **Riesgo concreto:** cada asiento/detalle extra en un carrito extiende linealmente cuánto tiempo se mantienen los locks de `lockForUpdate()` abiertos, aumentando la ventana de contención con otros checkouts concurrentes.

#### 🟡 MEDIO — Listados sin restricción de columnas
**Archivos:** [TeatroController.php:33-46](app/Http/Controllers/TeatroController.php#L33-L46), [EventoController.php:17-32](app/Http/Controllers/EventoController.php#L17-L32), [AdminController.php:83-91](app/Http/Controllers/AdminController.php#L83-L91) · **Patrón violado:** `query-select-columns`

`getSolicitudesOrganizador()` trae el `User` completo por cada organizador pendiente sin `->select()` — a diferencia de `AdminController::index()` que sí restringe columnas correctamente (`select('id','nombre','correo','rol','created_at')`, [:15-22](app/Http/Controllers/AdminController.php#L15-L22)). `TeatroController::index()` serializa recinto + zonas + todos los asientos sin restricción, un payload pesado en recintos grandes.

#### 🟡 MEDIO — `SeatGeneratorService::inicializarAsientosEvento()` sin `DB::transaction()`
**Archivo:** [app/Services/SeatGeneratorService.php:70-96](app/Services/SeatGeneratorService.php#L70-L96) — Inconsistente con el otro método del mismo archivo (que sí usa `DB::transaction()`, línea 18). Riesgo bajo hoy (es un solo `upsert()` atómico) pero rompe el patrón consistente del archivo.

#### 🟡 MEDIO — `down()` de la migración de reparación RN-05 reintroduce el bug
**Archivo:** [database/migrations/2026_07_29_050000_fix_asientos_evento_usuario_foreign_key.php:35-39](database/migrations/2026_07_29_050000_fix_asientos_evento_usuario_foreign_key.php#L35-L39) — el rollback recrea la FK apuntando a `users` en vez de `usuarios`. Una trampa latente si alguna vez se ejecuta `migrate:rollback` sobre esta migración.

#### 🟢 BAJO — Notas informativas (sin patch prioritario)
- `sessions.user_id` ([0001_01_01_000000_create_users_table.php:32](database/migrations/0001_01_01_000000_create_users_table.php#L32)) sin FK — tabla `users` estándar de Laravel no usada por el dominio, almacena IDs de `usuarios` sin constraint.
- Inconsistencia de nomenclatura `estado` (asientos_evento) vs. `estatus` (eventos, ventas, accesos, usuarios).
- `/api/eventos` vive como closure con `DB::table()->join()` crudo en `routes/web.php:34-40`, fuera de cualquier controlador — mismo anti-patrón de capas, un nivel más afuera.
- `buscarEventoAutorizado()` ([EventoController.php:37-48](app/Http/Controllers/EventoController.php#L37-L48)) tiene `$with = []` por defecto — frágil ante un futuro caller que itere relaciones sin pasarlo explícitamente.

#### ✅ Controles verificados sin hallazgos
- RN-05: todas las FK activas (`up()`) apuntan correctamente a `usuarios`; nombres de tabla en plural snake_case consistentes; FKs no relacionadas a usuario correctamente indexadas vía `foreignId()->constrained()`.
- Transacciones de `CompraService`/`SeatGeneratorService` contienen exclusivamente operaciones de BD — cero `Mail::`/`Http::`/`dispatch(`/`Storage::` dentro de los closures.
- Excepciones (`CompraException`) se lanzan sin capturar dentro de la transacción y solo se atrapan en el controlador — rollback correcto garantizado.
- `CompraService`/`SeatGeneratorService` no dependen de `Request` — reciben solo tipos de dominio (aislamiento HTTP correcto).
- `TeatroController::index()`/`EventoController::index()`/`getMapaEvento()` precargan relaciones correctamente (`->with()`) — sin N+1 de lectura.

### 2.3 Plan de Remediación Directa

#### Parche 1 — Autoproteger RN-01 dentro del servicio (cierra el race condition)
```diff
--- a/app/Services/SeatGeneratorService.php
+++ b/app/Services/SeatGeneratorService.php
@@ public static function generarAsientosParaTeatro(Teatro $teatro): void
     {
-        DB::transaction(function () use ($teatro) {
+        DB::transaction(function () use ($teatro) {
+            $tieneInventarioComprometido = DB::table('asientos_evento')
+                ->join('asientos', 'asientos_evento.asiento_id', '=', 'asientos.id')
+                ->where('asientos.teatro_id', $teatro->id)
+                ->whereIn('asientos_evento.estado', ['reservado', 'vendido'])
+                ->lockForUpdate()
+                ->exists();
+
+            if ($tieneInventarioComprometido) {
+                throw new \App\Exceptions\InventarioComprometidoException(
+                    'No se puede modificar la distribución física del recinto: existen asientos reservados o vendidos.'
+                );
+            }
+
             $teatro->asientos()->delete();
             // ... resto del método
-        });
+        }, attempts: 3);
     }
```
```diff
--- a/app/Http/Controllers/TeatroController.php
+++ b/app/Http/Controllers/TeatroController.php
@@ public function update($id, Request $request)
-        $tieneInventarioComprometido = DB::table('asientos_evento')
-            ->join('asientos', 'asientos_evento.asiento_id', '=', 'asientos.id')
-            ->where('asientos.teatro_id', $teatro->id)
-            ->whereIn('asientos_evento.estado', ['reservado', 'vendido'])
-            ->exists();
-
-        if ($tieneInventarioComprometido) {
-            return response()->json([...], 409);
-        }
+        // Guard ahora vive en SeatGeneratorService::generarAsientosParaTeatro()
+        try {
+            SeatGeneratorService::generarAsientosParaTeatro($teatro);
+        } catch (InventarioComprometidoException $e) {
+            return response()->json(['message' => $e->getMessage()], 409);
+        }
```

#### Parche 2 — Migración de índices compuestos
```php
Schema::table('asientos_evento', function (Blueprint $table) {
    $table->index(['evento_id', 'estado']);
    $table->index(['estado', 'reservado_hasta']);
});
Schema::table('boletos_evento', function (Blueprint $table) {
    $table->index(['evento_id', 'stock_disponible']);
});
Schema::table('eventos', function (Blueprint $table) {
    $table->index(['teatro_id', 'estatus']);
});
Schema::table('ventas', function (Blueprint $table) {
    $table->index(['usuario_id', 'estatus_pago']);
});
```

#### Parche 3 — Retry ante deadlocks
```diff
--- a/app/Services/CompraService.php
-        DB::transaction(function () use (...) { ... });
+        DB::transaction(function () use (...) { ... }, attempts: 3);
```
(Aplicar a las 3 transacciones: `reservarAsientos`, `procesarCompra`, y el Parche 1 en `SeatGeneratorService`.)

#### Parche 4 — Extraer lógica de zonas/precios a servicios
```diff
--- a/app/Http/Controllers/TeatroController.php
+++ b/app/Http/Controllers/TeatroController.php
     public function storeZona($teatroId, Request $request)
     {
-        // ... 89 líneas de validación + rango + DB::transaction inline
+        $validated = $request->validate([...]);
+        return response()->json(
+            $this->zonaService->crearZona($teatroId, $validated), 201
+        );
     }
```
Nuevo `app/Services/ZonaService.php` recibiendo `$teatroId` + array validado, encapsulando el cálculo de rango/aforo y el `DB::transaction()`. Mismo tratamiento para `EventoController::guardarPrecios()` → `PricingService::guardarPrecios()`.

#### Parche 5 — Transacción en `registerOrganizador()`
```diff
--- a/app/Http/Controllers/AuthController.php
+++ b/app/Http/Controllers/AuthController.php
-        $usuario = User::create([...]);
-        $teatro = Teatro::create([...]);
-        SeatGeneratorService::generarAsientosParaTeatro($teatro);
+        [$usuario, $teatro] = DB::transaction(function () use ($validated) {
+            $usuario = User::create([...]);
+            $teatro = Teatro::create([...]);
+            SeatGeneratorService::generarAsientosParaTeatro($teatro);
+            return [$usuario, $teatro];
+        }, attempts: 3);
```

#### Parche 6 — Bulk insert en `CompraService`
```diff
--- a/app/Services/CompraService.php
-        foreach ($asientoIds as $asientoId) {
-            AsientoEvento::updateOrCreate([...], [...]);
-        }
+        AsientoEvento::upsert(
+            $filasParaReservar,           // array construido antes del loop
+            ['evento_id', 'asiento_id'],
+            ['estado', 'reservado_por_usuario_id', 'reservado_hasta']
+        );

-        foreach ($detalles as $detalle) {
-            DetalleVenta::create([...]);
-        }
+        DetalleVenta::insert($filasDetalles);
```
(Mismo tratamiento para `emitirAccesos()`: precomputar `ZonaTeatro::whereIn('id', $zonaIds)->get()->keyBy('id')` antes del loop, y `Acceso::insert($filasAccesos)` en vez de `create()` por fila.)

#### Parche 7 — Restringir columnas en listados
```diff
--- a/app/Http/Controllers/AdminController.php
-        $solicitudes = User::with('teatros')->where('estatus_organizador', 'pendiente')->orderBy('id', 'desc')->get();
+        $solicitudes = User::select('id', 'nombre', 'correo', 'estatus_organizador', 'created_at')
+            ->with('teatros')
+            ->where('estatus_organizador', 'pendiente')
+            ->orderBy('id', 'desc')
+            ->get();
```

#### Parche 8 — Consistencia transaccional
```diff
--- a/app/Services/SeatGeneratorService.php
     public static function inicializarAsientosEvento(Evento $evento): void
     {
+        DB::transaction(function () use ($evento) {
             // ... upsert existente
-        });
+        }, attempts: 3);
     }
```

#### Parche 9 — Corregir `down()` de la migración RN-05
```diff
--- a/database/migrations/2026_07_29_050000_fix_asientos_evento_usuario_foreign_key.php
     public function down(): void
     {
         Schema::table('asientos_evento', function (Blueprint $table) {
             $table->dropForeign(['reservado_por_usuario_id']);
-            $table->foreign('reservado_por_usuario_id')->references('id')->on('users')->nullOnDelete();
+            $table->foreign('reservado_por_usuario_id')->references('id')->on('usuarios')->nullOnDelete();
         });
     }
```

---

## 3. Auditoría de Frontend & Reactividad (Vue 3 / Composition API)

### 3.1 Resumen Ejecutivo

**Calificación general: 🟡 REQUIERE ATENCIÓN**

El código cumple de forma ejemplar los fundamentos básicos exigidos por el checklist: **100% Composition API con `<script setup>`, cero uso de Options API, cero `v-html`, cero promesas sin manejar, y claves `:key` estables en todos los `v-for` auditados** — incluyendo el temporizador de reserva de 5 minutos (RN-03), que se limpia correctamente en los tres escenarios posibles (expiración, pago exitoso, desmontaje). Sin embargo, se detectó un archivo de configuración crítico (`bootstrap.js`) que nunca se importa —código muerto que debería estar activando `withCredentials`/`baseURL` para Sanctum—, una fuga de datos de sesión en `localStorage` nunca limpiada al cerrar sesión, y el componente más pesado de la app (`EventoDetail.vue`, 617 líneas) concentra mapa de asientos + compra + temporizador sin dividirse, con lookups de selección sin memoizar que degradan el rendimiento en recintos grandes.

| Categoría | Veredicto |
|---|---|
| Composition API / `<script setup>` | ✅ Cumple (100%) |
| Manejo de promesas async (try/catch) | ✅ Cumple |
| Claves `:key` en `v-for` | ✅ Cumple |
| Limpieza de temporizadores (RN-03) | ✅ Cumple |
| Configuración de Axios activa | 🔴 Falla (`bootstrap.js` código muerto) |
| Limpieza de `localStorage` al logout | 🔴 Falla |
| Componentización (mega componente) | 🔴 Falla (`EventoDetail.vue`) |
| Memoización de cómputos costosos | 🟡 Parcial |
| Separación estado/efectos en composables | 🟡 Parcial |
| Duplicación de lógica de rol | 🟡 Parcial |

### 3.2 Hallazgos Técnicos & Inconsistencias

#### 🔴 ALTO — `bootstrap.js` nunca se importa: la configuración de Axios es código muerto
**Archivo:** [resources/js/bootstrap.js](resources/js/bootstrap.js), [resources/js/app.js:1-8](resources/js/app.js#L1-L8) · **Patrón violado:** configuración de aplicación antes del montaje (`configure-app-before-mount`)

`app.js` solo hace `createApp(App).use(router).mount('#app')` — nunca importa `bootstrap.js`. Confirmado por grep exhaustivo: ningún archivo del proyecto importa `bootstrap.js`. Todo su contenido queda sin ejecutar:
```js
// bootstrap.js — nunca se ejecuta
axios.defaults.withCredentials = true;
axios.defaults.baseURL = 'http://localhost:8000';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
```
**Riesgo concreto de UI:** cada vista importa `axios` crudo del paquete npm (sin `withCredentials`) o vía el re-export de `useAuth.js` (también sin configurar). La autenticación por cookie stateful de Sanctum depende de `withCredentials: true` para que el navegador envíe la cookie de sesión en peticiones cross-origin — hoy "funciona" solo porque frontend y backend se sirven same-origin en desarrollo; en cualquier despliegue donde difieran (subdominios, CDN de assets, distinto puerto) los logins dejarían de persistir sesión sin ningún error visible obvio.

#### 🔴 ALTO — Sesión persistida en `localStorage` nunca limpiada al cerrar sesión
**Archivo:** [resources/js/Views/Login.vue:91](resources/js/Views/Login.vue#L91), [resources/js/composables/useAuth.js:28-42](resources/js/composables/useAuth.js#L28-L42) · **Patrón violado:** fuente única de verdad del estado de sesión

```js
// Login.vue:91
localStorage.setItem('usuario_kikiitick', JSON.stringify(data.user));
```
```js
// useAuth.js:28-42 — logout() nunca hace removeItem
const logout = async () => {
  try { await axios.post('/api/logout'); }
  finally {
    user.value = null;
    loading.value = false;
    window.location.href = '/login';
  }
};
```
**Riesgo concreto de UI:** esta clave no se lee en ningún otro lugar del código (confirmado — es escritura muerta, el estado real viene de `GET /api/user`), pero persiste indefinidamente tras cerrar sesión, incluyendo en equipos compartidos, exponiendo nombre/correo/rol del último usuario. *(Mismo hallazgo raíz que §1.2, MEDIA.)*

#### 🔴 ALTO — `EventoDetail.vue` es un "mega componente" (617 líneas)
**Archivo:** [resources/js/Views/EventoDetail.vue](resources/js/Views/EventoDetail.vue) · **Patrón violado:** regla de split de `vue-best-practices` ("3+ distinct UI sections" → dividir)

Un solo archivo combina: renderizado del mapa de asientos en **dos modos** (SVG arena, líneas 127-184; grilla cartesiana, líneas 186-236), filtro/leyenda de zonas (53-97), resumen de compra (259-317), y lógica + UI del temporizador de cuenta regresiva (246-257, 556-577) — sin un solo subcomponente importado.

**Riesgo concreto de UI:** cualquier cambio al flujo de compra obliga a tocar el mismo archivo que renderiza miles de nodos de asiento, aumentando el riesgo de romper el mapa al modificar el checkout (y viceversa); también dificulta testear cada parte de forma aislada.

#### 🔴 ALTO — Lookups de selección de asientos sin memoizar
**Archivo:** [EventoDetail.vue:509-511](resources/js/Views/EventoDetail.vue#L509-L511) (`estaSeleccionado`), [:373-396](resources/js/Views/EventoDetail.vue#L373-L396) (`obtenerZonaDeAsiento`) · **Patrón violado:** "derive everything possible with `computed`" (vue-best-practices §Reactivity)

```js
const estaSeleccionado = (asientoId) => {
  return asientosSeleccionados.value.some(a => String(a.id) === String(asientoId));
};
```
Invocada hasta 4 veces por asiento directamente en el template (líneas 154, 163-165, 175, 213-215, 218) — no es un `computed`, es una función plana. **Riesgo concreto de UI:** cada clic sobre un asiento dispara reactividad en `asientosSeleccionados`, y Vue re-evalúa esta función `.some()` lineal para **cada** asiento visible del grid — trabajo O(asientos × seleccionados) por clic. En un recinto grande (cientos/miles de asientos), esto se nota como lag perceptible al seleccionar/deseleccionar asientos.

#### 🔴 ALTO — Sin watcher de cambio de ruta en `EventoDetail.vue`
**Archivo:** [EventoDetail.vue:610-612](resources/js/Views/EventoDetail.vue#L610-L612) · **Patrón violado:** re-fetch de datos al cambiar parámetros de ruta

```js
onMounted(() => { cargarMapa(); });
// No existe: watch(() => route.params.id, () => cargarMapa())
```
**Riesgo concreto de UI:** si Vue Router reutiliza esta instancia de componente al navegar entre `/eventos/1` y `/eventos/2` (comportamiento estándar de Vue Router cuando la ruta coincide con el mismo componente), `cargarMapa()` nunca se vuelve a ejecutar — el usuario vería el mapa de asientos, zonas, y el temporizador activo del **evento anterior** superpuestos sobre la URL del nuevo evento.

#### 🟡 MEDIO — `useAuth.js` mezcla estado de UI con efectos secundarios
**Archivo:** [resources/js/composables/useAuth.js:10-52](resources/js/composables/useAuth.js#L10-L52) · **Patrón violado:** guía de composables (separar lógica de features de componentes presentacionales)

Un único composable-singleton (estado a nivel de módulo, líneas 7-8) devuelve simultáneamente refs bindeables al template (`user`, `loading`, `isAuthenticated`, `userRole`) y funciones con I/O de red + navegación de browser (`fetchUser`, `logout` con `window.location.href`), sin separar "qué es estado" de "qué es acción". También tiene un import muerto (`import router from '../router'`, línea 3, nunca usado — y riesgo latente de import circular ya que `router/index.js` importa `useAuth` de vuelta).

#### 🟡 MEDIO — Lógica de verificación de rol duplicada en 5+ lugares
**Archivos:** [Navbar.vue:63,72](resources/js/Components/Navbar.vue#L63), [router/index.js:81,87,118](resources/js/router/index.js#L81), [Login.vue:98-100](resources/js/Views/Login.vue#L98-L100), [AdminUsuarios.vue:140-142,402](resources/js/Views/AdminUsuarios.vue#L140-L142) · **Patrón violado:** "keep state predictable: one source of truth, derive everything else"

`useAuth.js` solo expone `userRole` crudo (línea 12); ningún lugar centraliza `isAdmin`/`isOrganizador`. Cada consumidor reimplementa `=== 'admin'`/`=== 'organizador'` de forma independiente. **Riesgo concreto de UI:** si mañana se agrega un nuevo rol o se renombra uno existente, hay que encontrar y actualizar 5+ sitios distintos — alto riesgo de que alguno quede desactualizado y muestre/oculte UI incorrectamente.

#### 🟡 MEDIO — `setTimeout` sin limpieza en `ResetPasswordView.vue`
**Archivo:** [ResetPasswordView.vue:219-221](resources/js/Views/ResetPasswordView.vue#L219-L221) · **Patrón violado:** `cleanup-side-effects` (vue-debug-guides)

```js
setTimeout(() => { irAlLogin(); }, 2500);
// Sin onUnmounted / clearTimeout correspondiente
```
Si el usuario navega manualmente (botón "Ir al inicio de sesión ahora") antes de los 2.5s, el callback sigue programado y se ejecuta después de desmontado el componente. Hoy inofensivo por idempotencia de `router.push`, pero es exactamente el patrón de fuga que el skill de debugging marca como riesgo.

#### 🟡 MEDIO — Botón CTA sin funcionalidad en `EmptyState.vue`
**Archivo:** [EmptyState.vue:10-13](resources/js/Components/EmptyState.vue#L10-L13) · Sin `@click` ni `defineEmits` — UI muerta, el padre no tiene forma de reaccionar al clic.

#### 🟡 MEDIO — Archivo huérfano `VerificarCodigoView.vue` (0 bytes)
**Archivo:** [resources/js/Views/VerificarCodigoView.vue](resources/js/Views/VerificarCodigoView.vue) — completamente vacío; el router usa `VerifyCode.vue`. Trampa de mantenimiento por nombre casi idéntico.

#### 🟡 MEDIO — Petición duplicada en el arranque
**Archivo:** [App.vue:12-14](resources/js/App.vue#L12-L14), [router/index.js:99-104](resources/js/router/index.js#L99-L104) — `App.vue` llama `fetchUser()` sin `await` en `onMounted`, en paralelo con el guard de navegación que también la llama; dos `GET /api/user` concurrentes en la carga inicial (no rompe nada, pero es red desperdiciada).

#### 🟢 BAJO — Notas menores
- Import inconsistente de `axios` (directo del paquete vs. re-export de `useAuth.js`) entre vistas — agravado por el hallazgo Alto de `bootstrap.js`.
- URL de placeholder hardcodeada y duplicada literal en [EventCard.vue:8](resources/js/Components/EventCard.vue#L8) y [Organizador.vue:216](resources/js/Views/Organizador.vue#L216).
- [EventCard.vue:41-45](resources/js/Components/EventCard.vue#L41-L45) navega directamente con `router.push` en vez de emitir un evento — fuga de responsabilidad en lo que debería ser un componente presentacional puro.
- [Footer.vue:3](resources/js/Components/Footer.vue#L3) — año de copyright hardcodeado (`© 2026`) en vez de derivarlo con `new Date().getFullYear()`.
- [EventCard.vue:35-37](resources/js/Components/EventCard.vue#L35-L37) — `defineProps({ evento: Object })` sin `required` ni validación de forma.

#### ✅ Controles verificados sin hallazgos
- Cero Options API en todo `resources/js` (grep exhaustivo `export default {`).
- Cero `v-html` en el árbol completo.
- Todas las llamadas axios auditadas envueltas en try/catch/finally.
- Claves `:key` estables (por `id`) en absolutamente todos los `v-for`/grids auditados.
- Temporizador RN-03 en `EventoDetail.vue` limpiado correctamente en expiración, pago exitoso y `onUnmounted`.
- `AdminUsuarios.vue` maneja errores de acciones administrativas de forma consistente, sin dejar la UI en estado inconsistente.
- Sin pérdida de reactividad por destructuring (el codebase usa solo `ref()`, nunca `reactive()`, de forma consistente).

### 3.3 Plan de Remediación Directa

#### Parche 1 — Activar la configuración de Axios
```diff
--- a/resources/js/app.js
+++ b/resources/js/app.js
 import { createApp } from 'vue';
+import './bootstrap';
 import App from './App.vue';
 import router from './router';
 import '../css/app.css';
```
```diff
--- a/resources/js/bootstrap.js
-axios.defaults.baseURL = 'http://localhost:8000';
+axios.defaults.baseURL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000';
```
Y actualizar `Login.vue`/`Register.vue`/etc. para importar `axios` desde un único punto (`window.axios` o un módulo `resources/js/lib/axios.js`) en vez de importar el paquete crudo.

#### Parche 2 — Limpiar `localStorage` en logout
```diff
--- a/resources/js/Views/Login.vue
-        localStorage.setItem('usuario_kikiitick', JSON.stringify(data.user));
+        // Estado de sesión manejado vía cookie httpOnly + GET /api/user
```
```diff
--- a/resources/js/composables/useAuth.js
   const logout = async () => {
     try { await axios.post('/api/logout'); }
     finally {
       user.value = null;
       loading.value = false;
+      localStorage.removeItem('usuario_kikiitick');
       window.location.href = '/login';
     }
   };
```

#### Parche 3 — Dividir `EventoDetail.vue`
Extraer a:
- `resources/js/Components/SeatMap.vue` — recibe `asientos`, `zonas`, `seleccionados` como props, emite `@toggle-seat`.
- `resources/js/composables/useReservationTimer.js` — encapsula `intervalTimer`/`tiempoRestante` con su propio `onUnmounted`.
- `EventoDetail.vue` queda como orquestador: carga datos, compone `<SeatMap>` + resumen de compra + timer.

#### Parche 4 — Memoizar selección de asientos
```diff
--- a/resources/js/Views/EventoDetail.vue
-const estaSeleccionado = (asientoId) => {
-  return asientosSeleccionados.value.some(a => String(a.id) === String(asientoId));
-};
+const idsSeleccionados = computed(
+  () => new Set(asientosSeleccionados.value.map(a => String(a.id)))
+);
+const estaSeleccionado = (asientoId) => idsSeleccionados.value.has(String(asientoId));
```

#### Parche 5 — Watcher de cambio de ruta
```diff
--- a/resources/js/Views/EventoDetail.vue
+import { watch } from 'vue';
+
 onMounted(() => { cargarMapa(); });
+watch(() => route.params.id, () => cargarMapa());
```

#### Parche 6 — Helpers de rol centralizados
```diff
--- a/resources/js/composables/useAuth.js
   const userRole = computed(() => user.value?.rol || user.value?.role || 'cliente');
+  const isAdmin = computed(() => userRole.value === 'admin');
+  const isOrganizador = computed(() => userRole.value === 'organizador');
   return {
-    user, loading, isAuthenticated, userRole, fetchUser, logout
+    user, loading, isAuthenticated, userRole, isAdmin, isOrganizador, fetchUser, logout
   };
```
Reemplazar `userRole === 'admin'` por `isAdmin` en `Navbar.vue`, `router/index.js`, `Login.vue`, `AdminUsuarios.vue`.

#### Parche 7 — Limpiar el `setTimeout`
```diff
--- a/resources/js/Views/ResetPasswordView.vue
+import { onUnmounted } from 'vue';
+let redirectTimer = null;
 ...
-        setTimeout(() => { irAlLogin(); }, 2500);
+        redirectTimer = setTimeout(() => { irAlLogin(); }, 2500);
+
+onUnmounted(() => { if (redirectTimer) clearTimeout(redirectTimer); });
```

#### Parche 8 — Contrato de evento en `EmptyState.vue`
```diff
--- a/resources/js/Components/EmptyState.vue
+<script setup>
+const emit = defineEmits(['cta-click']);
+</script>
+
 <button
+  @click="emit('cta-click')"
   class="px-5 py-2.5 bg-indigo-600 ...">
```

#### Parche 9 — Eliminar archivo huérfano
```diff
- resources/js/Views/VerificarCodigoView.vue  (eliminar, 0 bytes, no referenciado)
```

---

*Fin del informe consolidado. Ninguno de los parches presentados en las secciones 1, 2 y 3 fue aplicado al código — todos quedan pendientes de revisión y aprobación explícita antes de su implementación.*
