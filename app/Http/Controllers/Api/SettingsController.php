<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public, read-only application settings for the Angular client.
 */
class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'currency' => [
                    'code' => config('travelplan.currency.code'),
                    'symbol' => config('travelplan.currency.symbol'),
                ],
            ],
        ]);
    }
}
