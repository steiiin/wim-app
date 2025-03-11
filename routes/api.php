<?php

use App\Http\Controllers\Modules\SharepointController;
use App\Http\Controllers\Modules\TrashController;
use Illuminate\Support\Facades\Route;

Route::get('/trash', [ TrashController::class, 'update' ]);
Route::get('/sharepoint', [ SharepointController::class, 'update' ]);
