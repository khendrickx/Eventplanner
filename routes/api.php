<?php

use App\Http\Controllers\Api\EventPlanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('events/{event}/plans', [EventPlanController::class, 'index']);
    Route::post('events/{event}/plans', [EventPlanController::class, 'store']);
    Route::patch('plans/{plan}', [EventPlanController::class, 'update']);
    Route::post('plans/{plan}/duplicate', [EventPlanController::class, 'duplicate']);
    Route::delete('plans/{plan}', [EventPlanController::class, 'destroy']);
});
