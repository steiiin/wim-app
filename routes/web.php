<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Entries\EventController;
use App\Http\Controllers\Entries\InfoController;
use App\Http\Controllers\Entries\RecurringController;
use App\Http\Controllers\Entries\TaskController;
use App\Http\Controllers\Modules\TrashController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\SettingsController;
use App\Http\Middleware\IsAuthenticated;
use Illuminate\Support\Facades\Route;

/* Monitor-Endpoints */
Route::get('/monitor', [ MonitorController::class, 'index' ])->name('monitor.index');
Route::get('/monitor-poll', [ MonitorController::class, 'poll' ])->name('monitor.poll');

/* Admin-Endpoints */
Route::middleware(IsAuthenticated::class)->group(function() {

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/admin-heartbeat', [AdminController::class, 'heartbeat'])->name('admin.heartbeat');

    Route::get('/login', [LoginController::class, 'index'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate']);
    Route::get('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::post('/set-settings', [SettingsController::class, 'store']);
    Route::post('/set-module-trash', [TrashController::class, 'store']);

    Route::resource('/set-info', InfoController::class)->only(['store', 'destroy']);
    Route::resource('/set-event', EventController::class)->only(['store', 'destroy']);
    Route::resource('/set-task', TaskController::class)->only(['store', 'destroy']);
    Route::resource('/set-recurring', RecurringController::class)->only(['store', 'destroy']);

});
Route::redirect('/', '/admin');
