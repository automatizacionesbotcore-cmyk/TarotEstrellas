<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - Tarot Estrellas</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f9fafb; margin: 0; }
        .top { padding: 16px 20px; background: #111827; color: #fff; display: flex; justify-content: space-between; align-items: center; }
        .content { padding: 20px; }
        .box { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 10px 30px rgba(0,0,0,.06); max-width: 720px; }
        button { border: 0; border-radius: 8px; background: #ef4444; color: #fff; padding: 8px 12px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="top">
        <div>Panel Admin</div>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit">Cerrar sesion</button>
        </form>
    </div>
    <div class="content">
        <div class="box">
            <h1>Bienvenido, {{ $user->name }}</h1>
            <p>Sesion iniciada como: <strong>{{ $user->email }}</strong></p>
            <p>Este panel es una puerta de acceso admin minima para entrar por navegador mientras se activa Filament.</p>
        </div>
    </div>
</body>
</html>
