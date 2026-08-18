<?php

namespace App\Http\Controllers;

use App\Actions\Account\ChangePassword;
use App\Http\Requests\Account\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class AccountPasswordController extends Controller
{
    public function edit(): InertiaResponse
    {
        return Inertia::render('Account/Password');
    }

    public function update(UpdatePasswordRequest $request, ChangePassword $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated('password'));

        return redirect()->route('dashboard')->with('success', 'Senha atualizada.');
    }
}
