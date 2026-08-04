<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#081a2f">
    <title>Entrar | {{ config('app.name') }}</title>
    @vite('resources/css/app.css')
    <style>
        :root {
            color-scheme: light;
            --navy-950: #081a2f;
            --navy-900: #0b243f;
            --navy-800: #113451;
            --teal-600: #0d9488;
            --teal-700: #0f766e;
            --slate-950: #0f172a;
            --slate-700: #334155;
            --slate-500: #64748b;
            --slate-300: #cbd5e1;
            --slate-100: #f1f5f9;
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
            background: var(--navy-950);
        }

        body {
            margin: 0;
            min-height: 100vh;
            min-height: 100dvh;
            font-family: "Instrument Sans", Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--slate-100);
            color: var(--slate-950);
            -webkit-font-smoothing: antialiased;
        }

        button,
        input {
            font: inherit;
        }

        .shell {
            min-height: 100vh;
            min-height: 100dvh;
            display: grid;
            grid-template-columns: minmax(0, 1.08fr) minmax(30rem, 0.92fr);
        }

        .showcase {
            position: relative;
            isolation: isolate;
            display: flex;
            min-height: 100%;
            overflow: hidden;
            padding: clamp(2rem, 5vw, 5rem);
            color: var(--white);
            background:
                radial-gradient(circle at 78% 24%, rgba(20, 184, 166, 0.28), transparent 27rem),
                linear-gradient(145deg, var(--navy-950) 0%, var(--navy-900) 55%, #0b3d50 100%);
        }

        .showcase::before {
            position: absolute;
            z-index: -2;
            inset: 0;
            content: "";
            opacity: 0.22;
            background-image:
                linear-gradient(rgba(255, 255, 255, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.1) 1px, transparent 1px);
            background-size: 72px 72px;
            mask-image: linear-gradient(to bottom right, black, transparent 82%);
        }

        .showcase::after {
            position: absolute;
            z-index: -1;
            width: min(56vw, 46rem);
            aspect-ratio: 1;
            right: -24%;
            bottom: -34%;
            content: "";
            border: 1px solid rgba(94, 234, 212, 0.28);
            border-radius: 50%;
            box-shadow:
                0 0 0 5rem rgba(13, 148, 136, 0.05),
                0 0 0 10rem rgba(13, 148, 136, 0.04),
                0 0 0 15rem rgba(13, 148, 136, 0.025);
        }

        .showcase-content {
            width: 100%;
            max-width: 46rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 4rem;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            width: fit-content;
            color: var(--white);
            font-size: 1rem;
            font-weight: 750;
            letter-spacing: 0.02em;
        }

        .brand-mark {
            display: grid;
            width: 2.7rem;
            height: 2.7rem;
            place-items: center;
            border: 1px solid rgba(153, 246, 228, 0.38);
            border-radius: 0.9rem;
            background: rgba(13, 148, 136, 0.2);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16);
        }

        .brand-mark svg {
            width: 1.45rem;
            height: 1.45rem;
        }

        .eyebrow {
            margin: 0 0 1.25rem;
            color: #99f6e4;
            font-size: 0.75rem;
            font-weight: 750;
            letter-spacing: 0.22em;
            text-transform: uppercase;
        }

        .showcase h1 {
            max-width: 42rem;
            margin: 0;
            font-size: clamp(2.25rem, 5vw, 4.5rem);
            font-weight: 680;
            letter-spacing: -0.05em;
            line-height: 1.02;
        }

        .showcase-copy {
            max-width: 37rem;
            margin: 1.5rem 0 0;
            color: #cbd5e1;
            font-size: clamp(1rem, 1.5vw, 1.15rem);
            line-height: 1.75;
        }

        .capabilities {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 2rem;
        }

        .capability {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            padding: 0.62rem 0.85rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 999px;
            background: rgba(15, 23, 42, 0.24);
            color: #e2e8f0;
            font-size: 0.79rem;
            font-weight: 650;
            backdrop-filter: blur(10px);
        }

        .capability::before {
            width: 0.42rem;
            height: 0.42rem;
            content: "";
            border-radius: 50%;
            background: #2dd4bf;
            box-shadow: 0 0 0 0.2rem rgba(45, 212, 191, 0.12);
        }

        .showcase-footer {
            color: #94a3b8;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .access-panel {
            display: grid;
            min-height: 100%;
            place-items: center;
            padding: clamp(1.5rem, 5vw, 4rem);
            background:
                radial-gradient(circle at 100% 0%, rgba(13, 148, 136, 0.08), transparent 22rem),
                #f8fafc;
        }

        .access-content {
            width: 100%;
            max-width: 27rem;
        }

        .mobile-brand {
            display: none;
            margin-bottom: 2.5rem;
        }

        .access-kicker {
            margin: 0 0 0.65rem;
            color: var(--teal-700);
            font-size: 0.75rem;
            font-weight: 750;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .access-content h2 {
            margin: 0;
            color: var(--slate-950);
            font-size: clamp(1.75rem, 3vw, 2.2rem);
            font-weight: 680;
            letter-spacing: -0.035em;
            line-height: 1.15;
        }

        .access-description {
            margin: 0.85rem 0 2rem;
            color: var(--slate-500);
            font-size: 0.95rem;
            line-height: 1.65;
        }

        .field {
            margin-bottom: 1.2rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--slate-700);
            font-size: 0.85rem;
            font-weight: 700;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            min-height: 3.25rem;
            padding: 0.78rem 0.9rem;
            border: 1px solid var(--slate-300);
            border-radius: 0.8rem;
            background: var(--white);
            color: var(--slate-950);
            font-size: 0.95rem;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        input::placeholder {
            color: #94a3b8;
        }

        input:focus {
            outline: none;
            border-color: var(--teal-600);
            box-shadow: 0 0 0 3px rgba(13, 148, 136, 0.14);
        }

        .row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin: 0.25rem 0 1.5rem;
        }

        .remember {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            margin: 0;
            color: var(--slate-500);
            font-size: 0.85rem;
            font-weight: 550;
            cursor: pointer;
        }

        .remember input {
            width: 1rem;
            height: 1rem;
            accent-color: var(--teal-700);
        }

        .button {
            width: 100%;
            min-height: 3.25rem;
            border: 0;
            border-radius: 0.8rem;
            color: var(--white);
            background: var(--navy-950);
            box-shadow: 0 0.65rem 1.4rem rgba(8, 26, 47, 0.14);
            cursor: pointer;
            font-size: 0.92rem;
            font-weight: 750;
            transition: background 150ms ease, box-shadow 150ms ease, transform 150ms ease;
        }

        .button:hover {
            background: var(--navy-800);
            box-shadow: 0 0.8rem 1.6rem rgba(8, 26, 47, 0.2);
            transform: translateY(-1px);
        }

        .button:focus-visible {
            outline: 3px solid rgba(13, 148, 136, 0.34);
            outline-offset: 3px;
        }

        .error {
            margin: 0 0 1.25rem;
            padding: 0.8rem 0.9rem;
            border: 1px solid var(--danger-line);
            border-radius: 0.75rem;
            background: var(--danger-bg);
            color: var(--danger-text);
            font-size: 0.85rem;
            line-height: 1.5;
        }

        .security-note {
            display: flex;
            align-items: flex-start;
            gap: 0.65rem;
            margin: 1.5rem 0 0;
            color: var(--slate-500);
            font-size: 0.76rem;
            line-height: 1.5;
        }

        .security-note svg {
            flex: 0 0 auto;
            width: 1rem;
            height: 1rem;
            margin-top: 0.08rem;
            color: var(--teal-700);
        }

        @media (max-width: 900px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .showcase {
                display: none;
            }

            .access-panel {
                min-height: 100vh;
                min-height: 100dvh;
                padding: 2rem 1.25rem;
            }

            .mobile-brand {
                display: inline-flex;
                color: var(--navy-950);
            }

            .mobile-brand .brand-mark {
                color: var(--white);
                background: var(--navy-950);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition-duration: 0.01ms !important;
            }
        }
    </style>
