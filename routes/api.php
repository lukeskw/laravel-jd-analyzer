<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CandidateFitController;
use App\Http\Controllers\AuthenticationController;

Route::prefix('v1')
    ->as('api.v1.')
    ->middleware('throttle:60,1') // 60 requests per minute
    ->group(function (): void {
        Route::prefix('auth')
            ->as('auth.')
            ->group(function (): void {
                Route::post('login', [AuthenticationController::class, 'login'])
                    ->name('login');
                Route::post('refresh', [AuthenticationController::class, 'refresh'])
                    ->name('refresh');
            });

        Route::middleware('auth:api')->group(function (): void {
            Route::get('auth/me', [AuthenticationController::class, 'me'])
                ->name('auth.me');
            Route::post('auth/logout', [AuthenticationController::class, 'logout'])
                ->name('auth.logout');

            // Alias for current user info
            Route::get('me', [AuthenticationController::class, 'me'])
                ->name('me');

        });

        // Public JD Analyzer endpoints (keep behavior as-is)
        // 1) Upload a JD PDF (one per role)
        Route::post('/jds', [CandidateFitController::class, 'storeJobDescription'])->name('jds.store');

        // 2) Upload multiple resumes for a JD; 3) Process and score
        Route::post('/jds/{jd}/resumes', [CandidateFitController::class, 'storeResumes'])->name('jds.resumes.store');

        // 4) View all candidates for a JD, sorted by fit, and drill into detail via ?candidateId=
        Route::get('/jds/{jd}/candidates', [CandidateFitController::class, 'listCandidates'])->name('jds.candidates.index');
    });
