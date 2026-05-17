<?php

use Extensions\pedallion\Controllers\PedallionController;
use Extensions\pedallion\Controllers\PedallionCategoryController;
use Extensions\pedallion\Controllers\PedallionProductController;
use Extensions\pedallion\Controllers\PedallionProductGroupController;
use Extensions\pedallion\Controllers\PedallionOrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    // Pedallion
    Route::middleware(['perm:manage_pedallion'])->group(function () {
        Route::get('/pedallion', [PedallionController::class, 'index'])->name('ext.pedallion.index');
        Route::post('/pedallion/save', [PedallionController::class, 'save'])->name('ext.pedallion.save');
        Route::post('/pedallion/test', [PedallionController::class, 'testConnection'])->name('ext.pedallion.test');
        Route::post('/pedallion/toggle-logging', [PedallionController::class, 'toggleLogging'])->name('ext.pedallion.toggle_logging');
        Route::delete('/pedallion/api-logs', [PedallionController::class, 'clearApiLogs'])->name('ext.pedallion.clear_api_logs');
        Route::post('/pedallion/explorer/run', [PedallionController::class, 'explorerRun'])->name('ext.pedallion.explorer_run');
        Route::post('/pedallion/order-status-map', [PedallionController::class, 'saveOrderStatusMap'])->name('ext.pedallion.order_status_map');
        Route::post('/pedallion/fetch-order-statuses', [PedallionController::class, 'fetchOrderStatuses'])->name('ext.pedallion.fetch_order_statuses');
        Route::post('/pedallion/sync-days', [PedallionController::class, 'saveSyncDays'])->name('ext.pedallion.sync_days');

        // Categories & Manufacturers
        Route::get('/pedallion/categories', [PedallionCategoryController::class, 'index'])->name('ext.pedallion.categories.index');
        Route::post('/pedallion/categories/fetch', [PedallionCategoryController::class, 'fetch'])->name('ext.pedallion.categories.fetch');
        Route::post('/pedallion/manufacturers/fetch', [PedallionCategoryController::class, 'fetchManufacturers'])->name('ext.pedallion.manufacturers.fetch');

        // Product Groups
        Route::get('/pedallion/product-groups', [PedallionProductGroupController::class, 'index'])->name('ext.pedallion.product-groups.index');
        Route::get('/pedallion/product-groups/create', [PedallionProductGroupController::class, 'create'])->name('ext.pedallion.product-groups.create');
        Route::post('/pedallion/product-groups', [PedallionProductGroupController::class, 'store'])->name('ext.pedallion.product-groups.store');
        Route::get('/pedallion/product-groups/{id}/edit', [PedallionProductGroupController::class, 'edit'])->name('ext.pedallion.product-groups.edit');
        Route::put('/pedallion/product-groups/{id}', [PedallionProductGroupController::class, 'update'])->name('ext.pedallion.product-groups.update');
        Route::delete('/pedallion/product-groups/{id}', [PedallionProductGroupController::class, 'destroy'])->name('ext.pedallion.product-groups.destroy');
        Route::get('/pedallion/product-groups/{id}/products', [PedallionProductGroupController::class, 'products'])->name('ext.pedallion.product-groups.products');
        Route::post('/pedallion/product-groups/{id}/sync-products', [PedallionProductGroupController::class, 'syncProducts'])->name('ext.pedallion.product-groups.sync_products');

        // Products
        Route::get('/pedallion/products', [PedallionProductController::class, 'index'])->name('ext.pedallion.products.index');
        Route::post('/pedallion/products/link', [PedallionProductController::class, 'link'])->name('ext.pedallion.products.link');
        Route::delete('/pedallion/products/{id}/unlink', [PedallionProductController::class, 'unlink'])->name('ext.pedallion.products.unlink');
        Route::post('/pedallion/products/{productId}/push', [PedallionProductController::class, 'push'])->name('ext.pedallion.products.push');
        Route::post('/pedallion/products/{productId}/sync-qty', [PedallionProductController::class, 'syncQty'])->name('ext.pedallion.products.sync_qty');
        Route::post('/pedallion/products/{productId}/sync-price', [PedallionProductController::class, 'syncPrice'])->name('ext.pedallion.products.sync_price');
        Route::post('/pedallion/products/{productId}/delete', [PedallionProductController::class, 'deleteFromPedallion'])->name('ext.pedallion.products.delete');
        Route::post('/pedallion/products/{productId}/sync', [PedallionProductController::class, 'sync'])->name('ext.pedallion.products.sync');
        Route::post('/pedallion/products/bulk-sync', [PedallionProductController::class, 'bulkSync'])->name('ext.pedallion.products.bulk_sync');
        Route::post('/pedallion/products/bulk-unlink', [PedallionProductController::class, 'bulkUnlink'])->name('ext.pedallion.products.bulk_unlink');
        Route::post('/pedallion/products/bulk-push', [PedallionProductController::class, 'bulkPush'])->name('ext.pedallion.products.bulk_push');
        Route::post('/pedallion/products/bulk-sync-qty', [PedallionProductController::class, 'bulkSyncQty'])->name('ext.pedallion.products.bulk_sync_qty');
        Route::post('/pedallion/products/bulk-sync-price', [PedallionProductController::class, 'bulkSyncPrice'])->name('ext.pedallion.products.bulk_sync_price');
        Route::post('/pedallion/products/bulk-delete', [PedallionProductController::class, 'bulkDelete'])->name('ext.pedallion.products.bulk_delete');
        Route::post('/pedallion/products/push-qty', [PedallionProductController::class, 'pushQty'])->name('ext.pedallion.products.push_qty');
    });

    // Pedallion Orders
    Route::middleware(['perm:manage_pedallion_orders'])->group(function () {
        Route::get('/pedallion/orders', [PedallionOrderController::class, 'index'])->name('ext.pedallion.orders.index');
        Route::get('/pedallion/orders/{id}', [PedallionOrderController::class, 'show'])->name('ext.pedallion.orders.show');
    });

});
