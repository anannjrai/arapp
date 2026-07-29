<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentBatchController;
use App\Http\Controllers\Settings\BankFormatController;
use App\Http\Controllers\Settings\BankCountryController;
use App\Http\Controllers\Settings\CountryReasonCodeController;
use App\Http\Controllers\Settings\MasterFieldController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/payment-batches', [PaymentBatchController::class, 'index'])->name('payment-batches.index');
    Route::get('/payment-batches/create', [PaymentBatchController::class, 'create'])->middleware('role:admin,preparer')->name('payment-batches.create');
    Route::post('/payment-batches', [PaymentBatchController::class, 'store'])->middleware('role:admin,preparer')->name('payment-batches.store');
    Route::get('/payment-batches/template', [PaymentBatchController::class, 'template'])->middleware('role:admin,preparer')->name('payment-batches.template');
    Route::get('/payment-batches/{paymentBatch}/export', [PaymentBatchController::class, 'exportInstructions'])->middleware('role:admin,exporter')->name('payment-batches.export.instructions');
    Route::post('/payment-batches/{paymentBatch}/review', [PaymentBatchController::class, 'review'])->middleware('role:admin,reviewer')->name('payment-batches.review');
    Route::post('/payment-batches/{paymentBatch}/export', [PaymentBatchController::class, 'export'])->middleware('role:admin,exporter')->name('payment-batches.export');
    Route::get('/payment-batches/{paymentBatch}/transactions/{paymentTransaction}/form', [PaymentBatchController::class, 'paymentForm'])->name('payment-transactions.form');
    Route::get('/payment-batches/{paymentBatch}/transactions/{paymentTransaction}/edit', [PaymentBatchController::class, 'editTransaction'])->middleware('role:admin,preparer')->name('payment-transactions.edit');
    Route::patch('/payment-batches/{paymentBatch}/transactions/{paymentTransaction}', [PaymentBatchController::class, 'updateTransaction'])->middleware('role:admin,preparer')->name('payment-transactions.update');
    Route::delete('/payment-batches/{paymentBatch}/transactions/{paymentTransaction}', [PaymentBatchController::class, 'destroyTransaction'])->middleware('role:admin')->name('payment-transactions.destroy');
    Route::delete('/payment-batches/{paymentBatch}', [PaymentBatchController::class, 'destroy'])->middleware('role:admin')->name('payment-batches.destroy');
    Route::get('/payment-batches/{paymentBatch}', [PaymentBatchController::class, 'show'])->name('payment-batches.show');
    Route::resource('suppliers', SupplierController::class)->only(['index', 'show']);

    Route::middleware('role:admin')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::resource('bank-countries', BankCountryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('country-reason-codes', CountryReasonCodeController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('master-fields', MasterFieldController::class)->only(['index', 'store', 'update', 'destroy']);

        Route::get('/bank-format', [BankFormatController::class, 'index'])->name('bank-format.index');
        Route::patch('/bank-format/{bankFileFormat}', [BankFormatController::class, 'updateFormat'])->name('bank-format.update');
        Route::post('/bank-format/{bankFileFormat}/columns', [BankFormatController::class, 'storeColumn'])->name('bank-format.columns.store');
        Route::patch('/bank-format/columns/{bankFileColumn}', [BankFormatController::class, 'updateColumn'])->name('bank-format.columns.update');
        Route::delete('/bank-format/columns/{bankFileColumn}', [BankFormatController::class, 'destroyColumn'])->name('bank-format.columns.destroy');

        Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});
