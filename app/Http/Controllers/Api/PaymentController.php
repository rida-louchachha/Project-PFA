<?php

namespace App\Http\Controllers\Api;

use App\Events\ReservationPaymentSettled;
use App\Http\Controllers\Controller;
use App\Models\Models\Paiement;
use App\Services\Booking\BookingPolicy;
use App\Models\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PaymentController extends Controller
{
    /**
     * Process a payment for a reservation
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'id_reservation' => 'required|exists:reservations,id_reservation',
                'mode' => 'required|in:especes,terminal,en_ligne',
                'montant' => 'required|numeric|min:0',
                'reference' => 'nullable|string|max:120',
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

            // Get reservation details
            $reservation = Reservation::where('id_reservation', $request->id_reservation)
                ->where('id_utilisateur', $user->id_utilisateur)
                ->first();

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation not found or unauthorized'
                ], 404);
            }

            // Check if payment already exists
            $existingPayment = Paiement::where('id_reservation', $request->id_reservation)->first();
            if ($existingPayment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment already exists for this reservation'
                ], 400);
            }

            // Validate payment amount matches reservation total
            if (abs($request->montant - $reservation->montant_total) > 0.01) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount does not match reservation total'
                ], 400);
            }

            // Determine payment status based on method
            $paymentStatus = 'regle'; // Default to paid
            
            if ($request->mode === 'especes' || $request->mode === 'terminal') {
                // For cash or terminal, assume immediate payment
                $paymentStatus = 'regle';
            } else {
                // For online payment, we could integrate with a payment gateway
                // For now, we'll simulate successful payment
                $paymentStatus = 'regle';
            }

            // Generate reference if not provided
            $reference = $request->reference ?: $this->generatePaymentReference();

            // Create payment record
            $paiement = Paiement::create([
                'id_reservation' => $request->id_reservation,
                'mode' => $request->mode,
                'montant' => $request->montant,
                'date_paiement' => Carbon::now(),
                'statut' => $paymentStatus,
                'reference' => $reference,
            ]);

            // Update reservation status to confirmed if payment is successful
            if ($paymentStatus === 'regle') {
                $reservation->update(['statut' => 'confirme']);

                // TravelPlan AI: queue the booking confirmation message
                ReservationPaymentSettled::dispatch($reservation->id_reservation);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => [
                        'id_paiement' => $paiement->id_paiement,
                        'id_reservation' => $paiement->id_reservation,
                        'mode' => $paiement->mode,
                        'montant' => $paiement->montant,
                        'date_paiement' => $paiement->date_paiement,
                        'statut' => $paiement->statut,
                        'reference' => $paiement->reference,
                        'created_at' => $paiement->created_at,
                    ]
                ],
                'message' => 'Payment processed successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment details for a reservation
     */
    public function show($reservationId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Verify the reservation belongs to the user
            $reservation = Reservation::where('id_reservation', $reservationId)
                ->where('id_utilisateur', $user->id_utilisateur)
                ->first();

            if (!$reservation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation not found or unauthorized'
                ], 404);
            }

            $paiement = Paiement::where('id_reservation', $reservationId)->first();

            if (!$paiement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found for this reservation'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payment' => [
                        'id_paiement' => $paiement->id_paiement,
                        'id_reservation' => $paiement->id_reservation,
                        'mode' => $paiement->mode,
                        'montant' => $paiement->montant,
                        'date_paiement' => $paiement->date_paiement,
                        'statut' => $paiement->statut,
                        'reference' => $paiement->reference,
                        'created_at' => $paiement->created_at,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching payment details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's payment history
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

            $payments = Paiement::with(['reservation.logement.agence'])
                ->whereHas('reservation', function($query) use ($user) {
                    $query->where('id_utilisateur', $user->id_utilisateur);
                })
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $payments
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching payment history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refund a payment (for cancelled reservations)
     */
    public function refund($paymentId): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $paiement = Paiement::with('reservation')
                ->where('id_paiement', $paymentId)
                ->whereHas('reservation', function($query) use ($user) {
                    $query->where('id_utilisateur', $user->id_utilisateur);
                })
                ->first();

            if (!$paiement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found or unauthorized'
                ], 404);
            }

            // Check if reservation is cancelled
            if ($paiement->reservation->statut !== 'annule') {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund can only be processed for cancelled reservations'
                ], 400);
            }

            // Check if payment is eligible for refund
            if ($paiement->statut !== 'regle') {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment is not in a refundable state'
                ], 400);
            }

            // Calculate refund amount (could include fees, cancellation policies, etc.)
            $refundAmount = $this->calculateRefundAmount($paiement);

            // Process refund (this would integrate with payment gateway)
            // For now, we'll just update the status
            $paiement->update([
                'statut' => 'rembourse',
                // You might want to add refund_amount, refund_date fields
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'refund_amount' => $refundAmount,
                    'original_amount' => $paiement->montant,
                    'reference' => $paiement->reference
                ],
                'message' => 'Refund processed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing refund',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a unique payment reference
     */
    private function generatePaymentReference(): string
    {
        return 'PAY-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
    }

    /**
     * Calculate refund amount based on cancellation policy
     */
    private function calculateRefundAmount(Paiement $paiement): float
    {
        // Refund policy (shared with TravelPlan AI, see BookingPolicy::refundRate):
        // - More than 7 days: 100% refund
        // - 3-7 days: 50% refund
        // - Less than 3 days: 25% refund
        $originalAmount = (float) $paiement->montant;
        $checkInDate = Carbon::parse($paiement->reservation->date_debut);

        return $originalAmount * app(BookingPolicy::class)->refundRate($checkInDate, Carbon::now());
    }

    /**
     * Verify payment status (for webhook integrations)
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'reference' => 'required|string',
                'status' => 'required|in:regle,echoue',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $paiement = Paiement::where('reference', $request->reference)->first();

            if (!$paiement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Update payment status
            $paiement->update([
                'statut' => $request->status,
                'date_paiement' => Carbon::now()
            ]);

            // Update reservation status based on payment status
            if ($request->status === 'regle') {
                $paiement->reservation->update(['statut' => 'confirme']);

                // TravelPlan AI: queue the booking confirmation message (idempotent)
                ReservationPaymentSettled::dispatch($paiement->id_reservation);
            } else {
                // Payment failed, you might want to handle this case
                // For example, cancel the reservation or mark it as pending
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment status updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while verifying payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
