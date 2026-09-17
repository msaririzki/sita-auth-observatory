<?php

use App\Http\Controllers\Api\EvidenceSubmissionController;
use Illuminate\Support\Facades\Route;

Route::post('v1/evidence', EvidenceSubmissionController::class)
    ->middleware('throttle:30,1')
    ->name('api.evidence.store');
