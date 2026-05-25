<?php

use App\Http\Controllers\Api\EventExportController;
use App\Http\Controllers\Api\EventPlanController;
use App\Http\Controllers\Api\MapElementController;
use App\Http\Controllers\Api\PublicApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('events/{event}/plans', [EventPlanController::class, 'index']);
    Route::post('events/{event}/plans', [EventPlanController::class, 'store']);
    Route::patch('plans/{plan}', [EventPlanController::class, 'update']);
    Route::post('plans/{plan}/duplicate', [EventPlanController::class, 'duplicate']);
    Route::delete('plans/{plan}', [EventPlanController::class, 'destroy']);

    Route::get('plans/{plan}/elements', [MapElementController::class, 'indexForPlan']);
    Route::get('events/{event}/elements', [MapElementController::class, 'indexForEvent']);
    Route::post('plans/{plan}/elements', [MapElementController::class, 'storeForPlan']);
    Route::post('events/{event}/elements', [MapElementController::class, 'storeShared']);
    Route::patch('elements/{element}', [MapElementController::class, 'update']);
    Route::delete('elements/{element}', [MapElementController::class, 'destroy']);

    Route::get('plans/{plan}/export/csv', [EventExportController::class, 'csv']);
});

// Public routes — no auth required, token-controlled
Route::get('public/{token}/plans/{plan}/elements', [PublicApiController::class, 'planElements']);
