<?php

use App\Http\Controllers\ClientErrorController;
use App\Http\Controllers\Modules\SharepointController;
use App\Http\Controllers\Modules\TrashController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/trash', [ TrashController::class, 'update' ]);
Route::get('/sharepoint', [ SharepointController::class, 'update' ]);

Route::get('/do-jobs', [ SettingsController::class, 'doJobs' ]);

Route::post('/client-error', [ ClientErrorController::class, 'store' ]);