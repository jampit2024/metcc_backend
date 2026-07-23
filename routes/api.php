<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExamResultController;
use App\Http\Controllers\ExaminationRegistrationController;
use App\Http\Controllers\ExaminationScheduleController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\PublicScheduleController;
use App\Http\Controllers\SubjectQuestionController;
use App\Http\Controllers\TestItemController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('public/schedule-search', [PublicScheduleController::class, 'search'])
    ->middleware('throttle:30,1');

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:forgot-password');
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
    Route::get('google/redirect', [GoogleAuthController::class, 'redirect']);
    Route::get('google/callback', [GoogleAuthController::class, 'callback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index']);

    Route::get('profile', [ProfileController::class, 'show']);
    Route::match(['put', 'patch'], 'profile/update', [ProfileController::class, 'update']);
    Route::post('profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::delete('profile/photo', [ProfileController::class, 'removePhoto']);

    Route::middleware('role:admin')->group(function () {
        Route::get('roles', [RoleController::class, 'index']);
        Route::patch('users/{user}/status', [UserController::class, 'updateStatus']);
        Route::apiResource('users', UserController::class);
        Route::get('examination-schedules', [ExaminationScheduleController::class, 'index']);
        Route::get('examination-schedules/{examinationSchedule}', [ExaminationScheduleController::class, 'show']);
        Route::post('examination-registrations/{registration}/reschedule', [ExaminationRegistrationController::class, 'reschedule']);
        Route::get('exam-results', [ExamResultController::class, 'index']);

        Route::get('subjects', [SubjectQuestionController::class, 'subjects']);
        Route::post('subjects', [SubjectQuestionController::class, 'storeSubject']);
        Route::get('subjects/{examSubject}', [SubjectQuestionController::class, 'showSubject']);
        Route::post('subjects/{examSubject}/banks', [SubjectQuestionController::class, 'storeBank']);
        Route::get('question-banks/{questionBank}', [SubjectQuestionController::class, 'showBank']);
        Route::post('question-banks/{questionBank}/questions', [SubjectQuestionController::class, 'storeQuestion']);
        Route::patch('exam-questions/{examQuestion}', [SubjectQuestionController::class, 'updateQuestion']);
        Route::post('exam-questions/{examQuestion}/toggle-selection', [SubjectQuestionController::class, 'toggleQuestionSelection']);
        Route::delete('exam-questions/{examQuestion}', [SubjectQuestionController::class, 'destroyQuestion']);
    });

    Route::apiResource('test-items', TestItemController::class);
});
