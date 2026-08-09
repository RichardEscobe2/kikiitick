# Estado de Módulo 5 (Pagos) — Handoff para la siguiente sesión

> Resumen de todo lo implementado en esta sesión: auditorías previas + remediación (Fases 1-3),
> y Módulo 5 completo (Entregables 1-3) con Mercado Pago Checkout Pro funcionando end-to-end
> contra credenciales sandbox reales. Léelo antes de tocar cualquier cosa relacionada a
> autenticación, checkout o pagos — varias decisiones aquí son deliberadas (seguridad/PCI), no
> descuidos.

## 1. Resumen ejecutivo

El backend y frontend de KikiiTick pasaron por una auditoría integral (seguridad OWASP,
arquitectura/BD, frontend Vue) documentada en `INFORME_AUDITORIA_INTEGRAL_KIKIITICK.md`, se
remediaron los hallazgos críticos/altos (documentado en `INFORME_AUDITORIA_VERIFICACION_POST_FIXES.md`),
y sobre esa base se construyó el Módulo 5: registro/login sin fricción vía modal, integración
real con Mercado Pago (Checkout Pro), venta en taquilla (POS), y las vistas de checkout/confirmación/voucher.
**El flujo de compra en línea fue verificado end-to-end con credenciales sandbox reales: devuelve
un `init_point` válido de `mercadopago.com.mx`.**

## 2. Línea de tiempo de la sesión (qué se hizo, en orden)

1. Auditoría de seguridad backend (OWASP) → `INFORME_AUDITORIA_SEGURIDAD` (consolidado en el informe integral).
2. Auditoría de arquitectura/concurrencia/BD.
3. Auditoría de frontend Vue 3.
4. Consolidación → `INFORME_AUDITORIA_INTEGRAL_KIKIITICK.md`.
5. **Fase 1** — remediación de seguridad backend (ver §3).
6. **Fase 2** — remediación de arquitectura/BD (ver §3).
7. **Fase 3** — remediación de frontend (ver §3).
8. Verificación post-remediación → `INFORME_AUDITORIA_VERIFICACION_POST_FIXES.md` (19 PASS / 1 PARCIAL / 6 PENDIENTE de 26 hallazgos).
9. Fix de CORS (config/cors.php + bootstrap.js + SecureHeaders).
10. **Módulo 5, Entregable 1** — Quick Auth Modal + interceptor de reserva (ver §4).
11. Fix del rate limiter de login/registro (`throttle:5,1` → limiter nombrado `auth-throttle`, 20/min local · 5/min producción).
12. **Módulo 5, Entregable 2** — Integración Mercado Pago + POS + email de confirmación (ver §5).
13. Fix de bug de datos: `Asiento.zona_teatro_id` huérfano tras regenerar matriz → precios `null`/0 en el mapa (ver §6).
14. **Módulo 5, Entregable 3** — Split Landing/Checkout, QR, ficha OXXO (ver §7).
15. Fix del 502 en `POST /api/boletos/comprar`: `auto_return` de Mercado Pago rechaza `back_urls.success` en `localhost` (ver §8).

## 3. Fases 1-3 (remediación pre-Módulo 5) — resumen de archivos tocados

- **Backend:** `AuthController::register()` (fuerza `rol='cliente'`), `SecureHeaders` middleware (nuevo), `EnsureUserIsOrganizador` middleware (nuevo, con bypass admin), `hash_equals()` en OTP, `SeatGeneratorService` (guard RN-01 autoprotegido + `lockForUpdate`), migración de índices compuestos, `attempts:3` en transacciones críticas, bulk `upsert`/`insert` en `CompraService`.
- **Frontend:** `bootstrap.js` activado en `app.js`, `localStorage` limpiado en logout, memoización `computed(Set)` de selección de asientos, `isAdmin`/`isOrganizador` en `useAuth.js` (no adoptados en todos los consumidores — ver pendientes), cleanup de `setTimeout`, `EmptyState.vue` con `defineEmits`.
- Detalle completo y exacto (con file:line) en `INFORME_AUDITORIA_INTEGRAL_KIKIITICK.md` e `INFORME_AUDITORIA_VERIFICACION_POST_FIXES.md` — no se repite aquí.

## 4. Módulo 5, Entregable 1 — Quick Auth Modal

