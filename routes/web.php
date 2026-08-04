<?php

use App\Http\Controllers\AreaController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientUnitController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefectAssessmentController;
use App\Http\Controllers\DefectController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentDocumentController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectionReferenceDocumentController;
use App\Http\Controllers\InspectionResponsibleController;
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
])->group(function () {
    // Rotas globais não dependem de um tenant. No MVP, o superadministrador
    // não seleciona nem impersona uma organização.
    Route::middleware('organization.active')->get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    // Módulos operacionais exigem uma organização resolvida. ResolveTenant
    // rejeita explicitamente superadministradores, que não possuem tenant.
    Route::middleware([
        'organization.active',
        'tenant',
    ])->group(function (): void {
        Route::resource('equipments', EquipmentController::class)
            ->except(['destroy']);

        Route::patch(
            'equipments/{equipment}/status',
            [EquipmentController::class, 'updateStatus'],
        )->name('equipments.status');

        Route::post(
            'equipments/{equipment}/documents',
            [EquipmentDocumentController::class, 'store'],
        )->name('equipments.documents.store');

        Route::get(
            'equipment-documents/{equipmentDocument}',
            [EquipmentDocumentController::class, 'show'],
        )->name('equipment-documents.show');

        Route::get(
            'equipment-documents/{equipmentDocument}/download',
            [EquipmentDocumentController::class, 'download'],
        )->name('equipment-documents.download');

        Route::patch(
            'equipment-documents/{equipmentDocument}/status',
            [EquipmentDocumentController::class, 'updateStatus'],
        )->name('equipment-documents.status');

        Route::patch(
            'equipment-documents/{equipmentDocument}/current',
            [EquipmentDocumentController::class, 'updateCurrent'],
        )->name('equipment-documents.current');

        Route::get('inspections', [InspectionController::class, 'index'])
            ->name('inspections.index');

        Route::get('inspections/create', [InspectionController::class, 'create'])
            ->name('inspections.create');

        Route::post('inspections', [InspectionController::class, 'store'])
            ->name('inspections.store');

        Route::get('inspections/{inspection}', [InspectionController::class, 'show'])
            ->name('inspections.show');

        Route::get('inspections/{inspection}/defects', [InspectionController::class, 'defects'])
            ->name('inspections.defects');

        Route::get('inspections/{inspection}/photos', [InspectionController::class, 'photos'])
            ->name('inspections.photos');

        Route::get('inspections/{inspection}/documents', [InspectionController::class, 'documents'])
            ->name('inspections.documents');

        Route::get('inspections/{inspection}/history', [InspectionController::class, 'history'])
            ->name('inspections.history');

        Route::get('inspections/{inspection}/report-preview', [InspectionController::class, 'reportPreview'])
            ->name('inspections.report-preview');

        Route::post(
            'inspections/{inspection}/defects',
            [DefectController::class, 'store'],
        )->name('inspections.defects.store');

        Route::post(
            'inspections/{inspection}/defects/{defect}/assessments',
            [DefectAssessmentController::class, 'store'],
        )->name('inspections.defects.assessments.store');

        Route::get('inspections/{inspection}/edit', [InspectionController::class, 'edit'])
            ->name('inspections.edit');

        Route::put('inspections/{inspection}', [InspectionController::class, 'update'])
            ->name('inspections.update');

        Route::post(
            'inspections/{inspection}/responsibles',
            [InspectionResponsibleController::class, 'store'],
        )->name('inspections.responsibles.store');

        Route::patch(
            'inspections/{inspection}/responsibles/{responsible}',
            [InspectionResponsibleController::class, 'update'],
        )->name('inspections.responsibles.update');

        Route::delete(
            'inspections/{inspection}/responsibles/{responsible}',
            [InspectionResponsibleController::class, 'destroy'],
        )->name('inspections.responsibles.destroy');

        Route::put(
            'inspections/{inspection}/reference-documents',
            [InspectionReferenceDocumentController::class, 'update'],
        )->name('inspections.reference-documents.update');

        Route::delete(
            'inspections/{inspection}/reference-documents/{referenceDocument}',
            [InspectionReferenceDocumentController::class, 'destroy'],
        )->name('inspections.reference-documents.destroy');

        Route::post(
            'inspections/{inspection}/start',
            [InspectionTransitionController::class, 'start'],
        )->name('inspections.start');

        Route::post(
            'inspections/{inspection}/submit-for-review',
            [InspectionTransitionController::class, 'submitForReview'],
        )->name('inspections.submit-for-review');

        Route::post(
            'inspections/{inspection}/return-for-correction',
            [InspectionTransitionController::class, 'returnForCorrection'],
        )->name('inspections.return-for-correction');

        Route::post(
            'inspections/{inspection}/complete-review',
            [InspectionTransitionController::class, 'completeReview'],
        )->name('inspections.complete-review');

        Route::post(
            'inspections/{inspection}/approve',
            [InspectionTransitionController::class, 'approve'],
        )->name('inspections.approve');

        Route::post(
            'inspections/{inspection}/release',
            [InspectionTransitionController::class, 'release'],
        )->name('inspections.release');

        Route::post(
            'inspections/{inspection}/cancel',
            [InspectionTransitionController::class, 'cancel'],
        )->name('inspections.cancel');

        Route::get('defects/{defect}', [DefectController::class, 'show'])
            ->name('defects.show');

        Route::get(
            'defect-assessments/{defectAssessment}',
            [DefectAssessmentController::class, 'show'],
        )->name('defect-assessments.show');

        Route::patch(
            'defect-assessments/{defectAssessment}',
            [DefectAssessmentController::class, 'update'],
        )->name('defect-assessments.update');

        Route::post(
            'defect-assessments/{defectAssessment}/complete',
            [DefectAssessmentController::class, 'complete'],
        )->name('defect-assessments.complete');

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
