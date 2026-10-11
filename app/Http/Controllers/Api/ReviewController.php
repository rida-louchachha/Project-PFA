<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Models\Avis;
use App\Models\Models\Utilisateur;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        // Ensure user is authenticated
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        // Validate incoming payload
        $validated = $request->validate([
            'id_agence' => ['required', 'integer', 'exists:agences,id_agence'],
            'note' => ['required', 'integer', 'min:1', 'max:5'],
            'commentaire' => ['nullable', 'string']
        ]);

        // Map authenticated user to utilisateurs row
        // Try to locate the utilisateur by email. If not found, try by id if naming matches.
        $utilisateur = null;
        if (isset($user->email)) {
            $utilisateur = Utilisateur::where('email', $user->email)->first();
        }
        if (!$utilisateur && isset($user->id_utilisateur)) {
            $utilisateur = Utilisateur::find($user->id_utilisateur);
        }

        if (!$utilisateur) {
            return response()->json([
                'success' => false,
                'message' => 'Associated utilisateur not found for the authenticated user.'
            ], 422);
        }

        try {
            $avis = Avis::create([
                'id_utilisateur' => $utilisateur->id_utilisateur,
                'id_agence' => $validated['id_agence'],
                'note' => $validated['note'],
                'commentaire' => $validated['commentaire'] ?? null,
                'date_avis' => now()->toDateString(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [ 'avis' => $avis ],
                'message' => 'Review submitted successfully'
            ], 201);
        } catch (\Throwable $e) {
            // Handle unique constraint (user-agency-date) gracefully
            if (str_contains(strtolower($e->getMessage()), 'unique') || str_contains(strtolower($e->getMessage()), 'constraint')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already submitted a review for this agency today.'
                ], 409);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit review',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}


