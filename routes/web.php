<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\AssessmentPhotoController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientUnitController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefectAssessmentController;
use App\Http\Controllers\DefectCategoryController;
use App\Http\Controllers\DefectClassificationController;
use App\Http\Controllers\DefectController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentDocumentController;
use App\Http\Controllers\EquipmentRevisionController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectionLocationMapAssetController;
use App\Http\Controllers\InspectionLocationMapController;
use App\Http\Controllers\InspectionLocationMarkerController;
use App\Http\Controllers\InspectionOverviewController;
use App\Http\Controllers\InspectionOverviewPhotoController;
use App\Http\Controllers\InspectionReferenceDocumentController;
use App\Http\Controllers\InspectionResponsibleController;
use App\Http\Controllers\InspectionTransitionController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\ReinspectionChecklistController;
use App\Http\Controllers\SubareaController;
use App\Http\Controllers\UserSettingsController;
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
    Route::middleware(['organization.active', 'password.changed'])->get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('organization.active')->group(function (): void {
        Route::get('/account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
        Route::put('/account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');
    });

    // Módulos operacionais exigem uma organização resolvida. ResolveTenant
    // rejeita explicitamente superadministradores, que não possuem tenant.
    Route::middleware([
        'organization.active',
        'tenant',
        'password.changed',
    ])->group(function (): void {
        Route::get('/settings/company', [OrganizationSettingsController::class, 'edit'])->name('settings.company.edit');
        Route::put('/settings/company', [OrganizationSettingsController::class, 'update'])->name('settings.company.update');
        Route::delete('/settings/company/logo', [OrganizationSettingsController::class, 'destroyLogo'])->name('settings.company.logo.destroy');
        Route::delete('/settings/company/icon', [OrganizationSettingsController::class, 'destroyIcon'])->name('settings.company.icon.destroy');

        Route::get('/settings/users', [UserSettingsController::class, 'index'])->name('settings.users.index');
        Route::get('/settings/users/create', [UserSettingsController::class, 'create'])->name('settings.users.create');
        Route::post('/settings/users', [UserSettingsController::class, 'store'])->name('settings.users.store');
        Route::get('/settings/users/{user}/edit', [UserSettingsController::class, 'edit'])->name('settings.users.edit');
        Route::put('/settings/users/{user}', [UserSettingsController::class, 'update'])->name('settings.users.update');
        Route::patch('/settings/users/{user}/status', [UserSettingsController::class, 'updateStatus'])->name('settings.users.status');
        Route::post('/settings/users/{user}/temporary-password', [UserSettingsController::class, 'resetPassword'])->name('settings.users.reset-password');

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

        Route::post(
            'equipments/{equipment}/revisions',
            [EquipmentRevisionController::class, 'store'],
        )->name('equipments.revisions.store');

        Route::put(
            'equipment-revisions/{equipmentRevision}',
            [EquipmentRevisionController::class, 'update'],
        )->name('equipment-revisions.update');

        Route::delete(
            'equipment-revisions/{equipmentRevision}',
            [EquipmentRevisionController::class, 'destroy'],
        )->name('equipment-revisions.destroy');

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

        Route::get('inspections/{inspection}/report-overview', [InspectionOverviewController::class, 'show'])
            ->name('inspections.report-overview');

        Route::put(
            'inspections/{inspection}/report-overview/blocks/{position}',
            [InspectionOverviewController::class, 'update'],
        )->whereIn('position', ['1', '2'])->name('inspections.report-overview.blocks.update');

        Route::post(
            'inspections/{inspection}/report-overview/blocks/{position}/photos/{slot}',
            [InspectionOverviewPhotoController::class, 'store'],
        )->whereIn('position', ['1', '2'])->whereIn('slot', ['1', '2'])->name('inspections.report-overview.photos.store');

        Route::get(
            'inspection-overview-photos/{overviewPhoto}/{variant?}',
            [InspectionOverviewPhotoController::class, 'show'],
        )->whereIn('variant', ['optimized', 'thumbnail', 'original'])->name('inspection-overview-photos.show');

        Route::post(
            'inspection-overview-photos/{overviewPhoto}/retry',
            [InspectionOverviewPhotoController::class, 'retry'],
        )->name('inspection-overview-photos.retry');

        Route::delete(
            'inspection-overview-photos/{overviewPhoto}',
            [InspectionOverviewPhotoController::class, 'destroy'],
        )->name('inspection-overview-photos.destroy');

        Route::get('inspections/{inspection}/defects', [InspectionController::class, 'defects'])
            ->name('inspections.defects');

        Route::get('inspections/{inspection}/defects/create', [DefectController::class, 'create'])
            ->name('inspections.defects.create');

        Route::get('inspections/{inspection}/locations', [InspectionController::class, 'locations'])
            ->name('inspections.locations');

        Route::post('inspections/{inspection}/location-maps', [InspectionLocationMapController::class, 'store'])
            ->name('inspections.location-maps.store');
        Route::post('inspections/{inspection}/location-maps/copy-previous', [InspectionLocationMapController::class, 'copyPrevious'])
            ->name('inspections.location-maps.copy-previous');
        Route::put('inspections/{inspection}/location-maps/order', [InspectionLocationMapController::class, 'reorder'])
            ->name('inspections.location-maps.reorder');
        Route::get('inspections/{inspection}/location-maps/create', [InspectionLocationMapController::class, 'create'])
            ->name('inspections.location-maps.create');
        Route::get('inspection-location-maps/{map}/edit', [InspectionLocationMapController::class, 'edit'])
            ->name('inspection-location-maps.edit');
        Route::get('inspection-location-maps/{map}/editor', [InspectionLocationMapController::class, 'editor'])
            ->name('inspection-location-maps.editor');
        Route::post('inspection-location-maps/{map}/markers', [InspectionLocationMarkerController::class, 'store'])
            ->name('inspection-location-maps.markers.store');
        Route::put('inspection-location-maps/{map}/markers/order', [InspectionLocationMarkerController::class, 'reorder'])
            ->name('inspection-location-maps.markers.reorder');
        Route::put('inspection-location-markers/{marker}', [InspectionLocationMarkerController::class, 'update'])
            ->name('inspection-location-markers.update');
        Route::delete('inspection-location-markers/{marker}', [InspectionLocationMarkerController::class, 'destroy'])
            ->name('inspection-location-markers.destroy');
        Route::put('inspection-location-markers/{marker}/photos', [InspectionLocationMarkerController::class, 'syncPhotos'])
            ->name('inspection-location-markers.photos.sync');
        Route::put('inspection-location-maps/{map}', [InspectionLocationMapController::class, 'update'])
            ->name('inspection-location-maps.update');
        Route::delete('inspection-location-maps/{map}', [InspectionLocationMapController::class, 'destroy'])
            ->name('inspection-location-maps.destroy');
        Route::post('inspection-location-maps/{map}/source', [InspectionLocationMapController::class, 'source'])
            ->name('inspection-location-maps.source');
        Route::post('inspection-location-maps/{map}/retry', [InspectionLocationMapController::class, 'retry'])
            ->name('inspection-location-maps.retry');
        Route::get('inspection-location-maps/{map}/background/{variant?}', [InspectionLocationMapAssetController::class, 'background'])
            ->where('variant', 'thumbnail')
            ->name('inspection-location-maps.background');

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

        Route::post(
            'defect-assessments/{defectAssessment}/photos',
            [DefectAssessmentController::class, 'storePhoto'],
        )->name('defect-assessments.photos.store');

        Route::patch(
            'defect-assessments/{defectAssessment}/status',
            [DefectAssessmentController::class, 'changeStatus'],
        )->name('defect-assessments.status.update');

        Route::put(
            'defect-assessments/{defectAssessment}/gut',
            [DefectAssessmentController::class, 'updateGut'],
        )->name('defect-assessments.gut.update');

        Route::put(
            'defect-assessments/{defectAssessment}/classification',
            [DefectAssessmentController::class, 'assignClassification'],
        )->name('defect-assessments.manual-classification.update');

        Route::put(
            'defect-assessments/{defectAssessment}/quantity',
            [DefectAssessmentController::class, 'updateQuantity'],
        )->name('defect-assessments.quantity.update');

        Route::patch(
            'defect-assessments/{defectAssessment}/photos/order',
            [DefectAssessmentController::class, 'reorderPhotos'],
        )->name('defect-assessments.photos.reorder');

        Route::get(
            'assessment-photos/{assessmentPhoto}/{variant?}',
            [AssessmentPhotoController::class, 'show'],
        )->name('assessment-photos.show');

        Route::post(
            'assessment-photos/{assessmentPhoto}/retry',
            [DefectAssessmentController::class, 'retryPhoto'],
        )->name('assessment-photos.retry');

        Route::delete(
            'assessment-photos/{assessmentPhoto}',
            [DefectAssessmentController::class, 'destroyPhoto'],
        )->name('assessment-photos.destroy');

        Route::post(
            'inspections/{inspection}/defects/{defect}/related',
            [DefectController::class, 'storeRelated'],
        )->name('inspections.defects.related.store');

        Route::get(
            'inspections/{inspection}/reinspection-checklist',
            [ReinspectionChecklistController::class, 'show'],
        )->name('inspections.reinspection-checklist');

        Route::get('inspections/{inspection}/edit', [InspectionController::class, 'edit'])
            ->name('inspections.edit');

        Route::put('inspections/{inspection}', [InspectionController::class, 'update'])
            ->name('inspections.update');

        Route::put('inspections/{inspection}/report-metadata', [InspectionController::class, 'updateReportMetadata'])
            ->name('inspections.report-metadata.update');

        Route::put('inspections/{inspection}/general-aspects', [InspectionController::class, 'updateGeneralAspects'])
            ->name('inspections.general-aspects.update');

        Route::get('inspections/{inspection}/team', [InspectionController::class, 'team'])
            ->name('inspections.team');

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
            'inspections/{inspection}/generate-report',
            [InspectionTransitionController::class, 'generateReport'],
        )->name('inspections.generate-report');

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

        Route::resource('defect-categories', DefectCategoryController::class)
            ->parameters(['defect-categories' => 'defectCategory'])
            ->except(['destroy']);
        Route::patch('defect-categories/{defectCategory}/status', [DefectCategoryController::class, 'updateStatus'])
            ->name('defect-categories.status');
        Route::get('defect-categories/{defectCategory}/gut/edit', [DefectCategoryController::class, 'editGut'])
            ->name('defect-categories.gut.edit');
        Route::put('defect-categories/{defectCategory}/gut', [DefectCategoryController::class, 'updateGut'])
            ->name('defect-categories.gut.update');
        Route::get('defect-categories/{defectCategory}/classifications/create', [DefectClassificationController::class, 'create'])
            ->name('defect-categories.classifications.create');
        Route::post('defect-categories/{defectCategory}/classifications', [DefectClassificationController::class, 'store'])
            ->name('defect-categories.classifications.store');
        Route::get('defect-classifications/{defectClassification}/edit', [DefectClassificationController::class, 'edit'])
            ->name('defect-classifications.edit');
        Route::patch('defect-classifications/{defectClassification}', [DefectClassificationController::class, 'update'])
            ->name('defect-classifications.update');
        Route::patch('defect-classifications/{defectClassification}/status', [DefectClassificationController::class, 'updateStatus'])
            ->name('defect-classifications.status');

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
