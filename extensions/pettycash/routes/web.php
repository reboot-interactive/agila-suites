<?php

use Extensions\pettycash\Controllers\PettyCashController;
use Extensions\pettycash\Controllers\PettyCashSettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'perm:view_petty_cash_transactions'])->group(function () {
    Route::get('/petty-cash', [PettyCashController::class, 'index'])->name('ext.pettycash.index');
    Route::post('/petty-cash', [PettyCashController::class, 'store'])->name('ext.pettycash.store');
    Route::put('/petty-cash/{id}', [PettyCashController::class, 'update'])->name('ext.pettycash.update');
    Route::delete('/petty-cash/{id}', [PettyCashController::class, 'destroy'])->name('ext.pettycash.destroy');
});

Route::middleware(['auth', 'perm:manage_petty_cash_settings'])->group(function () {
    Route::get('/petty-cash/settings', [PettyCashSettingsController::class, 'index'])->name('ext.pettycash.settings');
    Route::post('/petty-cash/settings/roles', [PettyCashSettingsController::class, 'updateRoles'])->name('ext.pettycash.settings.roles');
    Route::post('/petty-cash/settings/categories', [PettyCashSettingsController::class, 'storeCategory'])->name('ext.pettycash.settings.categories.store');
    Route::put('/petty-cash/settings/categories/{id}', [PettyCashSettingsController::class, 'updateCategory'])->name('ext.pettycash.settings.categories.update');
    Route::delete('/petty-cash/settings/categories/{id}', [PettyCashSettingsController::class, 'destroyCategory'])->name('ext.pettycash.settings.categories.destroy');
});
