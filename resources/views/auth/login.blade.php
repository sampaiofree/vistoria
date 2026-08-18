<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f3f4f6">
    <title>Entrar | {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
    <style>
        :root {
            color-scheme: light;
            --gray-950: #111827;
            --gray-900: #1f2937;
            --gray-800: #374151;
            --gray-700: #4b5563;
            --gray-600: #6b7280;
            --gray-500: #9ca3af;
            --gray-300: #d1d5db;
            --gray-200: #e5e7eb;
            --gray-100: #f3f4f6;
            --gray-50: #f9fafb;
            --white: #ffffff;
            --danger-bg: #fff1f2;
            --danger-line: #fecdd3;
            --danger-text: #be123c;
        }

        * {
            box-sizing: border-box;
        }

        html {
            min-width: 320px;
            background: var(--gray-100);
        }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            background: var(--gray-100);
            color: var(--gray-950);
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        button,
        input {
            font: inherit;
        }

        .shell {
            display: grid;
            min-height: 100vh;
            min-height: 100dvh;
            place-items: center;
            padding: 2rem 1.25rem;
        }

        .access-content {
            width: 100%;
            max-width: 27rem;
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            color: var(--gray-900);
            font-size: 1rem;
            font-weight: 700;
        }

        .brand-mark {
            display: grid;
            width: 2.5rem;
            height: 2.5rem;
            place-items: center;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            background: var(--white);
            color: var(--gray-700);
        }

        .brand-mark svg {
            width: 1.35rem;
            height: 1.35rem;
        }

        .access-panel {
            padding: clamp(1.5rem, 5vw, 2rem);
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            background: var(--white);
        }

        .access-panel h1 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 650;
            line-height: 1.25;
        }

        .access-description {
            margin: 0.65rem 0 1.75rem;
            color: var(--gray-600);
            font-size: 0.925rem;
            line-height: 1.55;
        }

        .field {
            margin-bottom: 1.15rem;
        }

        label {
            display: block;
            margin-bottom: 0.45rem;
            color: var(--gray-800);
            font-size: 0.875rem;
            font-weight: 600;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            min-height: 2.75rem;
            padding: 0.65rem 0.75rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.375rem;
            background: var(--white);
            color: var(--gray-950);
            font-size: 0.95rem;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        input::placeholder {
            color: var(--gray-500);
        }

        input:focus {
            outline: none;
            border-color: var(--gray-700);
            box-shadow: 0 0 0 3px rgba(75, 85, 99, 0.14);
        }

        .row {
            margin: 0.25rem 0 1.5rem;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            margin: 0;
            color: var(--gray-700);
            font-size: 0.85rem;
            font-weight: 400;
            cursor: pointer;
        }

        .remember input {
            width: 1rem;
            height: 1rem;
            accent-color: var(--gray-800);
        }

        .button {
            width: 100%;
            min-height: 2.75rem;
            border: 1px solid var(--gray-900);
            border-radius: 0.375rem;
            background: var(--gray-900);
            color: var(--white);
            cursor: pointer;
            font-size: 0.925rem;
            font-weight: 650;
            transition: background 150ms ease, border-color 150ms ease;
        }

        .button:hover {
            border-color: var(--gray-950);
            background: var(--gray-950);
        }

        .button:focus-visible {
            outline: 3px solid rgba(75, 85, 99, 0.3);
            outline-offset: 2px;
        }

        .error {
            margin: 0 0 1.25rem;
            padding: 0.8rem 0.9rem;
            border: 1px solid var(--danger-line);
            border-radius: 0.375rem;
            background: var(--danger-bg);
            color: var(--danger-text);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .security-note {
            display: flex;
            align-items: flex-start;
            gap: 0.6rem;
            margin: 1.5rem 0 0;
            color: var(--gray-600);
            font-size: 0.75rem;
            line-height: 1.5;
        }

        .security-note svg {
            flex: 0 0 auto;
            width: 1rem;
            height: 1rem;
            margin-top: 0.08rem;
            color: var(--gray-700);
        }

        @media (max-width: 480px) {
            .shell {
                align-items: start;
                padding: 1.5rem 1rem;
            }

            .access-panel {
                padding: 1.5rem;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
<main class="shell">
    <div class="access-content">
        <div class="brand" aria-label="Vistoria">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M7 4.5h7.8L19 8.7V19.5A1.5 1.5 0 0 1 17.5 21H7a1.5 1.5 0 0 1-1.5-1.5V6A1.5 1.5 0 0 1 7 4.5Z"/>
                    <path d="M14.8 4.5V9H19M8.3 12.2l2.15 2.15 5.2-5.2"/>
                </svg>
            </span>
            <span>Vistoria</span>
        </div>

        <section class="access-panel" aria-labelledby="access-title">
            <h1 id="access-title">Boas-vindas ao Vistoria</h1>
            <p class="access-description">Entre com sua conta para acessar o ambiente da sua empresa.</p>

            @if ($errors->any())
                <div class="error" role="alert">
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
                        placeholder="nome@empresa.com.br"
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
                        placeholder="Digite sua senha"
                        required
                        autocomplete="current-password"
                    >
                </div>

                <div class="row">
                    <label class="remember" for="remember">
                        <input id="remember" name="remember" type="checkbox" value="1" {{ old('remember') ? 'checked' : '' }}>
                        Manter acesso neste dispositivo
                    </label>
                </div>

                <button class="button" type="submit">Entrar</button>
            </form>

            <p class="security-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="5" y="10" width="14" height="10" rx="2"/>
                    <path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>
                </svg>
                <span>Acesso restrito a usuários autorizados. Suas atividades podem ser registradas para fins de segurança.</span>
            </p>
        </section>
    </div>
</main>
</body>
</html>
