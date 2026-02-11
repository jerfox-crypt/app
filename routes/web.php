<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\RFIDController;


// In routes/web.php or routes/api.php
Route::post('/api/scan-rfid', [RFIDController::class, 'handleScanPost']);
Route::get('/api/scan-rfid', [RFIDController::class, 'handleScanGet']);
Route::get('/api/transaction-logs', [RFIDController::class, 'getTransactionLogs']);
