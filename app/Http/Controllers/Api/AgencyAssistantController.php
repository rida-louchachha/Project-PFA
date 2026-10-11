<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Models\AiConversation;
use App\Models\Models\AiMessage;
use App\Models\Models\Reservation;
use App\Models\Models\ReservationConfirmation;
use App\Services\Ai\Support\ConversationPresenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * Agency AI dashboard + human handoff (auth:sanctum + agency middleware).
 * Every query is scoped to the logements of the agency owned by the logged-in account.
 */
class AgencyAssistantController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $agencyId = $request->user()->agence->id_agence;
        $ownedByAgency = fn (Builder $q) => $q->where('id_agence', $agencyId);

        $upcoming = Reservation::with(['utilisateur', 'logement', 'paiement', 'confirmation', 'conversations'])
            ->whereHas('logement', $ownedByAgency)
            ->where('statut', '!=', 'annule')
            ->where('date_debut', '>=', Carbon::now()->startOfDay())
            ->orderBy('date_debut')
            ->limit(100)
            ->get();

        $handoffs = AiConversation::with(['reservation.logement.agence', 'reservation.paiement', 'reservation.confirmation', 'reservation.utilisateur'])
            ->where('statut', AiConversation::STATUT_HANDOFF)
            ->whereHas('reservation.logement', $ownedByAgency)
            ->orderByDesc('handoff_requested_at')
            ->get();

        $intentionOf = fn (Reservation $r) => $r->confirmation?->intention;

        return response()->json([
            'success' => true,
            'data' => [
                'agence' => ['id' => $agencyId, 'nom' => $request->user()->agence->nom],
                'stats' => [
                    'upcoming_total' => $upcoming->count(),
                    'intention_confirmed' => $upcoming->filter(fn ($r) => $intentionOf($r) === ReservationConfirmation::CONFIRMED)->count(),
                    'awaiting_response' => $upcoming->filter(fn ($r) => $r->statut === 'confirme'
                        && in_array($intentionOf($r), [null, ReservationConfirmation::PENDING], true))->count(),
                    'change_requests' => $upcoming->filter(fn ($r) => in_array($intentionOf($r), [
                        ReservationConfirmation::DECLINED,
                        ReservationConfirmation::MODIFICATION_REQUESTED,
                    ], true))->count(),
                    'uncertain' => $upcoming->filter(fn ($r) => $intentionOf($r) === ReservationConfirmation::UNCERTAIN)->count(),
                    'handoffs_open' => $handoffs->count(),
                ],
                'reservations' => $upcoming->map(function (Reservation $r) {
                    $conversation = $r->conversations->first();

                    return [
                        ...ConversationPresenter::reservation($r),
                        'customer' => $r->utilisateur ? ['prenom' => $r->utilisateur->prenom, 'nom' => $r->utilisateur->nom] : null,
                        'confirmation' => ConversationPresenter::confirmation($r->confirmation),
                        'conversation' => $conversation ? [
                            'id' => $conversation->id_conversation,
                            'statut' => $conversation->statut,
                            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                        ] : null,
                    ];
                })->values(),
                'handoffs' => $handoffs->map(fn (AiConversation $c) => ConversationPresenter::header($c, forAgency: true))->values(),
            ],
        ]);
    }

    public function show(AiConversation $conversation): JsonResponse
    {
        Gate::authorize('handleAsAgency', $conversation);

        return response()->json([
            'success' => true,
            'data' => ConversationPresenter::conversation($conversation, forAgency: true),
        ]);
    }

    /** Human agent reply, visible to the customer in the same conversation. */
    public function reply(Request $request, AiConversation $conversation): JsonResponse
    {
        Gate::authorize('handleAsAgency', $conversation);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $message = $conversation->messages()->create([
            'role' => AiMessage::ROLE_AGENT,
            'content' => trim($validated['content']),
            'kind' => 'chat',
            'id_auteur' => $request->user()->id_utilisateur,
        ]);

        $conversation->forceFill([
            'handled_by' => $request->user()->id_utilisateur,
            'last_message_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'data' => ConversationPresenter::message($message->load('auteur'), $request->user()->agence->nom, forAgency: true),
        ], 201);
    }

    /** Close the human handoff: TravelPlan AI handles the conversation alone again. */
    public function resolve(Request $request, AiConversation $conversation): JsonResponse
    {
        Gate::authorize('handleAsAgency', $conversation);

        $conversation->forceFill([
            'statut' => AiConversation::STATUT_ACTIVE,
            'handoff_resolved_at' => now(),
            'handled_by' => $request->user()->id_utilisateur,
        ])->save();

        return response()->json([
            'success' => true,
            'data' => ConversationPresenter::header($conversation->fresh(), forAgency: true),
        ]);
    }
}
