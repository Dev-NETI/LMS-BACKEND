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
use App\Http\Controllers\TraineeProgressController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AdminAssessmentController;

// SPA Authentication (session-based) - for frontend
Route::prefix('trainee')->group(function () {
    Route::post('/login', [TraineeAuthController::class, 'login']);

    Route::middleware('auth:trainee-sanctum')->group(function () {
        Route::post('/logout', [TraineeAuthController::class, 'logout'])->name('trainee.logout');
        Route::get('/me', [TraineeAuthController::class, 'me'])->name('trainee.me');
        Route::get('/enrolled-courses', [CourseController::class, 'getEnrolledCourses'])->name('trainee.enrolled-courses');
        Route::get('/schedules/{sched_id}/announcements', [AnnouncementController::class, 'getBySchedule']);
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
        Route::get('/course-content/{courseContent}/articulate', [CourseContentController::class, 'getArticulateContent'])->middleware('secure.file');

        // Training Materials routes for trainees (read-only access)
        Route::get('/courses/{courseId}/training-materials', [TrainingMaterialController::class, 'getByCourse']);
        Route::get('/training-materials/{trainingMaterial}/download', [TrainingMaterialController::class, 'download'])->middleware('secure.file');
        Route::get('/training-materials/{trainingMaterial}/view', [TrainingMaterialController::class, 'view'])->middleware('secure.file');

        // Progress tracking routes for trainees
        Route::get('/courses/{courseId}/progress/{scheduleId}', [TraineeProgressController::class, 'getCourseProgress']);
        Route::post('/progress/start', [TraineeProgressController::class, 'markAsStarted']);
        Route::post('/progress/complete', [TraineeProgressController::class, 'markAsCompleted']);
        Route::post('/progress/update', [TraineeProgressController::class, 'updateProgress']);

        Route::get('/courses/{courseId}/details', [CourseDetailController::class, 'getByCourse']);

        // Notification routes for trainees
        Route::get('/notifications', [NotificationController::class, 'getTraineeNotifications']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
        Route::patch('/notifications/{notificationId}/read', [NotificationController::class, 'markAsRead']);
        Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{notificationId}', [NotificationController::class, 'deleteNotification']);

        // Assessment routes for trainees
        Route::get('/assessments/stats', [AssessmentController::class, 'getAssessmentStats']);
        Route::get('/schedules/{scheduleId}/assessments', [AssessmentController::class, 'getScheduleAssessments']);
        Route::get('/assessments', [AssessmentController::class, 'getTraineeAssessments']);
        Route::get('/assessments/{assessmentId}', [AssessmentController::class, 'getAssessment']);
        Route::post('/assessments/{assessmentId}/start', [AssessmentController::class, 'startAttempt']);
        Route::get('/assessments/{assessmentId}/questions', [AssessmentController::class, 'getAssessmentQuestions']);
        Route::post('/assessments/{assessmentId}/questions/{questionId}/answer', [AssessmentController::class, 'saveAnswer']);
        Route::post('/assessments/{assessmentId}/security-log', [AssessmentController::class, 'logSecurityEvent']);
        Route::post('/assessments/{assessmentId}/update-time', [AssessmentController::class, 'updateTimeRemaining']);
        Route::post('/assessments/{assessmentId}/submit', [AssessmentController::class, 'submitAttempt']);
        Route::get('/assessment-attempts/{attemptId}/result', [AssessmentController::class, 'getResult']);
        Route::get('/assessment-attempts/{attemptId}/status', [AssessmentController::class, 'getAttemptStatus']);
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
        // Specific routes must come before the resource routes
        Route::put('/course-content/update-order', [CourseContentController::class, 'updateOrder']);
        Route::get('/courses/{courseId}/content/next-order', [CourseContentController::class, 'getNextOrderForCourse']);
        Route::get('/courses/{courseId}/content', [CourseContentController::class, 'getByCourse']);

        // Question Bank routes
        Route::put('/questions/update-order', [QuestionController::class, 'updateOrder']);
        Route::put('/questions/bulk-update', [QuestionController::class, 'bulkUpdate']);
        Route::delete('/questions/bulk-delete', [QuestionController::class, 'bulkDelete']);
        Route::post('/questions/{question}/duplicate', [QuestionController::class, 'duplicate']);
        Route::get('/courses/{courseId}/questions', [QuestionController::class, 'getQuestionsByCourse']);
        Route::get('/courses/{courseId}/questions/next-order', [QuestionController::class, 'getNextOrderForCourse']);
        Route::apiResource('questions', QuestionController::class);

        // Assessment Management routes
        Route::get('/courses/{courseId}/assessments', [AdminAssessmentController::class, 'getAssessmentsByCourse']);
        Route::post('/courses/{courseId}/assessments', [AdminAssessmentController::class, 'store']);
        Route::get('/assessments/{id}/stats', [AdminAssessmentController::class, 'getAssessmentStats']);
        Route::put('/assessments/{id}/questions', [AdminAssessmentController::class, 'updateQuestions']);

        // Schedule assignment routes
        Route::get('/courses/{courseId}/schedules', [AdminAssessmentController::class, 'getCourseSchedules']);
        Route::post('/assessments/{assessmentId}/assign-schedules', [AdminAssessmentController::class, 'assignToSchedules']);
        Route::delete('/assessments/{assessmentId}/schedules/{scheduleId}', [AdminAssessmentController::class, 'removeFromSchedule']);
        Route::put('/assessments/{assessmentId}/schedules/{scheduleId}', [AdminAssessmentController::class, 'updateScheduleAssignment']);

        Route::apiResource('assessments', AdminAssessmentController::class)->except(['index', 'store']);
        Route::get('/course-content/{courseContent}/download', [CourseContentController::class, 'download'])->middleware('secure.file');
        Route::get('/course-content/{courseContent}/view', [CourseContentController::class, 'view'])->middleware('secure.file');
        Route::get('/course-content/{courseContent}/articulate', [CourseContentController::class, 'getArticulateContent'])->middleware('secure.file');
        Route::delete('/course-content/{courseContent}/cleanup', [CourseContentController::class, 'cleanupArticulateContent']);

        // Resource routes (these create wildcard patterns that can conflict with specific routes)
        Route::apiResource('course-content', CourseContentController::class);

        // Progress monitoring routes for admins
        Route::get('/progress/report', [TraineeProgressController::class, 'getProgressReport']);
        Route::get('/courses/{courseId}/progress/trainees', [TraineeProgressController::class, 'getTraineeProgressByCourse']);
        Route::get('/courses/{courseId}/progress/trainee/{traineeId}', [TraineeProgressController::class, 'getCourseProgress']);
        Route::get('/schedules/{scheduleId}/progress', [TraineeProgressController::class, 'getTraineeProgressBySchedule']);
        Route::get('/progress/{progressId}/activity-log', [TraineeProgressController::class, 'getActivityLog']);
        Route::post('/progress/update', [TraineeProgressController::class, 'updateProgress']);

        // Notification routes for admin
        Route::post('/notifications/announcement', [NotificationController::class, 'createAnnouncementNotification']);

        // Security monitoring routes for admin
        Route::get('/security/logs', [AssessmentController::class, 'getSecurityLogs']);
        Route::get('/assessments/{assessmentId}/security/logs', [AssessmentController::class, 'getAssessmentSecurityLogs']);
        Route::get('/trainees/{traineeId}/security/logs', [AssessmentController::class, 'getTraineeSecurityLogs']);

        // User management
        Route::get('/all-users', [UserController::class, 'getAllUsers']);
        Route::get('/all-instructors', [UserController::class, 'getAllInstructor']);
    });
});

// Add a generic login route that redirects to trainee login
Route::post('/login', [TraineeAuthController::class, 'login'])->name('login');
