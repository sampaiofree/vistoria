<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedSessionController extends Controller
{
    private const MAX_FAILED_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 300;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);
        $identifier = hash('sha256', Str::lower(trim($credentials['email'])).'|'.$request->ip());
        $failuresKey = 'login:failures:'.$identifier;
        $lockoutKey = 'login:lockout:'.$identifier;

        if (RateLimiter::tooManyAttempts($lockoutKey, 1)) {
            $this->throwLockout($lockoutKey);
        }

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'status' => UserStatus::Active->value,
        ], $request->boolean('remember'))) {
            if (RateLimiter::hit($failuresKey, self::LOCKOUT_SECONDS) >= self::MAX_FAILED_ATTEMPTS) {
                RateLimiter::hit($lockoutKey, self::LOCKOUT_SECONDS);
                RateLimiter::clear($failuresKey);
                $this->throwLockout($lockoutKey);
            }

            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha incorretos, ou a conta está inativa.',
            ]);
        }

        RateLimiter::clear($failuresKey);
        $user = $request->user();

        $request->session()->regenerate();

        if ($user !== null) {
            $user->forceFill([
                'last_login_at' => now(),
            ])->saveQuietly();

            if ($user->must_change_password) {
                return redirect()->route('account.password.edit');
            }
        }

        return $user?->isClient()
            ? redirect()->route('inspections.index')
            : redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // A página de login não é uma página Inertia. Retornar uma location
        // response força o navegador a carregá-la fora do diálogo de erro do
        // Inertia, que é exibido para respostas HTML não-Inertia.
        return Inertia::location(route('login'));
    }

    private function throwLockout(string $lockoutKey): never
    {
        throw ValidationException::withMessages([
            'email' => 'Muitas tentativas de login. Aguarde '.RateLimiter::availableIn($lockoutKey).' segundos e tente novamente.',
        ]);
    }
}
