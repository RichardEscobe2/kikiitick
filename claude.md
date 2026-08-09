# Guía Operativa de Desarrollo - KIKIITICK v2.5 (CLAUDE.md)

> **Estado del proyecto:** Módulo 5 (Pagos) en curso, Entregables 1-3 completados. Ver
> `estado_modulo5.md` para el resumen detallado de la sesión más reciente (qué se
> construyó, decisiones de arquitectura, y pendientes exactos) antes de continuar.

## 1. Contexto del Proyecto y Arquitectura
* **Nombre:** KikiiTick - Sistema de Gestión y Venta de Boletos para Eventos.
* **Patrón Arquitectónico:** Monolito Modular desacoplado (Backend API RESTful + Frontend SPA).
* **Backend:** PHP 8.3 / **Laravel 13.20** (no 11 — verificado en `composer.lock`) con autenticación Laravel Sanctum (Modo SPA / Stateful Cookie).
* **Frontend:** Vue 3 + Vite 8 + Tailwind CSS 4.
* **Base de Datos:** **MySQL real** (`DB_CONNECTION=mysql`, verificado en `.env` — ya NO corre en SQLite).
* **Correo:** SMTP vía Mailtrap (sandbox). Colas: `QUEUE_CONNECTION=database` — los correos despachados con `Mail::queue()` NO se envían hasta correr `php artisan queue:work` (no hay worker corriendo por defecto).
* **Pagos:** SDK oficial `mercadopago/dx-php` (Checkout Pro, vía redirect a `init_point`). Credenciales sandbox reales ya cargadas en `.env` (`MERCADOPAGO_ACCESS_TOKEN`/`MERCADOPAGO_PUBLIC_KEY`). `MERCADOPAGO_WEBHOOK_SECRET` sigue **vacío** — hasta configurarlo (Mercado Pago > Tus integraciones > Webhooks), el endpoint `POST /api/pagos/webhook` rechazará toda notificación real con 401. Además, en local `notification_url` se omite de la preferencia (Mercado Pago rechaza URLs no públicas como `localhost` con 400) — el webhook solo llega automáticamente con `APP_URL` pública (ver `estado_modulo5.md` §8).

## 2. Reglas de Negocio Críticas (Inviolables)
* **RN-01 (Protección de Histórico):** PROHIBIDO regenerar la matriz de asientos en `SeatGeneratorService` si el recinto posee eventos con boletos en estado 'reservado' o 'vendido'. Autoprotegida DENTRO del servicio (no solo en el controlador) con `lockForUpdate()` + `InventarioComprometidoException`.
* **RN-02 (Seguridad Administrativa):** TODOS los endpoints de `AdminController` DEBEN requerir `auth:sanctum` y verificar explícitamente el rol 'admin'. Incluye: el registro público (`POST /api/registro`) NUNCA debe permitir que el cliente auto-asigne su propio `rol` — siempre se fuerza `'cliente'` en el servidor.
* **RN-03 (Concurrencia de Reservas):** El bloqueo temporal de butacas dura 5 minutos (bloqueo pesimista vía `AsientoEvento.reservado_hasta`). Vencido el tiempo, el sistema libera las butacas a 'disponible'.
* **RN-05 (Integridad Referencial):** Toda relación de BD que apunte a usuarios DEBE referenciar a la tabla 'usuarios' (NO a 'users').
* **RN-08 (Atomicidad de Checkout):** `CompraService::procesarCompra()`/`reservarAsientos()` usan `lockForUpdate()` para evitar que dos requests concurrentes procesen la misma reserva dos veces. Todas las transacciones críticas usan `attempts: 3` para reintentar ante deadlocks.
* **RN-09 (Venta en Taquilla — RF-10):** El rol `'vendedor'` (junto con `organizador`/`admin`) puede vender boletos directo en taquilla vía `POST /api/boletos/comprar-pos`, marcando la venta como `'pagado'` de inmediato (efectivo/tarjeta física), sin pasar por la pasarela en línea.
* **RN-11 (Estados de Pago):** Una `Venta` nace en `'pendiente'` al hacer checkout en línea; solo pasa a `'pagado'` cuando el webhook de Mercado Pago confirma el pago vía `CompraService::confirmarPagoAprobado()` — re-consultando el estado real contra la API de Mercado Pago, nunca confiando en el cuerpo de la notificación ni en a qué `back_url` redirigió el navegador.
* **RN-14 (Protección de Recintos/Eventos con Venta):** Bloquear el borrado de teatros/zonas/eventos si existen boletos reservados o vendidos asociados (ver `TeatroController::destroy/destroyZona`, `EventoController::destroy`).
* **RN-13 / RN-16:** Referenciadas en comentarios de `CompraService.php` (ligadas al ciclo de vida de accesos/boletos tras el checkout) pero **sin definición formal documentada** — pendiente de aclarar con el equipo de producto antes de asumir su alcance exacto.
* **RF-12 (Validación de Acceso por QR):** Cada `Acceso` (boleto individual) tiene un `token_qr` único (UUID) usado para generar un código QR de validación en puerta — ver `ConfirmacionCompra.vue`.

