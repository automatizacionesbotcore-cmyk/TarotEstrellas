<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login - Tarot Estrellas</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f3f4f6; margin: 0; }
        .wrap { min-height: 100vh; display: grid; place-items: center; padding: 24px; }
        .card { width: 100%; max-width: 420px; background: #fff; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,.08); padding: 24px; }
        h1 { margin: 0 0 16px; font-size: 24px; }
        label { display: block; margin: 12px 0 6px; font-weight: 600; }
        input[type="email"], input[type="password"] { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 8px; box-sizing: border-box; }
        .row { margin-top: 10px; }
        .btn { margin-top: 16px; width: 100%; border: 0; border-radius: 8px; background: #111827; color: #fff; padding: 11px 14px; cursor: pointer; }
        .error { background: #fee2e2; color: #991b1b; border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; }
        .hint { margin-top: 12px; color: #374151; font-size: 13px; }
    </style>
</head>
<body>
<div class="wrap">
    <form class="card" method="POST" action="{{ route('admin.login.submit') }}">
        @csrf
        <h1>Ingreso Admin</h1>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <div class="row">
            <label>
                <input type="checkbox" name="remember" value="1"> Recordarme
            </label>
        </div>

        <button class="btn" type="submit">Entrar</button>

        <p class="hint">Solo usuarios con rol <strong>super_admin</strong> o <strong>admin_especialista</strong>.</p>
    </form>
</div>
</body>
</html>
