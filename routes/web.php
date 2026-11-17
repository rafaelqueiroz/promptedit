<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\Webhooks\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth'])->group(function (): void {
    // The full, paginated history. Generating happens on the landing page.
    Route::get('dashboard', [MediaController::class, 'index'])->name('dashboard');
    Route::post('generate', [MediaController::class, 'store'])->name('media.store');
});

// Signature-verified rather than session-authenticated.
Route::post('webhooks/pixelforge', PixelforgeWebhookController::class)
    ->name('webhooks.pixelforge');

require __DIR__.'/settings.php';
