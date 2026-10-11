<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Models\AiConversation;
use App\Models\Models\AiMessage;
use App\Models\Models\Reservation;
use App\Services\Ai\AssistantService;
use App\Services\Ai\Support\ConversationPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Customer-facing TravelPlan AI endpoints (auth:sanctum).
 */
class AssistantController extends Controller
{
    public function __construct(private readonly AssistantService $assistant)
    {
    }

    /** My conversations + my reservations (to start a reservation-specific chat). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = AiConversation::with(['reservation.logement.agence', 'reservation.paiement', 'reservation.confirmation'])
            ->where('id_utilisateur', $user->id_utilisateur)
            ->orderByDesc('last_message_at')
            ->get();

        $lastMessages = AiMessage::query()
            ->whereIn('id_message', function ($q) use ($conversations) {
                $q->selectRaw('max(id_message)')
                    ->from('ai_messages')
                    ->whereIn('id_conversation', $conversations->pluck('id_conversation'))
                    ->groupBy('id_conversation');
            })
            ->get()
            ->keyBy('id_conversation');

        $reservations = Reservation::with(['logement.agence', 'paiement', 'confirmation'])
            ->where('id_utilisateur', $user->id_utilisateur)
            ->orderByDesc('date_debut')
            ->limit(30)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'assistant' => [
                    'name' => 'TravelPlan AI',
                    'demo_mode' => config('ai.provider') !== 'anthropic',
                ],
                'conversations' => $conversations->map(fn (AiConversation $c) => [
                    ...ConversationPresenter::header($c),
                    'last_message' => ($m = $lastMessages->get($c->id_conversation)) ? [
                        'role' => $m->role,
                        'content' => mb_strimwidth($m->content, 0, 140, '…'),
                    ] : null,
                ])->values(),
                'reservations' => $reservations->map(fn (Reservation $r) => [
                    ...ConversationPresenter::reservation($r),
                    'confirmation' => ConversationPresenter::confirmation($r->confirmation),
                ])->values(),
            ],
        ]);
    }

    /** Open (or create) the conversation for one of MY reservations, or the general one. */
    public function open(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_reservation' => ['nullable', 'integer', 'min:1'],
        ]);

        $user = $request->user();
        $reservation = null;

        if (!empty($validated['id_reservation'])) {
            // Ownership check — a 404 does not reveal whether another customer's reservation exists
            $reservation = Reservation::where('id_reservation', $validated['id_reservation'])
                ->where('id_utilisateur', $user->id_utilisateur)
                ->first();

            if (!$reservation) {
                return response()->json(['success' => false, 'message' => 'Réservation introuvable.'], 404);
            }
        }

        $conversation = $this->assistant->openConversation($user, $reservation);

        return response()->json([
            'success' => true,
            'data' => ConversationPresenter::conversation($conversation->fresh()),
        ]);
    }

    public function show(AiConversation $conversation): JsonResponse
    {
        Gate::authorize('participate', $conversation);

        return response()->json([
            'success' => true,
            'data' => ConversationPresenter::conversation($conversation),
        ]);
    }

    public function sendMessage(Request $request, AiConversation $conversation): JsonResponse
    {
        Gate::authorize('participate', $conversation);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:' . (int) config('ai.chat.max_message_length')],
        ]);

        if (trim($validated['content']) === '') {
            return response()->json(['success' => false, 'message' => 'Le message est vide.'], 422);
        }

        $result = $this->assistant->reply($request->user(), $conversation, $validated['content']);
        $fresh = $conversation->fresh();

        return response()->json([
            'success' => true,
            'data' => [
                'customer_message' => ConversationPresenter::message($result['customer'], null),
                'assistant_message' => ConversationPresenter::message($result['assistant'], null),
                'recorded_intention' => $result['recorded_intention'],
                'conversation' => ConversationPresenter::header($fresh),
            ],
        ]);
    }
}
