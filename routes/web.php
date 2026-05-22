<?php

use App\Http\Controllers\EventController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventController::class, 'index'])->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('events', EventController::class)->except('index');
    Route::post('events/{event}/duplicate', [EventController::class, 'duplicate'])->name('events.duplicate');
});

require __DIR__.'/auth.php';
