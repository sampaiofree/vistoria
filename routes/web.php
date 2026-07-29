<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientUnitController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectionResponsibleController;
use App\Http\Controllers\InspectionReferenceDocumentController;
use App\Http\Controllers\InspectionTransitionController;
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
    'organization.active',
    'tenant',
])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

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

    Route::resource('inspections', InspectionController::class)->except(['destroy']);

    Route::post('inspections/{inspection}/responsibles', [InspectionResponsibleController::class, 'store'])->name('inspections.responsibles.store');
    Route::put('inspections/{inspection}/responsibles/{responsible}', [InspectionResponsibleController::class, 'update'])->name('inspections.responsibles.update');
    Route::delete('inspections/{inspection}/responsibles/{responsible}', [InspectionResponsibleController::class, 'destroy'])->name('inspections.responsibles.destroy');
    Route::post('inspections/{inspection}/references', [InspectionReferenceDocumentController::class, 'store'])->name('inspections.references.store');
    Route::delete('inspections/{inspection}/references/{reference}', [InspectionReferenceDocumentController::class, 'destroy'])->name('inspections.references.destroy');

    Route::post('inspections/{inspection}/start', [InspectionTransitionController::class, 'start'])->name('inspections.start');
    Route::post('inspections/{inspection}/submit-for-review', [InspectionTransitionController::class, 'submitForReview'])->name('inspections.submit-for-review');
    Route::post('inspections/{inspection}/return-for-correction', [InspectionTransitionController::class, 'returnForCorrection'])->name('inspections.return-for-correction');
    Route::post('inspections/{inspection}/complete-review', [InspectionTransitionController::class, 'completeReview'])->name('inspections.complete-review');
    Route::post('inspections/{inspection}/approve', [InspectionTransitionController::class, 'approve'])->name('inspections.approve');
    Route::post('inspections/{inspection}/release', [InspectionTransitionController::class, 'release'])->name('inspections.release');
    Route::post('inspections/{inspection}/cancel', [InspectionTransitionController::class, 'cancel'])->name('inspections.cancel');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
});
