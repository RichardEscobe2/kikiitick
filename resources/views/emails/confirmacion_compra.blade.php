<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 20px; color: #1f2937; }
        .card { background: #ffffff; padding: 30px; border-radius: 8px; max-width: 560px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 4px; }
        .header h2 { color: #4f46e5; margin: 0; }
        .folio { font-size: 13px; color: #6b7280; text-align: center; margin: 0 0 24px; }
        .evento-title { font-size: 20px; font-weight: bold; margin: 0 0 4px; }
        .evento-meta { font-size: 13px; color: #6b7280; margin: 0 0 20px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th, td { text-align: left; padding: 8px 4px; font-size: 13px; border-bottom: 1px solid #e5e7eb; }
        th { color: #6b7280; text-transform: uppercase; font-size: 11px; letter-spacing: 0.05em; }
        .totales td { border-bottom: none; padding: 4px; font-size: 13px; }
        .totales .label { color: #6b7280; }
        .totales .valor { text-align: right; }
        .total-final td { font-weight: bold; font-size: 16px; border-top: 2px solid #4f46e5; padding-top: 12px; }
        .nota { font-size: 12px; color: #6b7280; margin-top: 20px; }
        .boleto-detalle { font-size: 13px; }
        .boleto-detalle strong { display: block; margin-bottom: 2px; }
        .boleto-detalle span { color: #6b7280; font-size: 12px; }
        .qr-cell { text-align: right; vertical-align: middle; width: 100px; }
        .cta-wrap { text-align: center; margin: 24px 0 4px; }
        .cta-btn { display: inline-block; background: #4f46e5; color: #ffffff !important; text-decoration: none; font-weight: bold; font-size: 14px; padding: 12px 28px; border-radius: 8px; }
        .footer { text-align: center; font-size: 11px; color: #9ca3af; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h2>¡Compra confirmada!</h2>
        </div>
        <p class="folio">Folio de orden #{{ $venta->id }}</p>

        @if($evento)
            <p class="evento-title">{{ $evento->titulo }}</p>
            <p class="evento-meta">
                📅 {{ optional($evento->fecha_hora)->translatedFormat('d \d\e F \d\e Y, h:i A') }}<br>
                📍 {{ $teatro->nombre ?? '' }}{{ isset($teatro) && $teatro->ubicacion ? ' (' . $teatro->ubicacion . ')' : '' }}
            </p>
        @endif

        <table>
            <thead>
                <tr>
                    <th>Boleto</th>
                    <th class="qr-cell">Código de acceso</th>
                </tr>
            </thead>
            <tbody>
                @foreach($accesosConQr as $item)
                    <tr>
                        <td class="boleto-detalle">
                            <strong>{{ $item['acceso']->numero_control }}</strong>
                            <span>{{ $item['acceso']->seccion_pasillo }} · Fila {{ $item['acceso']->fila_palco }} · #{{ $item['acceso']->numero_asiento }}</span>
                        </td>
                        <td class="qr-cell">
                            <img
                                src="{{ $item['qr_data_uri'] }}"
                                width="90"
                                height="90"
                                alt="Código QR de acceso — boleto {{ $item['acceso']->numero_control }}"
                                style="display:inline-block; border-radius:4px;"
                            />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <table class="totales">
            <tr>
                <td class="label">Subtotal boletos</td>
                <td class="valor">${{ number_format((float) $venta->monto_neto, 2) }} MXN</td>
            </tr>
            <tr>
                <td class="label">Cargo por servicio</td>
                <td class="valor">${{ number_format((float) $venta->total_comisiones, 2) }} MXN</td>
            </tr>
            <tr class="total-final">
                <td>Total pagado</td>
                <td class="valor">${{ number_format((float) $venta->monto_total, 2) }} MXN</td>
            </tr>
        </table>

        {{--
            🛡️ El botón solo se muestra para compras en línea. GET /api/ventas/{id}
            (que resuelve /confirmacion/{id}) exige sesión Sanctum del DUEÑO real de la
            Venta (CompraController::mostrarVenta(): venta.usuario_id !== user.id -> 403,
            además la ruta en sí exige requiresAuth). En una venta de taquilla,
            venta.usuario_id es el ID del CAJERO que la procesó (CompraService::
            comprarEnTaquilla()), no el del cliente de mostrador — ese cliente no tiene
            cuenta con la que pueda autenticarse jamás para ver esta orden. Mostrar el
            botón también en ese caso sería prometer una descarga que siempre termina en
            un muro de login o un 403, así que para taquilla se mantiene únicamente el
            folio impreso en esta misma tabla como comprobante válido.
        --}}
        @unless($venta->vendido_por_usuario_id)
            <div class="cta-wrap">
                <a class="cta-btn" href="{{ rtrim((string) config('app.frontend_url'), '/') }}/confirmacion/{{ $venta->id }}">
                    Ver / Descargar Boletos Digitales (QR)
                </a>
            </div>
        @endunless

        @if($venta->vendido_por_usuario_id)
            <p class="nota">
                Conserva este correo como tu comprobante de compra: presenta el código QR de
                cada boleto (arriba en esta tabla) al ingresar al evento — también sirve como
                respaldo digital de tu recibo impreso en taquilla.
            </p>
        @else
            <p class="nota">
                El código QR de cada boleto ya está en este correo (arriba en la tabla) — puedes
                presentarlo directo desde tu bandeja de entrada, o descargar/imprimir tus boletos
                digitales haciendo clic en el botón de arriba en cualquier momento desde tu perfil de KikiiTick.
            </p>
        @endif
    </div>
    <p class="footer">KikiiTick &middot; Este es un correo automático, no respondas a este mensaje.</p>
</body>
</html>