## 3. Estructura de Capas y Código
* `/app/Http/Controllers`: Controladores delgados (Auth, Evento, Teatro, Compra, Admin).
* `/app/Services`: Lógica de negocio compleja desacoplada (`SeatGeneratorService`, `CompraService`, `MercadoPagoService`).
* `/app/Models`: Modelos Eloquent con relaciones explícitas (`User`->`$table='usuarios'`, Teatro, Evento, Asiento, AsientoEvento, ZonaTeatro, BoletoEvento, Venta, DetalleVenta, Acceso).
* `/app/Mail`: Mailables (`CodigoVerificacionMail`, `OrganizadorAprobadoMail`, `ConfirmacionCompraMail`).
* `/app/Console/Commands`: Comandos de mantenimiento/reparación de datos (`RepararIntegridadPrecios`).
* `/resources/js/Views`: Vistas SPA de Vue.js — `EventoLanding` (landing pública) + `EventoCheckout` (mapa/reserva/pago) reemplazan a la antigua `EventoDetail` (eliminada); además `ConfirmacionCompra`, `FichaOxxo`, `Organizador`, `AdminUsuarios`, `Perfil`.
* `/resources/js/Components/QuickAuthModal.vue` + `/resources/js/composables/useAuthModal.js`: modal de login/registro global, interceptor "frictionless" reutilizado en el flujo de compra.
* `/routes/web.php`: TODAS las rutas API viven aquí bajo `Route::prefix('api')` (nota: `/routes/api.php` existe pero está vacío/sin usar — wiring muerto conocido, no confundir).

## 4. Pendientes Conocidos (no bloqueantes, ver `estado_modulo5.md` para detalle completo)
* `AdminActionLog` (auditoría de acciones administrativas) — no implementado.
* `SESSION_SECURE_COOKIE` — debe forzarse `true` antes de cualquier despliegue real en HTTPS (hoy sin definir, entorno local).
* Refactor de controladores gordos (`TeatroController::storeZona`, `EventoController::getMapaEvento`) a Services dedicados (`ZonaService`/`PricingService`) — identificado, no aplicado.
* `useAuth.js` expone `isAdmin`/`isOrganizador`, pero `Navbar.vue`, `AdminUsuarios.vue` y `Login.vue` todavía usan comparaciones de rol inline duplicadas — no migrados.
* No existe una vista "Mis Boletos" (listado de órdenes pasadas) — el botón "Ver Mis Boletos" en `ConfirmacionCompra.vue` apunta a `Perfil` como destino provisional.
* `FichaOxxo.vue` muestra un código de barras con la referencia REAL de la orden en KikiiTick, no un voucher OXXO emitido por Mercado Pago (eso requeriría integrar su Payments API con `payment_method_id: 'oxxo'` — no implementado; el flujo real de efectivo hoy ocurre dentro del Checkout Pro de Mercado Pago tras el redirect).
* `MERCADOPAGO_WEBHOOK_SECRET` vacío en `.env` — configurarlo es requisito para que las notificaciones reales de pago se procesen.

## 5. Instrucciones Directivas para Claude Code
* Responder siempre con modificaciones directas en el código, manteniendo limpio el historial de Git.
* Respetar las convenciones de nombres del proyecto (snake_case para BD, camelCase para JS/PHP).
* Antes de dar por concluida una tarea de backend, verificar que no rompa la integridad de la base de datos o el contrato JSON esperado por Vue.
* Mantener la cobertura de pruebas unitarias/integración de Laravel Pest/PHPUnit apuntando al objetivo del 70% (nota: hoy solo existen los 2 tests de ejemplo del esqueleto de Laravel — toda la verificación de Módulo 5 se hizo manualmente vía `tinker` en transacciones con rollback, documentado en `estado_modulo5.md`; sigue pendiente escribir tests automatizados reales).
* Antes de recomendar o modificar algo relacionado a pagos/checkout, leer `estado_modulo5.md` completo — contiene decisiones de seguridad (PCI, verificación de firma de webhook) que no deben revertirse sin entender por qué se tomaron.