**Archivos nuevos:**
- `resources/js/composables/useAuthModal.js` — singleton (`isOpen`, `activeTab`, `openAuthModal(callback, tab)`, `closeAuthModal()`, `handleSuccess()`).
- `resources/js/Components/QuickAuthModal.vue` — login/registro/verificación OTP en un solo modal (`<Teleport to="body">`, ESC para cerrar). El registro NUNCA hace login automático (AuthController::register() solo crea el usuario y envía OTP) — el modal pasa a un paso interno `'verify'` tras registrarse.

**Modificado:** `App.vue` (monta `<QuickAuthModal />` globalmente), `EventoDetail.vue` en su momento (ahora es `EventoCheckout.vue`, ver §7) — patrón: antes de reservar, si `!isAuthenticated`, `openAuthModal(() => ejecutarReserva(), 'login')` en vez de dejar que el request falle con 401 crudo.

## 5. Módulo 5, Entregable 2 — Mercado Pago + POS + Email

**Dependencia nueva:** `composer require mercadopago/dx-php` (SDK v3, namespaces `MercadoPago\Client\...`, `MercadoPago\MercadoPagoConfig`). Verificado que no introduce vulnerabilidades nuevas (las de `composer audit` son preexistentes, de `guzzlehttp/guzzle`/`league/commonmark`).

**Migración nueva** `2026_08_06_000002_add_modulo5_payment_columns.php`:
- `usuarios.rol` enum extendido con `'vendedor'`.
- `asientos_evento.venta_id` (nullable FK a `ventas`) — enlaza qué asientos pertenecen a qué venta, para que el webhook pueda marcar `'vendido'` exactamente los correctos sin reconstrucción frágil.
- `ventas.metodo_pago` (string nullable) y `ventas.vendido_por_usuario_id` (FK nullable a `usuarios`, para ventas de taquilla).

**Archivos nuevos:**
- `app/Services/MercadoPagoService.php` — `crearPreferencia(Venta, array $boletos)` y `obtenerPago(int $paymentId)`.
- `app/Mail/ConfirmacionCompraMail.php` + `resources/views/emails/confirmacion_compra.blade.php`.

**Modificado:**
- `CompraService.php` — `confirmarPagoAprobado(Venta)` (idempotente, devuelve `bool`; llamada desde el webhook), `comprarEnTaquilla(...)` (venta POS inmediata), enlace `venta_id` en `procesarCompra()`.
- `CompraController.php` — `procesarCompra()` ahora también crea la preferencia de MP y devuelve `init_point`; `webhookMercadoPago()` (verifica firma HMAC con `WebhookSignatureValidator` del SDK, re-consulta el pago vía `obtenerPago()`, nunca confía en el body de la notificación); `comprarPos()` (rol `vendedor`/`organizador`/`admin`, chequeo inline en el controlador — ningún middleware de rol existente cubre esa combinación de 3 roles).
- `routes/web.php` — `POST /api/pagos/webhook` (público, throttle 120/min), `POST /api/boletos/comprar-pos` (auth:sanctum).
- `bootstrap/app.php` — `preventRequestForgery(except: ['api/pagos/webhook'])` (el webhook es una petición servidor-a-servidor, no puede llevar CSRF de sesión; su autenticidad se verifica por firma HMAC).
- `config/services.php` (`mercadopago.access_token/public_key/webhook_secret`), `config/app.php` (`frontend_url`).
- `AdminController::cambiarRol()` — permite asignar `'vendedor'`.

**Decisión de seguridad importante (no revertir sin pensarlo):** el ticket original pedía inputs de tarjeta propios (`número/CVC`) enviados a un endpoint nuestro. **No se implementó así** — sería captura de datos de tarjeta fuera de scope PCI. En su lugar, tanto la opción "Tarjeta" como "OXXO" en el checkout llaman al mismo `POST /api/boletos/comprar` real, que redirige a `init_point` (la página de Mercado Pago), donde el usuario ingresa la tarjeta o elige efectivo de forma nativa y segura. Nosotros nunca vemos ni almacenamos datos de tarjeta.

## 6. Fix de datos: precios `null`/0 en el mapa de eventos

**Causa raíz:** `SeatGeneratorService::generarAsientosParaTeatro()` borra y recrea TODOS los `Asiento` en cada regeneración de matriz, siempre con `zona_teatro_id = null`. Si esto ocurre después de que el organizador ya configuró zonas, los asientos quedan huérfanos de su zona (y por tanto de su tarifa en `BoletoEvento`) sin ningún aviso.

