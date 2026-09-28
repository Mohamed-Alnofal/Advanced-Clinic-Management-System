<?php

use Illuminate\Support\Facades\Route;
use Modules\AI\Http\Controllers\MedicalAiChatController;
use Modules\AI\Http\Controllers\UnifiedAiChatController;

Route::middleware('auth:api')->prefix('v1/ai')->group(function () {
    // Canonical unified chatbot (emergency + medical Q&A).
    Route::post('/chat', [UnifiedAiChatController::class, 'chat']);

    // Patient attaches their doctor-written chart to the assistant (opt-in).
    Route::get('/my-chart', [UnifiedAiChatController::class, 'myChart']);

    // Doctor clinical copilot — visibility-scoped chart briefing.
    Route::post('/analyze-chart', [UnifiedAiChatController::class, 'analyzeChart']);

    // Legacy alias — same handler for older clients/docs.
    Route::post('/medical-chat', [MedicalAiChatController::class, 'chat']);
});

Route::get('/v1/ai/health', [UnifiedAiChatController::class, 'aiHealth']);
