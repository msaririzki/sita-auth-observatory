<?php

use App\Http\Controllers\Api\EvidenceSubmissionController;
use App\Http\Controllers\Api\TrialProgressController;
use Illuminate\Support\Facades\Route;

Route::post('v1/evidence', EvidenceSubmissionController::class)
    ->middleware('throttle:30,1')
    ->name('api.evidence.store');

Route::post('v1/progress', TrialProgressController::class)
    ->middleware('throttle:120,1')
    ->name('api.progress.store');
