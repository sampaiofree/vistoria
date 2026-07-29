<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard | {{ config('app.name') }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f3f4f6;
            --panel: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --line: #d1d5db;
            --accent: #0f766e;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        .wrap {
            max-width: 960px;
            margin: 0 auto;
            padding: 24px;
        }
        .panel {
            background: var(--panel);
            border: 1px solid rgba(17, 24, 39, 0.08);
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 12px 36px rgba(17, 24, 39, 0.08);
        }
        .top {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        h1 {
            margin: 0 0 8px;
            font-size: 28px;
        }
        p {
            margin: 0;
            color: var(--muted);
            line-height: 1.5;
        }
        .meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }
        .box {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 16px;
        }
        .label {
            font-size: 12px;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 6px;
        }
        .value {
            font-size: 16px;
            font-weight: 700;
        }
        .button {
            display: inline-flex;
            align-items: center;
            border: 0;
            border-radius: 10px;
            padding: 10px 14px;
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            background: var(--accent);
            cursor: pointer;
        }
    </style>
</head>
<body>
<main class="wrap">
    <section class="panel">
        <div class="top">
            <div>
                <h1>Dashboard</h1>
                <p>Você entrou no ambiente local do sistema.</p>
            </div>

            <form method="post" action="{{ route('logout') }}">
                @csrf
                <button class="button" type="submit">Sair</button>
            </form>
        </div>

        <div class="meta">
            <div class="box">
                <div class="label">Usuário</div>
                <div class="value">{{ auth()->user()->name }}</div>
            </div>
            <div class="box">
                <div class="label">E-mail</div>
                <div class="value">{{ auth()->user()->email }}</div>
            </div>
            <div class="box">
                <div class="label">Conta</div>
                <div class="value">{{ auth()->user()->account_type->value }}</div>
            </div>
            <div class="box">
                <div class="label">Empresa</div>
                <div class="value">{{ auth()->user()->organization?->name ?? 'Sem empresa' }}</div>
            </div>
        </div>
    </section>
</main>
</body>
</html>
