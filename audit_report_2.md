# KikiiTick v2.5 — Code Quality & Architecture Audit (Pre-Module 5)

## [CRITICAL FOR MODULE 5]

* **`app/Http/Controllers/CompraController.php`** (Lines 65, 84-86) + **`resources/js/Views/EventoDetail.vue`** (Line 560): Reservation lock is hardcoded to 10 minutes (`addMinutes(10)`, `tiempo_limite_seg: 600`) on the backend and `tiempoRestante.value = 600` on the frontend. This directly contradicts **RN-03**, which mandates a strict **5-minute** pessimistic lock. Both layers are internally consistent with each other but violate the documented business rule — booking capacity will hold seats twice as long as intended, which is a hard blocker before extending this engine.

* **`app/Http/Controllers/CompraController.php`** (Lines 115-124) + **`resources/js/Views/EventoDetail.vue`** (Line 244): `procesarCompra` reads the caller's active reservation without `lockForUpdate()`, and the corresponding "Confirmar Pago" button has no `:disabled`/loading guard (unlike the reservation button at line 304, which does). A double-click or two near-simultaneous requests can both pass the reservation-validity check before either flips `estado` to `vendido`, risking duplicate `Venta` records and a double stock decrement on the same seats.

* **`app/Services/SeatGeneratorService.php`** (Lines 76-90): `inicializarAsientosEvento` loops over every physical seat of the venue and calls `AsientoEvento::firstOrCreate()` per seat — one query pair (SELECT+INSERT) per seat. For a mid-size venue (500+ seats) this is 500+ round-trips fired synchronously on every event creation. This won't scale once Module 5 introduces higher event-creation throughput.

* **`app/Http/Controllers/EventoController.php`** (Line 252, `getMapaEvento`): The seat-availability map trusts the raw `estado` column and never checks `reservado_hasta` against `now()`. Expired-but-unreleased reservations only get cleaned up opportunistically inside `CompraController::reservarAsientos`, scoped to the *specific* event someone is currently trying to reserve. A seat abandoned by a user shows as unavailable to every other browsing user until someone else attempts a new reservation on that same event — a stale false "sold out" state with no background sweep.

* **`app/Models/BoletoEvento.php`** (`stock_disponible` field) vs. **`app/Models/AsientoEvento.php`** (`estado` field): Two independent, uncoordinated sources of truth for seat availability — a per-zone counter decremented in `CompraController.php` (line 143) and a per-seat status enum. Nothing enforces they stay in sync (e.g., a rolled-back transaction, manual DB edit, or future refund flow). Drift here means either overselling or phantom sold-out zones.

## [TECHNICAL DEBT - REFACTOR SOON]

* **`app/Http/Controllers/CompraController.php`** (Lines 21-198, entire file): Locking logic, pricing/commission math, stock mutation, and sale-record creation all live directly in the controller. This violates the project's own layering convention (`/app/Services` for complex business logic, per CLAUDE.md). Should be extracted into e.g. `ReservationService`/`PurchaseService`.

* **`app/Http/Controllers/CompraController.php`** (Lines 67-79): `reservarAsientos` issues one `updateOrCreate()` per seat inside a `foreach` instead of a single bulk upsert — N write queries for an N-seat selection.

* **`app/Http/Controllers/CompraController.php`** (Lines 133-150, 168-175): `procesarCompra` runs `BoletoEvento::where(...)->firstOrFail()` inside a per-zone loop and `DetalleVenta::create()` inside a per-detail loop — repeated single-row queries instead of bulk fetch/insert.

* **`app/Http/Controllers/CompraController.php`** (Lines 88, 195): Both methods `catch (\Exception $e)` broadly and return `$e->getMessage()` verbatim to the client — leaks internal/DB exception text to the frontend and swallows the stack trace with no `Log::error()`, making production failures unauditable.

* **`app/Http/Controllers/EventoController.php`** (Lines 205-223, `guardarPrecios`): Per-item loop queries and upserts `BoletoEvento` without wrapping the operation in `DB::transaction()`. A failure mid-loop leaves prices partially updated with no rollback.

* **`app/Http/Controllers/TeatroController.php`** (Lines ~17, 31, 96, 150, 177, 265) vs **`app/Http/Controllers/EventoController.php`** (all methods): `TeatroController` duplicates the same `$user->rol !== 'admin' && ...` ownership check five separate times instead of a Policy/middleware; `EventoController` has **no** admin bypass at all, so admins cannot manage organizer events through this API. Inconsistent authorization model across two controllers governing related resources.

* **`app/Http/Controllers/AuthController.php`** (Lines 40, 94, 140, 216) and **`app/Http/Controllers/AdminController.php`** (Line 102): `Mail::to()->send()` is called synchronously despite `QUEUE_CONNECTION=database` being configured and the Mailables already using the `Queueable` trait. Every register/login/approval request blocks on live SMTP I/O with no surrounding `try/catch` — mail-server latency or downtime turns into hung or 500 responses on unrelated auth flows.

* **`resources/js/Views/Organizador.vue`** (1255 lines total; script 748-1256): Single SFC owns four unrelated CRUD domains — Recintos, Zonas, Eventos, Precios — each with its own modal/form/state block. Needs splitting into per-domain sub-components.

* **`resources/js/Views/Organizador.vue`** (10 call sites), **`resources/js/Views/EventoDetail.vue`** (3 call sites), **`resources/js/Views/Login.vue`** (1 call site): Raw `fetch()` used directly with duplicated headers/error handling, while `resources/js/composables/useAuth.js` uses `axios`. No single HTTP client/service layer for the frontend.

## [CODE SMELLS]

* **`resources/js/Views/EventoDetail.vue`** (Lines 550, 553, 568, 600, 603, 606): All user feedback — success and error alike — goes through blocking `alert()` calls instead of the inline error-banner pattern already established in `Login.vue` (`errorMsg` div).

* **`app/Http/Controllers/CompraController.php`** (Lines 65, 84-86): Reservation duration is a magic number duplicated in two places rather than a single named constant/config value — the direct root cause of the RN-03 mismatch noted above.

* **`app/Http/Controllers/EventoController.php`** (Lines 62-65, 110-113): Image upload handling (`Storage::store`, `asset()`) is duplicated inline between `store()` and `update()` rather than extracted to a helper/service.

* **`app/Models/Evento.php`** (Lines 46-49): The `asientosEvento()` relation is not indented/formatted consistently with the rest of the class — indicates the file isn't passing through a formatter/linter (Pint) before commit.
