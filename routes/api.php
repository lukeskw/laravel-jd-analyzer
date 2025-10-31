<?php

use App\Http\Controllers\AuthenticationController;
use App\Http\Controllers\CandidateFitController;
use Illuminate\Support\Facades\Route;

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

            Route::get('me', [AuthenticationController::class, 'me'])
                ->name('me');

            Route::get('/jds', [CandidateFitController::class, 'listJobDescriptions'])->name('jds.index');

            Route::post('/jds', [CandidateFitController::class, 'storeJobDescription'])->name('jds.store');

            Route::post('/jds/{jd}/resumes', [CandidateFitController::class, 'storeResumes'])->name('jds.resumes.store');

            Route::get('/jds/{jd}/candidates', [CandidateFitController::class, 'listCandidates'])->name('jds.candidates.index');
        });
    });
