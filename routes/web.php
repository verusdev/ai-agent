<?php

use App\Http\Controllers\AgentController;
use App\Http\Controllers\ArtifactController;
use App\Http\Controllers\ProviderController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/agents');

/* ---------- Задачи агента ---------- */
Route::get('/agents', [AgentController::class, 'index'])->name('agents.index');
Route::post('/agents', [AgentController::class, 'store'])->name('agents.store');
Route::get('/agents/{agent}', [AgentController::class, 'show'])->name('agents.show');
Route::delete('/agents/{agent}', [AgentController::class, 'destroy'])->name('agents.destroy');
Route::post('/agents/{agent}/messages', [AgentController::class, 'addMessage'])->name('agents.messages.store');
Route::post('/agents/{agent}/tick', [AgentController::class, 'tick'])->name('agents.tick');
Route::get('/agents/{agent}/events', [AgentController::class, 'events'])->name('agents.events');

/* ---------- Модели ---------- */
Route::get('/providers', [ProviderController::class, 'index'])->name('providers.index');
Route::post('/providers', [ProviderController::class, 'store'])->name('providers.store');
Route::post('/providers/preset', [ProviderController::class, 'addPreset'])->name('providers.preset');
Route::put('/providers/{provider}', [ProviderController::class, 'update'])->name('providers.update');
Route::delete('/providers/{provider}', [ProviderController::class, 'destroy'])->name('providers.destroy');
Route::post('/providers/{provider}/probe', [ProviderController::class, 'probe'])->name('providers.probe');

/* ---------- Артефакты ---------- */
Route::get('/artifacts/{artifact}', [ArtifactController::class, 'show'])->name('artifacts.show');
Route::get('/artifacts/{artifact}/download', [ArtifactController::class, 'show'])
    ->defaults('download', true)
    ->name('artifacts.download');