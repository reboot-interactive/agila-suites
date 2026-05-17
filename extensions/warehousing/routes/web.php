<?php

use Extensions\warehousing\Controllers\WarehouseController;
use Extensions\warehousing\Controllers\WarehouseInventoryController;
use Extensions\warehousing\Controllers\WarehouseTransferController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    // Locations — requires manage_warehouse_locations or parent manage_warehousing
    Route::middleware(['perm:manage_warehouse_locations|manage_warehousing'])->group(function () {
        Route::get('/warehousing/locations', [WarehouseController::class, 'index'])->name('ext.warehousing.locations.index');
        Route::get('/warehousing/locations/create', [WarehouseController::class, 'create'])->name('ext.warehousing.locations.create');
        Route::post('/warehousing/locations', [WarehouseController::class, 'store'])->name('ext.warehousing.locations.store');
        Route::get('/warehousing/locations/{id}/edit', [WarehouseController::class, 'edit'])->name('ext.warehousing.locations.edit');
        Route::put('/warehousing/locations/{id}', [WarehouseController::class, 'update'])->name('ext.warehousing.locations.update');
        Route::delete('/warehousing/locations/{id}', [WarehouseController::class, 'destroy'])->name('ext.warehousing.locations.destroy');
    });

    // Transfers — requires manage_warehouse_transfers or parent manage_warehousing
    Route::middleware(['perm:manage_warehouse_transfers|manage_warehousing'])->group(function () {
        Route::get('/warehousing/transfers', [WarehouseTransferController::class, 'index'])->name('ext.warehousing.transfers.index');
        Route::get('/warehousing/transfers/create', [WarehouseTransferController::class, 'create'])->name('ext.warehousing.transfers.create');
        Route::post('/warehousing/transfers', [WarehouseTransferController::class, 'store'])->name('ext.warehousing.transfers.store');
        Route::get('/warehousing/transfers/{id}', [WarehouseTransferController::class, 'show'])->name('ext.warehousing.transfers.show');
        Route::get('/warehousing/transfers/{id}/edit', [WarehouseTransferController::class, 'edit'])->name('ext.warehousing.transfers.edit');
        Route::put('/warehousing/transfers/{id}', [WarehouseTransferController::class, 'update'])->name('ext.warehousing.transfers.update');
        Route::post('/warehousing/transfers/{id}/in-progress', [WarehouseTransferController::class, 'markInProgress'])->name('ext.warehousing.transfers.in_progress');
        Route::post('/warehousing/transfers/{id}/complete', [WarehouseTransferController::class, 'complete'])->name('ext.warehousing.transfers.complete');
        Route::post('/warehousing/transfers/{id}/cancel', [WarehouseTransferController::class, 'cancel'])->name('ext.warehousing.transfers.cancel');
        Route::post('/warehousing/transfers/{id}/void', [WarehouseTransferController::class, 'void'])->name('ext.warehousing.transfers.void');
        Route::delete('/warehousing/transfers/{id}', [WarehouseTransferController::class, 'destroy'])->name('ext.warehousing.transfers.destroy');
        Route::get('/warehousing/transfers/{id}/pdf', [WarehouseTransferController::class, 'pdf'])->name('ext.warehousing.transfers.pdf');

        // API (used by transfer form)
        Route::get('/api/warehousing/products', [WarehouseTransferController::class, 'searchProducts'])->name('ext.warehousing.api.products');
    });

    // Stock by Location + Adjustments — requires manage_warehouse_inventory or parent manage_warehousing
    Route::middleware(['perm:manage_warehouse_inventory|manage_warehousing'])->group(function () {
        Route::get('/warehousing/inventory', [WarehouseInventoryController::class, 'index'])->name('ext.warehousing.inventory.index');
        Route::get('/warehousing/adjust', [WarehouseInventoryController::class, 'adjustForm'])->name('ext.warehousing.inventory.adjust');
        Route::post('/warehousing/adjust', [WarehouseInventoryController::class, 'adjustStore'])->name('ext.warehousing.inventory.adjust.store');
    });
});
