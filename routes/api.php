<?php

use App\Http\Controllers\Modules\MaltesercloudController;
use App\Http\Controllers\Modules\TrashController;
use Illuminate\Support\Facades\Route;

Route::get('/trash', [ TrashController::class, 'update' ]);
Route::get('/maltesercloud', [ MaltesercloudController::class, 'update' ]);
