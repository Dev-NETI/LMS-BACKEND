<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TraineeAuthController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AnnouncementReplyController;
use App\Http\Controllers\CourseDetailController;
use App\Http\Controllers\TrainingMaterialController;
use App\Http\Controllers\CourseContentController;

// SPA Authentication (session-based) - for frontend
Route::prefix('trainee')->group(function () {
    Route::post('/login', [TraineeAuthController::class, 'login']);

    Route::middleware('auth:trainee-sanctum')->group(function () {
        Route::post('/logout', [TraineeAuthController::class, 'logout'])->name('trainee.logout');
        Route::get('/me', [TraineeAuthController::class, 'me'])->name('trainee.me');
        Route::get('/enrolled-courses', [CourseController::class, 'getEnrolledCourses'])->name('trainee.enrolled-courses');
        Route::get('/schedules/{scheduleId}/announcements', [AnnouncementController::class, 'getBySchedule']);
        Route::get('/announcements/{announcementId}/replies', [AnnouncementReplyController::class, 'index']);
        Route::post('/announcements/{announcementId}/replies', [AnnouncementReplyController::class, 'store']);
        Route::get('/replies/{reply}', [AnnouncementReplyController::class, 'show']);
        Route::put('/replies/{reply}', [AnnouncementReplyController::class, 'update']);
        Route::delete('/replies/{reply}', [AnnouncementReplyController::class, 'destroy']);

        // Course Content routes for trainees (read-only access for self-pace learning)
        Route::get('/courses/{courseId}/content', [CourseContentController::class, 'getByCourse']);
        Route::get('/course-content/{courseContent}', [CourseContentController::class, 'show']);
        Route::get('/course-content/{courseContent}/download', [CourseContentController::class, 'download'])->middleware('secure.file');
        Route::get('/course-content/{courseContent}/view', [CourseContentController::class, 'view'])->middleware('secure.file');
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
        Route::get('/courses-schedule/{id}', [ScheduleController::class, 'getCourseScheduleByCourseId']);
        Route::get('/courses/schedules/{id}', [ScheduleController::class, 'getCourseScheduleById']);

        Route::apiResource('announcements', AnnouncementController::class);
        Route::get('/schedules/{sched_id}/announcements', [AnnouncementController::class, 'getBySchedule']);
        Route::patch('/announcements/{announcement}/toggle-active', [AnnouncementController::class, 'toggleActive']);

        Route::get('/announcements/{announcementId}/replies', [AnnouncementReplyController::class, 'index']);
        Route::post('/announcements/{announcementId}/replies', [AnnouncementReplyController::class, 'store']);
        Route::get('/replies/{reply}', [AnnouncementReplyController::class, 'show']);
        Route::put('/replies/{reply}', [AnnouncementReplyController::class, 'update']);
        Route::delete('/replies/{reply}', [AnnouncementReplyController::class, 'destroy']);
        Route::patch('/replies/{reply}/toggle-active', [AnnouncementReplyController::class, 'toggleActive']);

        // Course Details routes
        Route::apiResource('course-details', CourseDetailController::class);
        Route::get('/courses/{courseId}/details', [CourseDetailController::class, 'getByCourse']);
        Route::post('/course-details/reorder', [CourseDetailController::class, 'reorder']);

        // Training Materials routes
        Route::apiResource('training-materials', TrainingMaterialController::class);
        Route::get('/courses/{courseId}/training-materials', [TrainingMaterialController::class, 'getByCourse']);
        Route::get('/training-materials/{trainingMaterial}/download', [TrainingMaterialController::class, 'download'])->middleware('secure.file');
        Route::get('/training-materials/{trainingMaterial}/view', [TrainingMaterialController::class, 'view'])->middleware('secure.file');

        // Course Content routes (for self-pace learning)
        Route::apiResource('course-content', CourseContentController::class);
        Route::get('/courses/{courseId}/content', [CourseContentController::class, 'getByCourse']);
        Route::get('/course-content/{courseContent}/download', [CourseContentController::class, 'download'])->middleware('secure.file');
        Route::get('/course-content/{courseContent}/view', [CourseContentController::class, 'view'])->middleware('secure.file');
    });
});

// Add a generic login route that redirects to trainee login
Route::post('/login', [TraineeAuthController::class, 'login'])->name('login');
