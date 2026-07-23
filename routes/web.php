<?php

use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminTrainingController;
use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\CompanyProfileController;
use App\Http\Controllers\CourseCatalogueController;
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
use App\Http\Controllers\TrainingCourseController;
use App\Http\Controllers\TrainingProviderController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');
Route::get('/jobs', [JobController::class, 'index'])->name('jobs.index');
Route::get('/jobs/{job:slug}', [JobController::class, 'show'])->name('jobs.show');
Route::get('/cv-builder', fn () => Inertia::render('CvBuilder'))->name('cv-builder');
Route::get('/courses', [CourseCatalogueController::class, 'index'])->name('courses.index');
Route::get('/courses/{course:slug}', [CourseCatalogueController::class, 'show'])->name('courses.show');

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

Route::middleware(['auth', 'role:training_provider'])->prefix('training')->name('training.')->group(function () {
    Route::get('/dashboard', [TrainingProviderController::class, 'dashboard'])->name('dashboard');
    Route::get('/profile', [TrainingProviderController::class, 'edit'])->name('profile');
    Route::post('/profile', [TrainingProviderController::class, 'update'])->name('profile.update');
    Route::post('/profile/request-verification', [TrainingProviderController::class, 'requestVerification'])->name('profile.verify');
    Route::get('/courses', [TrainingCourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/create', [TrainingCourseController::class, 'create'])->name('courses.create');
    Route::post('/courses', [TrainingCourseController::class, 'store'])->name('courses.store');
    Route::get('/courses/{course}', [TrainingCourseController::class, 'show'])->name('courses.show');
    Route::get('/courses/{course}/edit', [TrainingCourseController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{course}', [TrainingCourseController::class, 'update'])->name('courses.update');
    Route::post('/courses/{course}/submit', [TrainingCourseController::class, 'submit'])->name('courses.submit');
    Route::post('/courses/{course}/close', [TrainingCourseController::class, 'close'])->name('courses.close');
    Route::post('/courses/{course}/archive', [TrainingCourseController::class, 'archive'])->name('courses.archive');
    Route::post('/courses/{course}/analyze', [TrainingCourseController::class, 'analyze'])->name('courses.analyze');
});

Route::middleware(['auth', 'role:job_seeker'])->group(function () {
    Route::get('/seeker/courses/recommended', [CourseCatalogueController::class, 'recommendations'])->name('seeker.courses.recommended');
    Route::get('/seeker/courses/saved', [CourseCatalogueController::class, 'saved'])->name('seeker.courses.saved');
    Route::get('/seeker/courses/registrations', [CourseCatalogueController::class, 'registrations'])->name('seeker.courses.registrations');
    Route::post('/courses/{course}/register', [CourseCatalogueController::class, 'register'])->name('courses.register');
    Route::post('/courses/{course}/feedback', [CourseCatalogueController::class, 'feedback'])->name('courses.feedback');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/dashboard', AdminDashboardController::class)->name('admin.dashboard');
    Route::get('/admin/jobs/pending', AdminDashboardController::class)->name('admin.jobs.pending');
    Route::get('/admin/jobs/{job}/review', [JobController::class, 'review'])->name('admin.jobs.review');
    Route::get('/admin/companies/verification', AdminDashboardController::class)->name('admin.companies.verification');
    Route::get('/admin/companies/{company}/verification', [CompanyProfileController::class, 'reviewVerification'])->name('admin.companies.verification.show');
    Route::post('/admin/jobs/{job}/approve', [JobController::class, 'approve'])->name('admin.jobs.approve');
    Route::post('/admin/jobs/{job}/reject', [JobController::class, 'reject'])->name('admin.jobs.reject');
    Route::post('/admin/companies/{company}/verify', [CompanyProfileController::class, 'approveVerification'])->name('admin.companies.verify');
    Route::post('/admin/companies/{company}/reject-verification', [CompanyProfileController::class, 'rejectVerification'])->name('admin.companies.reject-verification');
    Route::get('/admin/training', [AdminTrainingController::class, 'index'])->name('admin.training.index');
    Route::get('/admin/training/providers/{provider}', [AdminTrainingController::class, 'provider'])->name('admin.training.providers.show');
    Route::get('/admin/training/courses/{course}/review', [AdminTrainingController::class, 'course'])->name('admin.training.courses.review');
    Route::post('/admin/training/providers/{provider}/verify', [AdminTrainingController::class, 'verify'])->name('admin.training.providers.verify');
    Route::post('/admin/training/providers/{provider}/reject', [AdminTrainingController::class, 'rejectProvider'])->name('admin.training.providers.reject');
    Route::post('/admin/training/courses/{course}/approve', [AdminTrainingController::class, 'approveCourse'])->name('admin.training.courses.approve');
    Route::post('/admin/training/courses/{course}/reject', [AdminTrainingController::class, 'rejectCourse'])->name('admin.training.courses.reject');
    Route::post('/admin/training/courses/{course}/transition', [AdminTrainingController::class, 'transition'])->name('admin.training.courses.transition');
});

require __DIR__.'/auth.php';
