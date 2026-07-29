<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Solicitud Aprobada - KikiiTick</title>
</head>
<body style="background-color: #0f172a; color: #f8fafc; font-family: sans-serif; padding: 20px; margin: 0;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #1e293b; padding: 30px; border-radius: 12px; border: 1px solid #334155;">
        <h1 style="color: #6366f1; text-align: center; margin-bottom: 20px;">¡Felicidades, {{ $usuario->nombre }}! 🎉</h1>
        
        <p style="font-size: 16px; line-height: 1.6; color: #cbd5e1;">
            Nos alegra informarte que tu solicitud para convertirte en **Organizador** en **KikiiTick** ha sido aprobada.
        </p>

        <p style="font-size: 16px; line-height: 1.6; color: #cbd5e1;">
            A partir de este momento ya tienes acceso al panel de organizador, donde podrás gestionar tus recintos y crear tus eventos para vender boletos.
        </p>

        @if($usuario->teatros && $usuario->teatros->count() > 0)
            <div style="background-color: #0f172a; padding: 15px; border-radius: 8px; margin: 20px 0; border: 1px solid #334155;">
                <h3 style="margin-top: 0; color: #818cf8;">Recinto Registrado:</h3>
                <p style="margin: 5px 0;"><strong>Nombre:</strong> {{ $usuario->teatros->first()->nombre }}</p>
                <p style="margin: 5px 0;"><strong>Ubicación:</strong> {{ $usuario->teatros->first()->ubicacion }}</p>
                <p style="margin: 5px 0;"><strong>Capacidad:</strong> {{ number_format($usuario->teatros->first()->capacidad) }} personas</p>
            </div>
        @endif

        <div style="text-align: center; margin-top: 30px;">
            <a href="{{ config('app.url') }}/login" style="background-color: #6366f1; color: #ffffff; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: bold; display: inline-block;">
                Iniciar Sesión en KikiiTick
            </a>
        </div>

        <hr style="border: 0; border-top: 1px solid #334155; margin: 30px 0;">
        <p style="font-size: 12px; text-align: center; color: #64748b;">
            Este es un correo automático enviado por KikiiTick. Por favor no respondas a este mensaje.
        </p>
    </div>
</body>
</html>