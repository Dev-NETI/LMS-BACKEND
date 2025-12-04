<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ArticulateViewerController;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// Public route for serving Articulate content with signed tokens
Route::get('/articulate-viewer/{token}/{path?}', [ArticulateViewerController::class, 'show'])
    ->where('path', '.*')
    ->name('articulate.viewer');