</head>
<body>
<main class="shell">
    <section class="showcase" aria-labelledby="product-title">
        <div class="showcase-content">
            <div class="brand" aria-label="Vistoria">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 4.5h7.8L19 8.7V19.5A1.5 1.5 0 0 1 17.5 21H7a1.5 1.5 0 0 1-1.5-1.5V6A1.5 1.5 0 0 1 7 4.5Z"/>
                        <path d="M14.8 4.5V9H19M8.3 12.2l2.15 2.15 5.2-5.2"/>
                    </svg>
                </span>
                <span>Vistoria</span>
            </div>

            <div>
                <p class="eyebrow">Gestão técnica integrada</p>
                <h1 id="product-title">Inspeções com clareza do campo ao relatório.</h1>
                <p class="showcase-copy">
                    Organize ativos, acompanhe avarias e transforme evidências técnicas em decisões seguras para a operação.
                </p>
                <div class="capabilities" aria-label="Recursos da plataforma">
                    <span class="capability">Rastreabilidade</span>
                    <span class="capability">Gestão de avarias</span>
                    <span class="capability">Visão executiva</span>
                </div>
            </div>

            <p class="showcase-footer">Plataforma de inspeção de ativos industriais</p>
        </div>
    </section>

    <section class="access-panel" aria-labelledby="access-title">
        <div class="access-content">
            <div class="brand mobile-brand" aria-label="Vistoria">
                <span class="brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M7 4.5h7.8L19 8.7V19.5A1.5 1.5 0 0 1 17.5 21H7a1.5 1.5 0 0 1-1.5-1.5V6A1.5 1.5 0 0 1 7 4.5Z"/>
                        <path d="M14.8 4.5V9H19M8.3 12.2l2.15 2.15 5.2-5.2"/>
                    </svg>
                </span>
                <span>Vistoria</span>
            </div>

            <p class="access-kicker">Acesso seguro</p>
            <h2 id="access-title">Boas-vindas ao Vistoria</h2>
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

                <button class="button" type="submit">Entrar na plataforma</button>
            </form>

            <p class="security-note">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="5" y="10" width="14" height="10" rx="2"/>
                    <path d="M8 10V7.5a4 4 0 0 1 8 0V10"/>
                </svg>
                <span>Acesso restrito a usuários autorizados. Suas atividades podem ser registradas para fins de segurança.</span>
            </p>
        </div>
    </section>
</main>
</body>
</html>
