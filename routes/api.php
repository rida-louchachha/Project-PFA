<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LogementController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AgencyAssistantController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\DiscoveryController;

// Public application settings (currency display...)
Route::get('/settings', [SettingsController::class, 'show']);

// Discovery: popular destinations + AI smart search (natural language → validated filters)
Route::get('/destinations', [DiscoveryController::class, 'destinations']);
Route::post('/ai/smart-search', [DiscoveryController::class, 'smartSearch'])->middleware('throttle:smart-search');

// Public auth routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Public accommodation routes
Route::get('/logements', [LogementController::class, 'index']);
Route::get('/logements/filters/options', [LogementController::class, 'filters']);
Route::get('/logements/{id}', [LogementController::class, 'show']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Reservation routes
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::get('/reservations/{id}', [ReservationController::class, 'show']);
    Route::put('/reservations/{id}/cancel', [ReservationController::class, 'cancel']);

    // Payment routes
    Route::post('/payments', [PaymentController::class, 'store']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/reservation/{reservationId}', [PaymentController::class, 'show']);
    Route::post('/payments/{paymentId}/refund', [PaymentController::class, 'refund']);
    Route::post('/payments/verify', [PaymentController::class, 'verifyPayment']);

    // Reviews (avis)
    Route::post('/avis', [ReviewController::class, 'store']);

    // TravelPlan AI — customer assistant
    Route::get('/assistant/conversations', [AssistantController::class, 'index']);
    Route::post('/assistant/conversations', [AssistantController::class, 'open']);
    Route::get('/assistant/conversations/{conversation}', [AssistantController::class, 'show']);
    Route::post('/assistant/conversations/{conversation}/messages', [AssistantController::class, 'sendMessage'])
        ->middleware('throttle:assistant');

    // TravelPlan AI — agency dashboard & human handoff
    // Personalised, explainable recommendations
    Route::get('/recommendations', [DiscoveryController::class, 'recommendations']);

    // Agency AI insights (KPIs + AI summary)
    Route::get('/agency/assistant/insights', [DiscoveryController::class, 'agencyInsights'])->middleware('agency');

    // Agency: its own accommodations (search / filters / pagination like /logements)
    Route::get('/agency/logements', [LogementController::class, 'mine'])->middleware('agency');

    Route::middleware('agency')->prefix('agency/assistant')->group(function () {
        Route::get('/dashboard', [AgencyAssistantController::class, 'dashboard']);
        Route::get('/conversations/{conversation}', [AgencyAssistantController::class, 'show']);
        Route::post('/conversations/{conversation}/messages', [AgencyAssistantController::class, 'reply']);
        Route::post('/conversations/{conversation}/resolve', [AgencyAssistantController::class, 'resolve']);
    });
});
