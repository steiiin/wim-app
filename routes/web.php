<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Entries\EventController;
use App\Http\Controllers\Entries\InfoController;
use App\Http\Controllers\Entries\RecurringController;
use App\Http\Controllers\Entries\TaskController;
use App\Http\Controllers\Modules\SharepointController;
use App\Http\Controllers\Modules\TrashController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\SettingsController;
use App\Http\Middleware\IsAuthenticated;
use Illuminate\Support\Facades\Route;

/* Monitor-Endpoints */
Route::controller(MonitorController::class)->group(function () {
    Route::get('/monitor', 'index')->name('monitor');
    Route::get('/monitor-poll', 'poll');
});

/* Admin-Endpoints */
Route::middleware(IsAuthenticated::class)->group(function() {

    Route::controller(AdminController::class)->group(function () {
        Route::get('/admin', 'index');
        Route::get('/admin-heartbeat', 'heartbeat');
    });

    Route::controller(LoginController::class)->group(function () {
        Route::get('/login', 'index')->name('login');
        Route::post('/login', 'authenticate');
        Route::get('/logout', 'logout');
    });

    Route::post('/set-settings', [SettingsController::class, 'store']);
    Route::post('/set-module-trash', [TrashController::class, 'store']);
    Route::post('/set-module-sharepoint', [SharepointController::class, 'store']);

    Route::resource('/set-info', InfoController::class)->only(['store', 'destroy']);
    Route::resource('/set-event', EventController::class)->only(['store', 'destroy']);
    Route::resource('/set-task', TaskController::class)->only(['store', 'destroy']);
    Route::resource('/set-recurring', RecurringController::class)->only(['store', 'destroy']);

});
Route::redirect('/', '/admin');
