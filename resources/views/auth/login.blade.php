<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar | {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f5f7fb;
            --panel: #ffffff;
            --text: #121826;
            --muted: #667085;
            --line: #d0d5dd;
            --accent: #0f766e;
            --accent-hover: #115e59;
            --danger-bg: #fef3f2;
            --danger-line: #fecdca;
            --danger-text: #b42318;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(180deg, #eef2f7 0%, #f8fafc 100%);
            color: var(--text);
        }
        .shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
        }
        .card {
            width: 100%;
            max-width: 420px;
            background: var(--panel);
            border: 1px solid rgba(16, 24, 40, 0.08);
            border-radius: 12px;
            box-shadow: 0 18px 60px rgba(16, 24, 40, 0.12);
            padding: 28px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 24px;
            line-height: 1.2;
        }
        p {
            margin: 0 0 20px;
            color: var(--muted);
            line-height: 1.5;
        }
        .field {
            margin-bottom: 16px;
        }
        label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            font-weight: 700;
        }
        input[type="email"],
        input[type="password"] {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 15px;
            background: #fff;
        }
        input:focus {
            outline: 2px solid rgba(15, 118, 110, 0.18);
            border-color: var(--accent);
        }
        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin: 8px 0 20px;
        }
        .remember {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: var(--muted);
        }
        .button {
            width: 100%;
            border: 0;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 15px;
            font-weight: 700;
            color: #fff;
            background: var(--accent);
            cursor: pointer;
        }
        .button:hover {
            background: var(--accent-hover);
        }
        .error {
            margin: 0 0 16px;
            padding: 12px 14px;
            border: 1px solid var(--danger-line);
            border-radius: 10px;
            background: var(--danger-bg);
            color: var(--danger-text);
            font-size: 14px;
            line-height: 1.45;
        }
        .hint {
            margin-top: 16px;
            font-size: 13px;
            color: var(--muted);
        }
        .hint code {
            font-family: Consolas, monospace;
        }
    </style>
</head>
<body>
<main class="shell">
    <section class="card">
        <h1>Entrar no sistema</h1>
        <p>Use a conta seedada para acessar o ambiente local.</p>

        @if ($errors->any())
            <div class="error">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="post" action="{{ route('login') }}">
            @csrf

            <div class="field">
                <label for="email">E-mail</label>
                <input
                    id="email"
                    name="email"
                    type="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="email"
                >
            </div>

            <div class="field">
                <label for="password">Senha</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                >
            </div>

            <div class="row">
                <label class="remember" for="remember">
                    <input id="remember" name="remember" type="checkbox" value="1" {{ old('remember') ? 'checked' : '' }}>
                    Lembrar acesso
                </label>
            </div>

            <button class="button" type="submit">Entrar</button>
        </form>

        <div class="hint">
            Conta local: <code>admin@vistoria.test</code> / <code>password</code>
        </div>
    </section>
</main>
</body>
</html>
