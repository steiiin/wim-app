<?php

use App\Http\Controllers\ClientErrorController;
use Illuminate\Support\Facades\Route;

Route::post('/client-error', [ ClientErrorController::class, 'store' ]);
