<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TraineeAuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\UserController;

// SPA Authentication (session-based) - for frontend
Route::prefix('trainee')->group(function () {
    Route::post('/login', [TraineeAuthController::class, 'login']);

    Route::middleware('auth:trainee-sanctum')->group(function () {
        Route::post('/logout', [TraineeAuthController::class, 'logout'])->name('trainee.logout');
        Route::get('/me', [TraineeAuthController::class, 'me'])->name('trainee.me');
        Route::get('/enrolled-courses', [CourseController::class, 'getEnrolledCourses'])->name('trainee.enrolled-courses');
    });
});

Route::prefix('admin')->group(function () {
    Route::post('/login', [UserController::class, 'login']);

    Route::middleware('auth:admin-sanctum')->group(function () {
        Route::post('/logout', [UserController::class, 'logout'])->name('admin.logout');
        Route::get('/me', [UserController::class, 'me'])->name('admin.me');
        Route::get('/enrolled-courses', [CourseController::class, 'getEnrolledCourses'])->name('admin.enrolled-courses');
        Route::get('/courses', [CourseController::class, 'index']);
        Route::get('/courses/{id}', [CourseController::class, 'show']);
        Route::get('/courses-schedule/{id}', [ScheduleController::class, 'getCourseScheduleById']);
        Route::get('/courses-schedule', [ScheduleController::class, 'getAllSchedules']);
    });
});

// Add a generic login route that redirects to trainee login
Route::post('/login', [TraineeAuthController::class, 'login'])->name('login');
