<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Models\Reservation;
use App\Models\Models\Logement;
use App\Models\User;
use App\Services\Booking\BookingPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ReservationController extends Controller
{
    /**
     * Create a new reservation
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_logement' => 'required|exists:logements,id_logement',
                'date_debut' => 'required|date|after_or_equal:today',
                'date_fin' => 'required|date|after:date_debut',
                'nb_personnes' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Get accommodation details
            $logement = Logement::find($request->id_logement);
            
            // Check if accommodation is available
            if ($logement->statut !== 'actif') {
                return response()->json([
                    'success' => false,
                    'message' => 'Accommodation is not available for booking'
                ], 400);
            }

            // Check capacity
            if ($request->nb_personnes > $logement->capacite) {
                return response()->json([
                    'success' => false,
                    'message' => 'Number of guests exceeds accommodation capacity'
                ], 400);
            }

            // Check for overlapping reservations
            $overlapping = Reservation::where('id_logement', $request->id_logement)
                ->where('statut', '!=', 'annule')
                ->where(function($query) use ($request) {
                    $query->whereBetween('date_debut', [$request->date_debut, $request->date_fin])
                          ->orWhereBetween('date_fin', [$request->date_debut, $request->date_fin])
                          ->orWhere(function($q) use ($request) {
                              $q->where('date_debut', '<=', $request->date_debut)
                                ->where('date_fin', '>=', $request->date_fin);
                          });
                })
                ->exists();

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'Accommodation is not available for the selected dates'
                ], 400);
            }

            // Calculate total amount
            $dateDebut = Carbon::parse($request->date_debut);
            $dateFin = Carbon::parse($request->date_fin);
            $days = $dateDebut->diffInDays($dateFin);
            
            $basePrice = (float) $logement->tarif_amount;
            $guestSurcharge = $request->nb_personnes > 2 ? ($request->nb_personnes - 2) * 20 : 0;
            $totalAmount = ($basePrice * $days) + $guestSurcharge;

            // Create reservation
            $reservation = Reservation::create([
                'id_utilisateur' => $user->id_utilisateur,
                'id_logement' => $request->id_logement,
                'date_debut' => $request->date_debut,
                'date_fin' => $request->date_fin,
                'nb_personnes' => $request->nb_personnes,
                'statut' => 'en_attente',
                'montant_total' => $totalAmount,
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'reservation' => [
                        'id_reservation' => $reservation->id_reservation,
                        'id_utilisateur' => $reservation->id_utilisateur,
                        'id_logement' => $reservation->id_logement,
                        'date_debut' => $reservation->date_debut,
                        'date_fin' => $reservation->date_fin,
                        'nb_personnes' => $reservation->nb_personnes,
                        'statut' => $reservation->statut,
                        'montant_total' => $reservation->montant_total,
                        'created_at' => $reservation->created_at,
                    ]
                ],
                'message' => 'Reservation created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's reservations
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $reservations = Reservation::with(['logement.agence', 'paiement', 'confirmation'])
                ->where('id_utilisateur', $user->id_utilisateur)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($reservations);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching reservations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific reservation
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $reservation = Reservation::with(['logement.agence', 'paiement'])
                ->where('id_reservation', $id)
                ->where('id_utilisateur', $user->id_utilisateur)
                ->first();

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $reservation
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching the reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a reservation
     */
    public function cancel($id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $reservation = Reservation::where('id_reservation', $id)
                ->where('id_utilisateur', $user->id_utilisateur)
                ->first();

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation not found'
                ], 404);
            }

            if ($reservation->statut === 'annule') {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation is already cancelled'
                ], 400);
            }

            // Check if reservation can be cancelled (e.g., not too close to check-in date)
            $checkIn = Carbon::parse($reservation->date_debut);
            $now = Carbon::now();

            // Rule shared with TravelPlan AI (Carbon 3 diffs are signed: the previous
            // `$checkIn->diffInDays($now) < 1` was negative for every future check-in)
            if (!app(BookingPolicy::class)->isCancellationWindowOpen($checkIn, $now)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot cancel reservation less than 24 hours before check-in'
                ], 400);
            }

            $reservation->update(['statut' => 'annule']);

            return response()->json([
                'success' => true,
                'message' => 'Reservation cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while cancelling the reservation',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
