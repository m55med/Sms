<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Webhook - receives SMS from iOS Shortcut (POST with message in body)
Route::post('/webhook/sms', [WebhookController::class, 'receiveSms']);

// Webhook - receives messages forwarded to Telegram Bot
Route::post('/webhook/telegram', [WebhookController::class, 'receiveTelegram']);

// Dashboard APIs
Route::get('/transactions', [DashboardController::class, 'transactions']);
Route::get('/dashboard', [DashboardController::class, 'summary']);

// Payment verification
Route::post('/verify-payment', [PaymentController::class, 'verify']);
