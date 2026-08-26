<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedSessionController extends Controller
{
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

        if (! Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'status' => UserStatus::Active->value,
        ], $request->boolean('remember'))) {
            throw ValidationException::withMessages([
                'email' => 'E-mail ou senha incorretos, ou a conta está inativa.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user !== null) {
            $user->forceFill([
                'last_login_at' => now(),
            ])->saveQuietly();

            if ($user->must_change_password) {
                return redirect()->route('account.password.edit');
            }
        }

        return redirect()->intended(route('dashboard'));
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
}
