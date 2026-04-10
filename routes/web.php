<?php

use Illuminate\Support\Facades\Route;
use Paymenter\Extensions\Gateways\Midtrans\Midtrans;

Route::post('/extensions/gateways/midtrans/webhook', [Midtrans::class, 'webhook'])->name('extensions.gateways.midtrans.webhook');
Route::post('/extensions/midtrans/webhook', [Midtrans::class, 'webhook'])->name('extensions.midtrans.webhook.legacy');
Route::get('/extensions/gateways/midtrans', [Midtrans::class, 'version'])->name('extensions.gateways.midtrans.version');