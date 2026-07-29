<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 20px; }
        .card { background: #ffffff; padding: 30px; border-radius: 8px; max-width: 400px; margin: 0 auto; text-align: center; }
        .code { font-size: 32px; font-weight: bold; letter-spacing: 8px; color: #4f46e5; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="card">
        <h2>Tu código de verificación</h2>
        <p>Usa el siguiente código para completar tu acceso a KikiiTick:</p>
        <div class="code">{{ $codigo }}</div>
        <p><small>Este código expira en 10 minutos.</small></p>
    </div>
</body>
</html>