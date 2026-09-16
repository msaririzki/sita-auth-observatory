<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExperimentController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('experiments', [ExperimentController::class, 'index'])->name('experiments.index');
    Route::post('experiments', [ExperimentController::class, 'store'])->name('experiments.store');
    Route::post('experiments/{experiment}/dispatch', [ExperimentController::class, 'dispatch'])
        ->name('experiments.dispatch');
});

require __DIR__.'/settings.php';
