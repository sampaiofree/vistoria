<?php

use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\AssessmentPhotoController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DefectAssessmentController;
use App\Http\Controllers\DefectAssessmentLocationController;
use App\Http\Controllers\DefectController;
use App\Http\Controllers\EquipmentController;
use App\Http\Controllers\EquipmentImportController;
use App\Http\Controllers\GlobalOrganizationController;
use App\Http\Controllers\GeneralAspectsTemplateController;
use App\Http\Controllers\InspectionController;
use App\Http\Controllers\InspectionCorrectionRequestController;
use App\Http\Controllers\InspectionLocationMapAssetController;
use App\Http\Controllers\InspectionOverviewController;
use App\Http\Controllers\InspectionOverviewPhotoController;
use App\Http\Controllers\InspectionResponsibleController;
use App\Http\Controllers\InspectionTransitionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizationSettingsController;
use App\Http\Controllers\ReinspectionChecklistController;
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

    Route::middleware(['password.changed', 'global.super-admin'])->prefix('admin')->name('admin.')->group(function (): void {
        Route::get('/organizations', [GlobalOrganizationController::class, 'index'])->name('organizations.index');
        Route::get('/organizations/create', [GlobalOrganizationController::class, 'create'])->name('organizations.create');
        Route::post('/organizations', [GlobalOrganizationController::class, 'store'])->name('organizations.store');
        Route::get('/organizations/{organization}/edit', [GlobalOrganizationController::class, 'edit'])->name('organizations.edit');
        Route::put('/organizations/{organization}', [GlobalOrganizationController::class, 'update'])->name('organizations.update');
        Route::patch('/organizations/{organization}/status', [GlobalOrganizationController::class, 'updateStatus'])->name('organizations.status');
    });

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
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');

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

        Route::resource('settings/inspection-report/general-aspects', GeneralAspectsTemplateController::class)
            ->parameters(['general-aspects' => 'generalAspectsTemplate'])
            ->names('settings.inspection-report.general-aspects')
            ->except(['show']);

        Route::get('equipments/import', [EquipmentImportController::class, 'create'])
            ->name('equipments.import.create');
        Route::get('equipments/import/preview', [EquipmentImportController::class, 'legacyPreview'])
            ->name('equipments.import.preview.legacy');
        Route::post('equipments/import/preview', [EquipmentImportController::class, 'preview'])
            ->name('equipments.import.preview');
        Route::post('equipments/import/confirm', [EquipmentImportController::class, 'confirm'])
            ->name('equipments.import.confirm');

        Route::resource('equipments', EquipmentController::class);

        Route::patch(
            'equipments/{equipment}/status',
            [EquipmentController::class, 'updateStatus'],
        )->name('equipments.status');

        Route::get('inspections', [InspectionController::class, 'index'])
            ->name('inspections.index');

        Route::get('inspections/create', [InspectionController::class, 'create'])
            ->name('inspections.create');

        Route::get('inspections/equipment-options', [InspectionController::class, 'equipmentOptions'])
            ->name('inspections.equipment-options');

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
        )->whereIn('variant', ['optimized', 'thumbnail'])->name('inspection-overview-photos.show');

        Route::delete(
            'inspection-overview-photos/{overviewPhoto}',
            [InspectionOverviewPhotoController::class, 'destroy'],
        )->name('inspection-overview-photos.destroy');

        Route::get('inspections/{inspection}/defects', [InspectionController::class, 'defects'])
            ->name('inspections.defects');

        Route::get('inspections/{inspection}/classifications', [InspectionController::class, 'classifications'])
            ->name('inspections.classifications');

        Route::get('inspections/{inspection}/defects/create', [DefectController::class, 'create'])
            ->name('inspections.defects.create');

        Route::get('defect-location-map-versions/{mapVersion}/background/{variant?}', [InspectionLocationMapAssetController::class, 'background'])
            ->where('variant', 'thumbnail')
            ->name('defect-location-map-versions.background');

        Route::get('inspections/{inspection}/photos', [InspectionController::class, 'photos'])
            ->name('inspections.photos');

        Route::get('inspections/{inspection}/history', [InspectionController::class, 'history'])
            ->name('inspections.history');

        Route::get('inspections/{inspection}/report-preview', [InspectionController::class, 'reportPreview'])
            ->name('inspections.report-preview');

        Route::put('inspections/{inspection}/classification-m2-links', [InspectionController::class, 'updateClassificationM2Links'])
            ->name('inspections.classification-m2-links.update');

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

        Route::post(
            'defect-assessments/{defectAssessment}/location-map',
            [DefectAssessmentLocationController::class, 'storeMap'],
        )->name('defect-assessments.location-map.store');

        Route::delete(
            'defect-assessments/{defectAssessment}/location-map',
            [DefectAssessmentLocationController::class, 'destroyMap'],
        )->name('defect-assessments.location-map.destroy');

        Route::get(
            'defect-assessments/{defectAssessment}/location/editor',
            [DefectAssessmentLocationController::class, 'editor'],
        )->name('defect-assessments.location.editor');

        Route::put(
            'defect-assessments/{defectAssessment}/location',
            [DefectAssessmentLocationController::class, 'update'],
        )->name('defect-assessments.location.update');

        Route::delete(
            'defect-assessments/{defectAssessment}/location',
            [DefectAssessmentLocationController::class, 'destroy'],
        )->name('defect-assessments.location.destroy');

        Route::patch(
            'defect-assessments/{defectAssessment}/status',
            [DefectAssessmentController::class, 'changeStatus'],
        )->name('defect-assessments.status.update');

        Route::put(
            'defect-assessments/{defectAssessment}/gut',
            [DefectAssessmentController::class, 'updateGut'],
        )->name('defect-assessments.gut.update');

        Route::put(
            'defect-assessments/{defectAssessment}/tel',
            [DefectAssessmentController::class, 'updateTel'],
        )->name('defect-assessments.tel.update');

        Route::post(
            'defect-assessments/{defectAssessment}/quantities',
            [DefectAssessmentController::class, 'storeQuantity'],
        )->name('defect-assessments.quantities.store');

        Route::put(
            'defect-assessment-quantities/{defectAssessmentQuantity}',
            [DefectAssessmentController::class, 'updateQuantity'],
        )->name('defect-assessment-quantities.update');

        Route::delete(
            'defect-assessment-quantities/{defectAssessmentQuantity}',
            [DefectAssessmentController::class, 'destroyQuantity'],
        )->name('defect-assessment-quantities.destroy');

        Route::patch(
            'defect-assessments/{defectAssessment}/photos/order',
            [DefectAssessmentController::class, 'reorderPhotos'],
        )->name('defect-assessments.photos.reorder');

        Route::get(
            'assessment-photos/{assessmentPhoto}/{variant?}',
            [AssessmentPhotoController::class, 'show'],
        )->whereIn('variant', ['optimized', 'thumbnail'])->name('assessment-photos.show');

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

        Route::put('inspections/{inspection}/report-revision', [InspectionController::class, 'updateReportRevision'])
            ->name('inspections.report-revision.update');

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
            'inspections/{inspection}/start-review',
            [InspectionTransitionController::class, 'startReview'],
        )->name('inspections.start-review');

        Route::post(
            'inspections/{inspection}/approve',
            [InspectionTransitionController::class, 'approve'],
        )->name('inspections.approve');

        Route::post(
            'inspections/{inspection}/return-for-review',
            [InspectionTransitionController::class, 'returnForReview'],
        )->name('inspections.return-for-review');

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

        Route::post(
            'defect-assessments/{defectAssessment}/correction-requests',
            [InspectionCorrectionRequestController::class, 'store'],
        )->name('defect-assessment-correction-requests.store');

        Route::patch(
            'inspection-correction-requests/{correctionRequest}',
            [InspectionCorrectionRequestController::class, 'update'],
        )->name('inspection-correction-requests.update');

        Route::delete(
            'inspection-correction-requests/{correctionRequest}',
            [InspectionCorrectionRequestController::class, 'destroy'],
        )->name('inspection-correction-requests.destroy');

        Route::patch(
            'inspection-correction-requests/{correctionRequest}/address',
            [InspectionCorrectionRequestController::class, 'address'],
        )->name('inspection-correction-requests.address');

        Route::patch(
            'inspection-correction-requests/{correctionRequest}/mark-pending',
            [InspectionCorrectionRequestController::class, 'markPending'],
        )->name('inspection-correction-requests.mark-pending');

        Route::patch(
            'inspection-correction-requests/{correctionRequest}/close',
            [InspectionCorrectionRequestController::class, 'close'],
        )->name('inspection-correction-requests.close');

        Route::post(
            'inspection-correction-requests/{correctionRequest}/replace',
            [InspectionCorrectionRequestController::class, 'replace'],
        )->name('inspection-correction-requests.replace');

        Route::post(
            'inspection-correction-requests/{correctionRequest}/children',
            [InspectionCorrectionRequestController::class, 'storeChild'],
        )->name('inspection-correction-requests.children.store');

        Route::resource('clients', ClientController::class)
            ->except(['destroy']);

        Route::patch(
            'clients/{client}/status',
            [ClientController::class, 'updateStatus'],
        )->name('clients.status');

        Route::scopeBindings()->group(function (): void {});
    });
});
