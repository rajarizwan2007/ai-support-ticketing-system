<?php

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => to_route('dashboard'))->name('home');

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('tickets/{ticket:reference}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('tickets/{ticket:reference}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('tickets/{ticket:reference}/messages', [TicketController::class, 'reply'])->name('tickets.reply');
    Route::get('tickets/{ticket:reference}/attachments/{attachment}', [TicketController::class, 'attachment'])->name('tickets.attachments.show')->scopeBindings();
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
});

require __DIR__.'/settings.php';
