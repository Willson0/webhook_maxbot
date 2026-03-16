<?php

use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ViewerController;
use App\Http\Middleware\CheckTelegram;
use Illuminate\Support\Facades\Route;

Route::post("/payment/webhook_jgkgfhs7430jdfsd", [PaymentController::class, 'webhook']);
Route::post("/md/{hash}", [ViewerController::class, 'get'])->middleware(CheckTelegram::class);
