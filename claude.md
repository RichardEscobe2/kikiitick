# Guía Operativa de Desarrollo - KIKIITICK v2.5 (CLAUDE.md)

## 1. Contexto del Proyecto y Arquitectura
* **Nombre:** KikiiTick - Sistema de Gestión y Venta de Boletos para Eventos.
* **Patrón Arquitectónico:** Monolito Modular desacoplado (Backend API RESTful + Frontend SPA).
* **Backend:** PHP 8.3 / Laravel 13 con autenticación Laravel Sanctum (Modo SPA / Statefull Cookie).
* **Frontend:** Vue 3 + Vite 8 + Tailwind CSS 4.
* **Base de Datos:** MySQL (Modo 3FN). NOTA: Actualmente corriendo localmente en SQLite, se deben asegurar migraciones 100% compatibles con MySQL.

## 2. Reglas de Negocio Críticas (Inviolables)
* **RN-01 (Protección de Histórico):** PROHIBIDO permitir la regeneración de la matriz de asientos en SeatGeneratorService si el recinto posee eventos con boletos en estado 'reservado' o 'vendido'.
* **RN-02 (Seguridad Administrativa):** TODOS los endpoints de AdminController DEBEN requerir autenticación 'auth:sanctum' y verificar explícitamente el rol de 'admin'.
* **RN-03 (Concurrencia de Reservas):** El bloqueo temporal de butacas dura un periodo estricto de 5 minutos (bloqueo pesimista). Vencido el tiempo, el sistema libera las butacas a estado 'disponible'.
* **RN-05 (Integridad Referencial):** Toda relación de base de datos que apunte a usuarios DEBE referenciar a la tabla 'usuarios' (NO a 'users').

## 3. Estructura de Capas y Código
* `/app/Http/Controllers`: Controladores delgados (Auth, Evento, Teatro, Compra, Admin).
* `/app/Services`: Lógica de negocio compleja desacoplada (SeatGeneratorService, QRService, TicketService).
* `/app/Models`: Modelos Eloquent con relaciones explícitas (User->$table='usuarios', Teatro, Evento, Asiento, AsientoEvento, Venta, DetalleVenta, Acceso).
* `/resources/js/Views`: Vistas SPA de Vue.js (Home, EventoDetail, Organizador, AdminUsuarios, Perfil).
* `/routes/web.php`: Definición de rutas API estructuradas bajo la agrupación Route::prefix('api').

## 4. Hallazgos Prioritarios a Corregir (Hotfixes Inmediatos)
* Corregir la migración '2026_07_22_200243_add_layout_and_seats_tables.php' para que 'reservado_por_usuario_id' apunte a la tabla 'usuarios'.
* Aplicar middleware de autenticación y rol 'admin' al grupo de rutas '/api/admin' en 'routes/web.php'.
* Corregir la discrepancia de contrato JSON en 'Login.vue' para procesar adecuadamente el objeto 'user' y sus roles.
* Implementar la guarda de seguridad en 'TeatroController@update' para evitar borrado de asientos en cascada con ventas existentes.

## 5. Instrucciones Directivas para Claude Code
* Responder siempre con modificaciones directas en el código, manteniendo limpio el historial de Git.
* Respetar las convenciones de nombres del proyecto (snake_case para BD, camelCase para JS/PHP).
* Antes de dar por concluida una tarea de backend, verificar que no rompa la integridad de la base de datos o el contrato JSON esperado por Vue.
* Mantener la cobertura de pruebas unitarias/integración de Laravel Pest/PHPUnit apuntando al objetivo del 70%.