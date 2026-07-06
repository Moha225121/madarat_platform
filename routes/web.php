<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\CvAnalysisController;
use App\Http\Controllers\EmployerDashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\JobController;
use App\Http\Controllers\JobDescriptionController;
use App\Http\Controllers\JobSeekerDashboardController;
use App\Http\Controllers\JobSeekerProfileController;
use App\Http\Controllers\MatchingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleRedirectController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job:slug}', [JobController::class, 'show'])->name('jobs.show');
Route::get('/cv-builder', fn () => Inertia::render('CvBuilder'))->name('cv-builder');

Route::get('/dashboard', RoleRedirectController::class)->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/assistant/message', [AssistantController::class, 'store'])->name('assistant.message');
});

Route::middleware(['auth', 'role:job_seeker'])->group(function () {
    Route::get('/seeker/dashboard', JobSeekerDashboardController::class)->name('seeker.dashboard');
    Route::get('/seeker/profile', [JobSeekerProfileController::class, 'edit'])->name('seeker.profile');
    Route::post('/seeker/profile', [JobSeekerProfileController::class, 'store'])->name('seeker.profile.store');
    Route::get('/seeker/cv-analysis', [CvAnalysisController::class, 'show'])->name('seeker.cv');
    Route::post('/seeker/cv-analysis', [CvAnalysisController::class, 'store'])->name('seeker.cv.store');
    Route::post('/jobs/{job}/apply', [ApplicationController::class, 'store'])->name('jobs.apply');
    Route::get('/seeker/applications', [ApplicationController::class, 'seekerIndex'])->name('seeker.applications');
});

Route::middleware(['auth', 'role:employer'])->group(function () {
    Route::get('/employer/dashboard', EmployerDashboardController::class)->name('employer.dashboard');
    Route::get('/employer/company', [CompanyProfileController::class, 'edit'])->name('employer.company');
    Route::post('/employer/company', [CompanyProfileController::class, 'store'])->name('employer.company.store');
    Route::post('/employer/company/request-verification', [CompanyProfileController::class, 'requestVerification'])->name('employer.company.request-verification');
    Route::get('/employer/jobs/create', [JobController::class, 'create'])->name('employer.jobs.create');
    Route::post('/employer/jobs', [JobController::class, 'store'])->name('employer.jobs.store');
    Route::get('/employer/jobs/{job}/edit', [JobController::class, 'edit'])->name('employer.jobs.edit');
    Route::put('/employer/jobs/{job}', [JobController::class, 'update'])->name('employer.jobs.update');
    Route::post('/employer/jobs/generate-description', [JobDescriptionController::class, 'store'])->name('employer.jobs.generate');
    Route::get('/employer/jobs/{job}/matches', [MatchingController::class, 'show'])->name('employer.jobs.matches');
    Route::post('/employer/applications/{application}/shortlist', [ApplicationController::class, 'shortlist'])->name('employer.applications.shortlist');
    Route::post('/employer/applications/{application}/invite-interview', [ApplicationController::class, 'inviteInterview'])->name('employer.applications.invite');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', AdminDashboardController::class)->name('admin.dashboard');
    Route::get('/admin/jobs/pending', AdminDashboardController::class)->name('admin.jobs.pending');
    Route::get('/admin/companies/verification', AdminDashboardController::class)->name('admin.companies.verification');
    Route::get('/admin/companies/{company}/verification', [CompanyProfileController::class, 'reviewVerification'])->name('admin.companies.verification.show');
    Route::post('/admin/jobs/{job}/approve', [JobController::class, 'approve'])->name('admin.jobs.approve');
    Route::post('/admin/jobs/{job}/reject', [JobController::class, 'reject'])->name('admin.jobs.reject');
    Route::post('/admin/companies/{company}/verify', [CompanyProfileController::class, 'approveVerification'])->name('admin.companies.verify');
    Route::post('/admin/companies/{company}/reject-verification', [CompanyProfileController::class, 'rejectVerification'])->name('admin.companies.reject-verification');
});

require __DIR__.'/auth.php';
