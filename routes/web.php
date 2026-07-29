<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientUnitController;
use App\Http\Controllers\SubareaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware([
    'auth',
    'user.active',
])->group(function () {
    // Rotas globais não dependem de um tenant. No MVP, o superadministrador
    // não seleciona nem impersona uma organização.
    Route::middleware('organization.active')->get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Módulos operacionais exigem uma organização resolvida. ResolveTenant
    // rejeita explicitamente superadministradores, que não possuem tenant.
    Route::middleware([
        'organization.active',
        'tenant',
    ])->group(function (): void {
        Route::resource('clients', ClientController::class)
            ->except(['destroy']);

        Route::patch(
            'clients/{client}/status',
            [ClientController::class, 'updateStatus'],
        )->name('clients.status');

        Route::scopeBindings()->group(function (): void {
            Route::resource('clients.units', ClientUnitController::class)
                ->parameters([
                    'clients' => 'client',
                    'units' => 'unit',
                ])
                ->shallow()
                ->except(['destroy']);

            Route::patch(
                'units/{unit}/status',
                [ClientUnitController::class, 'updateStatus'],
            )->name('units.status');

            Route::resource('units.areas', AreaController::class)
                ->parameters([
                    'units' => 'unit',
                    'areas' => 'area',
                ])
                ->shallow()
                ->except(['destroy']);

            Route::patch(
                'areas/{area}/status',
                [AreaController::class, 'updateStatus'],
            )->name('areas.status');

            Route::resource('areas.subareas', SubareaController::class)
                ->parameters([
                    'areas' => 'area',
                    'subareas' => 'subarea',
                ])
                ->shallow()
                ->except(['destroy']);

            Route::patch(
                'subareas/{subarea}/status',
                [SubareaController::class, 'updateStatus'],
            )->name('subareas.status');
        });
    });
});
