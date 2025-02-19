<?php

use App\Http\Controllers\Modules\TrashController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/trash', [ TrashController::class, 'update' ]);
