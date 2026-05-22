<?php

use App\Http\Controllers\EventCollaboratorController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

Route::get('/', [EventController::class, 'index'])->middleware('auth')->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::resource('events', EventController::class)->except('index');
    Route::post('events/{event}/duplicate', [EventController::class, 'duplicate'])->name('events.duplicate');
    Route::post('events/{event}/collaborators', [EventCollaboratorController::class, 'store'])
        ->name('events.collaborators.store');
    Route::patch('events/{event}/collaborators/{user}', [EventCollaboratorController::class, 'update'])
        ->name('events.collaborators.update');
    Route::delete('events/{event}/collaborators/{user}', [EventCollaboratorController::class, 'destroy'])
        ->name('events.collaborators.destroy');
});

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');

require __DIR__.'/auth.php';
