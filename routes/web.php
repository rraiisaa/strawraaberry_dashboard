<?php

use App\Http\Controllers\{DashboardController, ProductController, CashFlowController, VendorController, PartnerController, TermController, StockController, RestockController, SalesReportController, PaymentController, FileController};
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::prefix('produk')->name('products.')->group(function () {
    Route::get('/', [ProductController::class, 'index'])->name('index');
    Route::post('/', [ProductController::class, 'store'])->name('store');
    Route::put('/{product}', [ProductController::class, 'update'])->name('update');
    Route::post('/{product}/reject', [ProductController::class, 'reject'])->name('reject');
    Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
});

Route::prefix('titip-jual')->group(function () {
    Route::get('/', [PartnerController::class, 'overview'])->name('partners.overview');

    Route::prefix('mitra')->name('partners.')->group(function () {
        Route::get('/', [PartnerController::class, 'index'])->name('index');
        Route::get('/baru', [PartnerController::class, 'create'])->name('create');
        Route::post('/', [PartnerController::class, 'store'])->name('store');
        Route::get('/{store}', [PartnerController::class, 'show'])->withTrashed()->name('show');
        Route::get('/{store}/edit', [PartnerController::class, 'edit'])->name('edit');
        Route::put('/{store}', [PartnerController::class, 'update'])->name('update');
        Route::post('/{store}/arsip', [PartnerController::class, 'archive'])->name('archive');
        Route::post('/{store}/aktifkan', [PartnerController::class, 'restore'])->withTrashed()->name('restore');
        Route::post('/{store}/skema', [TermController::class, 'store'])->name('terms.store');
        Route::post('/{store}/stok', [StockController::class, 'move'])->name('stock.move');
    });

    Route::prefix('restock')->name('restocks.')->group(function () {
        Route::get('/', [RestockController::class, 'index'])->name('index');
        Route::get('/baru', [RestockController::class, 'create'])->name('create');
        Route::post('/', [RestockController::class, 'store'])->name('store');
        Route::get('/{restock}', [RestockController::class, 'show'])->name('show');
        Route::get('/{restock}/edit', [RestockController::class, 'edit'])->name('edit');
        Route::put('/{restock}', [RestockController::class, 'update'])->name('update');
        Route::post('/{restock}/kirim', [RestockController::class, 'send'])->name('send');
        Route::post('/{restock}/konfirmasi', [RestockController::class, 'confirm'])->name('confirm');
        Route::post('/{restock}/batal', [RestockController::class, 'cancel'])->name('cancel');
        Route::get('/{restock}/cetak', [RestockController::class, 'print'])->name('print');
    });

    Route::prefix('laporan')->name('reports.')->group(function () {
        Route::get('/', [SalesReportController::class, 'index'])->name('index');
        Route::get('/baru', [SalesReportController::class, 'create'])->name('create');
        Route::post('/', [SalesReportController::class, 'store'])->name('store');
        Route::get('/{report}', [SalesReportController::class, 'show'])->name('show');
        Route::get('/{report}/edit', [SalesReportController::class, 'edit'])->name('edit');
        Route::put('/{report}', [SalesReportController::class, 'update'])->name('update');
        Route::post('/{report}/ajukan', [SalesReportController::class, 'submit'])->name('submit');
        Route::post('/{report}/verifikasi', [SalesReportController::class, 'verify'])->name('verify');
        Route::post('/{report}/tolak', [SalesReportController::class, 'reject'])->name('reject');
        Route::post('/{report}/revisi', [SalesReportController::class, 'revise'])->name('revise');
        Route::post('/{report}/batal', [SalesReportController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('setoran')->name('payments.')->group(function () {
        Route::get('/', [PaymentController::class, 'index'])->name('index');
        Route::get('/baru', [PaymentController::class, 'create'])->name('create');
        Route::post('/', [PaymentController::class, 'store'])->name('store');
        Route::get('/{payment}', [PaymentController::class, 'show'])->name('show');
        Route::post('/{payment}/verifikasi', [PaymentController::class, 'verify'])->name('verify');
        Route::post('/{payment}/tolak', [PaymentController::class, 'reject'])->name('reject');
        Route::post('/{payment}/reversal', [PaymentController::class, 'reverse'])->name('reverse');
        Route::post('/{payment}/alokasi', [PaymentController::class, 'allocate'])->name('allocate');
        Route::post('/tagihan/{obligation}/ditagih', [PaymentController::class, 'bill'])->name('bill');
    });

    Route::get('/berkas/{type}/{id}', [FileController::class, 'proof'])->name('files.proof');
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