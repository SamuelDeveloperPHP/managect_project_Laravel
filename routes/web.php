<?php

use App\Http\Controllers\CompanyAccessController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\MasterCompaniesController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\GanttTaskController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectBacklogController;
use App\Http\Controllers\ProjectGanttApiController;
use App\Http\Controllers\ProjectOverviewController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReleaseVersionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'canRegister' => app()->environment(['local', 'development', 'dev', 'testing']),
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
});

Route::prefix('master')->name('master.')->middleware(['auth', 'company', 'role:master', 'throttle:authenticated-web'])->group(function () {
    Route::get('/companies', [MasterCompaniesController::class, 'index'])->name('companies.index');
    Route::post('/companies/select', [MasterCompaniesController::class, 'select'])->name('companies.select');
});

Route::middleware(['auth', 'company', 'throttle:authenticated-web'])->group(function () {
    Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::post('/projects', [ProjectController::class, 'store'])->middleware('permission:can_manage_projects')->name('projects.store');
    Route::get('/projects/{project}/backlog', [ProjectBacklogController::class, 'index'])->name('projects.backlog.index');
    Route::get('/projects/{project}/timeline', [GanttTaskController::class, 'index'])->name('projects.timeline.index');
    Route::get('/projects/{project}/overview', [ProjectOverviewController::class, 'show'])->name('projects.overview');
    Route::post('/projects/{project}/backlog', [ProjectBacklogController::class, 'store'])->middleware('permission:can_manage_projects')->name('projects.backlog.store');
    Route::patch('/projects/{project}/backlog/{item}/status', [ProjectBacklogController::class, 'updateStatus'])->middleware('permission:can_manage_projects')->name('projects.backlog.status');
    Route::put('/projects/{project}/backlog/{item}/gantt-tasks', [ProjectBacklogController::class, 'syncGanttTasks'])->middleware('permission:can_manage_projects')->name('projects.backlog.gantt-tasks.sync');
    Route::get('/api/projects/{project}/gantt', [ProjectGanttApiController::class, 'show'])->name('projects.gantt.show');
    Route::post('/api/projects/{project}/gantt', [ProjectGanttApiController::class, 'save'])->middleware('permission:can_manage_projects')->name('projects.gantt.save');
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
    Route::patch('/users/{user}/status', [CompanyAccessController::class, 'setActive'])->name('users.status');
    Route::get('/audit', [CompanyAccessController::class, 'audit'])->name('audit.index');
    Route::get('/versions', [ReleaseVersionController::class, 'index'])->name('versions.index');
});

require __DIR__.'/auth.php';
