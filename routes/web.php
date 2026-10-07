<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\CompanyAccessController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\GanttTaskController;
use App\Http\Controllers\MasterCompaniesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectBacklogController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectGanttApiController;
use App\Http\Controllers\ProjectOverviewController;
use App\Http\Controllers\ReleaseVersionController;
use App\Http\Controllers\TwoFactorManagementController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'canRegister' => true,
]))->name('home');

Route::get('/dashboard', AdminDashboardController::class)
    ->middleware(['auth', 'verified', 'company', 'throttle:authenticated-web'])
    ->name('dashboard');

Route::middleware(['auth', 'company', 'throttle:authenticated-web'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->middleware('throttle:sensitive-account-action')
        ->name('profile.destroy');

    Route::prefix('/user/two-factor')->name('two-factor.')->middleware('throttle:sensitive-account-action')->group(function () {
        Route::post('/', [TwoFactorManagementController::class, 'start'])->name('start');
        Route::post('/confirm', [TwoFactorManagementController::class, 'confirm'])->name('confirm');
        Route::delete('/pending', [TwoFactorManagementController::class, 'cancel'])->name('cancel');
        Route::post('/recovery-codes', [TwoFactorManagementController::class, 'regenerateRecoveryCodes'])->name('recovery-codes');
        Route::delete('/', [TwoFactorManagementController::class, 'disable'])->name('disable');
    });
});

Route::prefix('master')->name('master.')->middleware(['auth', 'company', 'role:master', 'throttle:authenticated-web'])->group(function () {
    Route::get('/companies', [MasterCompaniesController::class, 'index'])->name('companies.index');
    Route::post('/companies', [MasterCompaniesController::class, 'store'])->name('companies.store');
    Route::patch('/companies/{company}/status', [MasterCompaniesController::class, 'setActive'])->name('companies.status');
    Route::post('/companies/select', [MasterCompaniesController::class, 'select'])->name('companies.select');
});

Route::get('/company/versions', [ReleaseVersionController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('company.versions.index');

Route::middleware(['auth', 'company', 'throttle:authenticated-web'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('/projects/create', [ProjectController::class, 'create'])->middleware('permission:can_manage_projects')->name('projects.create');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware(['permission:can_manage_projects', 'throttle:sensitive-account-action'])->name('projects.store');
    Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])->middleware('permission:can_manage_projects')->name('projects.edit');
    Route::put('/projects/{project}', [ProjectController::class, 'update'])->middleware(['permission:can_manage_projects', 'throttle:sensitive-account-action'])->name('projects.update');
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->middleware('permission:can_manage_projects')->name('projects.destroy');
    Route::get('/projects/{project}/attachments/{attachment}', [ProjectController::class, 'downloadAttachment'])->name('projects.attachments.download');
    Route::delete('/projects/{project}/attachments/{attachment}', [ProjectController::class, 'destroyAttachment'])->middleware('permission:can_manage_projects')->name('projects.attachments.destroy');
    Route::get('/projects/{project}/backlogs', [ProjectBacklogController::class, 'index'])->name('projects.backlog.index');
    Route::post('/projects/{project}/backlogs', [ProjectBacklogController::class, 'store'])->middleware('permission:can_manage_projects')->name('projects.backlog.store');
    Route::get('/projects/{project}/backlogs/{backlog}', [ProjectBacklogController::class, 'show'])->name('projects.backlog.show');
    Route::post('/projects/{project}/backlogs/{backlog}/items', [ProjectBacklogController::class, 'storeItem'])->middleware('permission:can_manage_projects')->name('projects.backlog.items.store');
    Route::patch('/projects/{project}/backlogs/{backlog}/items/{item}/status', [ProjectBacklogController::class, 'updateStatus'])->middleware('permission:can_manage_projects')->name('projects.backlog.items.status');
    Route::put('/projects/{project}/backlogs/{backlog}/items/{item}/gantt-tasks', [ProjectBacklogController::class, 'syncGanttTasks'])->middleware('permission:can_manage_projects')->name('projects.backlog.items.gantt-tasks.sync');
    Route::get('/projects/{project}/backlogs/{backlog}/timeline', [GanttTaskController::class, 'index'])->name('projects.timeline.index');
    Route::get('/projects/{project}/overview', [ProjectOverviewController::class, 'show'])->name('projects.overview');
    Route::get('/api/projects/{project}/backlogs/{backlog}/gantt', [ProjectGanttApiController::class, 'show'])->name('projects.gantt.show');
    Route::post('/api/projects/{project}/backlogs/{backlog}/gantt', [ProjectGanttApiController::class, 'save'])->middleware('permission:can_manage_projects')->name('projects.gantt.save');
});

Route::prefix('company')->name('company.')->middleware(['auth', 'company', 'role:admin,master', 'throttle:authenticated-web'])->group(function () {
    Route::get('/settings', [CompanySettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [CompanySettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/logo', [CompanySettingsController::class, 'uploadLogo'])->middleware('throttle:sensitive-account-action')->name('settings.logo');
    Route::post('/settings/documents', [CompanySettingsController::class, 'uploadDocument'])->middleware('throttle:sensitive-account-action')->name('documents.store');
    Route::get('/settings/documents/{document}', [CompanySettingsController::class, 'downloadDocument'])->name('documents.download');
    Route::get('/users', [CompanyAccessController::class, 'users'])->name('users.index');
    Route::post('/users', [CompanyAccessController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [CompanyAccessController::class, 'update'])->name('users.update');
    Route::post('/users/{user}/two-factor/reset', [CompanyAccessController::class, 'resetTwoFactor'])->middleware('throttle:sensitive-account-action')->name('users.two-factor.reset');
    Route::post('/users/{user}/transfer-admin', [CompanyAccessController::class, 'transferAdmin'])->middleware('throttle:sensitive-account-action')->name('users.transfer-admin');
    Route::patch('/users/{user}/status', [CompanyAccessController::class, 'setActive'])->name('users.status');
    Route::get('/audit', [CompanyAccessController::class, 'audit'])->name('audit.index');
});

require __DIR__.'/auth.php';
