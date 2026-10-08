<?php

use App\Http\Controllers\TicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => to_route('dashboard'))->name('home');

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{ticket:reference}', [TicketController::class, 'show'])->name('tickets.show');
    Route::patch('tickets/{ticket:reference}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('tickets/{ticket:reference}/messages', [TicketController::class, 'reply'])->name('tickets.reply');
});

require __DIR__.'/settings.php';