**Fix:**
- `SeatGeneratorService.php` — tras regenerar, reconstruye el vínculo asiento→zona a partir de `fila_inicio`/`fila_fin` de cada `ZonaTeatro` (previene que el bug se repita).
- `app/Console/Commands/RepararIntegridadPrecios.php` (nuevo, `php artisan kikiitick:reparar-precios-mapa`, idempotente) — repara los vínculos ya huérfanos + asigna tarifa base por defecto a zonas de eventos activos sin precio. **Ya se ejecutó una vez** en esta sesión (1206 asientos reparados, 22 zonas con tarifa backfilled) — no hace falta volver a correrlo salvo que el bug reaparezca.

## 7. Módulo 5, Entregable 3 — Checkout UI completo

**Dependencias nuevas:** `qrcode` (QR 100% generado en el navegador, el token nunca sale a un tercero), `jsbarcode` (código de barras real, no fabricado con CSS).

**Vistas nuevas** (reemplazan a `EventoDetail.vue`, que fue **eliminada**):
- `EventoLanding.vue` (`/evento/:id`) — hero, descripción, zonas+disponibilidad, selector zona/cantidad (solo intención — el asiento exacto se elige en Checkout), CTA con interceptor de auth.
- `EventoCheckout.vue` (`/checkout/:id`) — header sticky con temporizador, mapa con zoom/pan (drag), resumen + acordeón de pago (Tarjeta/OXXO, ambos redirigen a Mercado Pago).
- `ConfirmacionCompra.vue` (`/confirmacion/:ordenId`) — banner según `estatus_pago` (pagado/pendiente/fallido, con polling cada 15s mientras esté pendiente), QR real por boleto, "Descargar PDF" vía `window.print()`.
- `FichaOxxo.vue` (`/ficha-oxxo/:ordenId`) — código de barras de la **referencia real de la orden en KikiiTick** (no un voucher OXXO de Mercado Pago — ver pendiente en `claude.md` §4).

**Backend nuevo necesario para que estas vistas tuvieran datos reales que mostrar:**
- `GET /api/ventas/{id}` (`CompraController::mostrarVenta`) — autorizado por dueño/admin (BOLA verificado: dueño 200, otro usuario 403).
- `MercadoPagoService::crearPreferencia()` — `back_urls` actualizadas a `/confirmacion/{ventaId}` (las 3: éxito/pendiente/fallido apuntan al mismo sitio, que re-consulta el estado real contra nuestro backend).

**Router:** `EventCard.vue` actualizado (`EventoDetail` → `EventoLanding`). Nota: `/checkout/:id` **no** tiene `requiresAuth: true` a propósito (el mapa es de lectura pública; el interceptor "frictionless" pide sesión justo al reservar, no de entrada) — `/confirmacion/:ordenId` y `/ficha-oxxo/:ordenId` sí lo tienen.

## 8. Fix del 502 en `POST /api/boletos/comprar` (la tarea más reciente)

**Causa raíz confirmada empíricamente** (aislada con 3 llamadas directas al SDK): Mercado Pago
rechaza la preferencia completa con `400 auto_return invalid. back_url.success must be defined`
cuando `auto_return: 'approved'` se combina con un `back_urls.success` en `localhost`. Sin
`auto_return`, o con una URL `https://` pública, funciona bien.

**Fix en `MercadoPagoService::crearPreferencia()`:** `auto_return` solo se agrega a la petición
si `FRONTEND_URL` no contiene `localhost`/`127.0.0.1`. En producción (con `FRONTEND_URL` público)
se activa automáticamente. **Además:** `CompraController::procesarCompra()` ahora loggea
`getStatusCode()` + `getApiResponse()->getContent()` de `MPApiException` por separado — antes
solo quedaba el mensaje genérico `"Api error. Check response for details"`, inútil para
diagnosticar sin reproducir el error a mano.

