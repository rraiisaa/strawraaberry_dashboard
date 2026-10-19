<?php

use App\Http\Controllers\{DashboardController, ConsignmentController, ProductController, CashFlowController, VendorController};
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::prefix('produk')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::post('/', [ProductController::class, 'store'])->name('store');
    Route::put('/{product}', [ProductController::class, 'update'])->name('update');
    Route::post('/{product}/reject', [ProductController::class, 'reject'])->name('reject');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
});

Route::prefix('konsinyasi')->name('consignments.')->group(function () {
    Route::get('/', [ConsignmentController::class, 'index'])->name('index');
    Route::post('/titip', [ConsignmentController::class, 'store'])->name('store');
    Route::patch('/{consignment}/terjual', [ConsignmentController::class, 'updateSold'])->name('sold');
    Route::post('/settle/{store}', [ConsignmentController::class, 'settle'])->name('settle');
    Route::get('/invoice/{store}', [ConsignmentController::class, 'invoice'])->name('invoice');
});

Route::prefix('vendor')->name('vendors.')->group(function () {
    Route::get('/', [VendorController::class, 'index'])->name('index');
    Route::post('/', [VendorController::class, 'store'])->name('store');
    Route::put('/{vendor}', [VendorController::class, 'update'])->name('update');
    Route::delete('/{vendor}', [VendorController::class, 'destroy'])->name('destroy');
});

Route::prefix('aliran-dana')->name('cashflow.')->group(function () {
    Route::get('/', [CashFlowController::class, 'index'])->name('index');
    Route::post('/', [CashFlowController::class, 'store'])->name('store');
    Route::delete('/{cashFlow}', [CashFlowController::class, 'destroy'])->name('destroy');
});
// Tambahkan middleware('auth') (Breeze) bila dashboard dipakai online.