**Segundo 400 encontrado justo después de este fix** (mismo síntoma 502, causa distinta —
confirmado en `storage/logs/laravel.log`, `venta_id` 15 y 16): Mercado Pago también rechaza la
preferencia completa con `400 notificaction_url attribute must be a valid url` [sic, typo de
Mercado Pago] cuando `notification_url` (antes fijado siempre a `url('/api/pagos/webhook')`, ej.
`http://localhost:8000/...`) tampoco es públicamente alcanzable. Mismo patrón que `auto_return`:
en `MercadoPagoService::crearPreferencia()`, `notification_url` ahora solo se agrega a la
petición si la URL del backend (`url('/api/pagos/webhook')`) no contiene `localhost`/`127.0.0.1`.
**Efecto en local:** al omitirse, el webhook nunca llega automáticamente en desarrollo (ya era el
caso de facto, porque Mercado Pago no puede alcanzar `localhost` de todas formas) — el pago debe
confirmarse manualmente para pruebas, ej. llamando `MercadoPagoService::obtenerPago()` a mano o
invocando `CompraController::webhookMercadoPago()` simulando el payload. En producción, con
`APP_URL` pública, se envía normalmente y el webhook confirma el pago end-to-end sin intervención
(sujeto a configurar `MERCADOPAGO_WEBHOOK_SECRET`, ver §9).

**Verificado end-to-end** con las credenciales sandbox reales que ya están en `.env`, con AMBOS
fixes aplicados: reserva real → `procesarCompra()` real → `HTTP 201` con `init_point` genuino de
`mercadopago.com.mx` (prueba hecha en una transacción con rollback, sin dejar datos ni
preferencias huérfanas en la BD local — aunque sí se crearon preferencias reales e inertes en el
dashboard sandbox de Mercado Pago, inevitable para probar con credenciales reales).

## 9. Config/credenciales — estado actual de `.env`

- `MERCADOPAGO_ACCESS_TOKEN` / `MERCADOPAGO_PUBLIC_KEY` — **credenciales sandbox reales ya cargadas** (no tocar/rotar sin avisar).
- `MERCADOPAGO_WEBHOOK_SECRET` — **vacío**. Sin esto, `POST /api/pagos/webhook` rechaza con 401 cualquier notificación real de Mercado Pago (por diseño — la firma HMAC no se puede validar sin el secreto). Configurarlo desde el dashboard de Mercado Pago (Tus integraciones → Webhooks) antes de probar el flujo de confirmación de pago real de punta a punta.
- `FRONTEND_URL=http://localhost:5173` — determina si `auto_return` se activa (ver §8).
- `APP_URL=http://localhost:8000` — determina si `notification_url` (webhook) se envía a Mercado Pago (ver §8); en local se omite, así que el webhook no llega automáticamente en desarrollo.
- `QUEUE_CONNECTION=database` — los correos (`ConfirmacionCompraMail`, etc.) se encolan pero no se envían sin `php artisan queue:work` corriendo.

## 10. Pendientes explícitos (no bloqueantes, priorizar según necesidad)

Ver `claude.md` §4 para la lista corta. Detalle adicional:
- Sin tests automatizados nuevos para Módulo 5 — toda la verificación de esta sesión fue manual, vía `php artisan tinker` dentro de transacciones con `DB::rollBack()` (patrón usado repetidamente para no ensuciar la BD real). Sería valioso convertir esas verificaciones en Feature tests de Pest/PHPUnit reales.
- `AdminActionLog`, `SESSION_SECURE_COOKIE` en producción, extracción de `ZonaService`/`PricingService`, adopción de `isAdmin`/`isOrganizador` en los componentes Vue que aún duplican la comparación de rol inline, vista "Mis Boletos".
- Voucher OXXO real vía Mercado Pago Payments API (`payment_method_id: 'oxxo'`) — hoy el flujo de efectivo real ocurre dentro del Checkout Pro de Mercado Pago, no en `FichaOxxo.vue`.

## 11. Cómo verificar que todo sigue en pie (comandos rápidos)

```bash
php artisan test                          # 2/2 — suite base, sin regresiones
php artisan route:list --path=api         # confirmar las ~35 rutas registradas
npm run build                             # build de producción sin errores
php artisan kikiitick:reparar-precios-mapa # idempotente, re-correr si reaparece el bug de precios null
```

Para probar el checkout completo de punta a punta manualmente: crear/usar un usuario `cliente`,
reservar asientos de un evento con precios configurados (ej. evento 4), y llamar
`POST /api/boletos/comprar` — debe devolver `init_point` de `mercadopago.com.mx` (no 502).
