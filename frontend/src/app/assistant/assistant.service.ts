import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';

export type Intention =
  | 'pending'
  | 'confirmed'
  | 'declined'
  | 'uncertain'
  | 'modification_requested'
  | 'human_assistance_required';

export interface AssistantConfirmation {
  intention: Intention;
  source: string;
  requested_at: string | null;
  responded_at: string | null;
  needs_attention: boolean;
}

export interface AssistantReservation {
  id: number;
  date_debut: string;
  date_fin: string;
  nb_personnes: number;
  statut: 'en_attente' | 'confirme' | 'annule';
  montant_total: string;
  devise: string;
  paiement_statut: string | null;
  logement: { titre: string; type: string; adresse: string | null; photo_url: string | null } | null;
  agence: { nom: string } | null;
  confirmation?: AssistantConfirmation | null;
}

export interface AssistantMessage {
  id: number;
  role: 'customer' | 'assistant' | 'agent';
  kind: string;
  content: string;
  created_at: string;
  author: string | null;
  demo_mode: boolean;
  automated: boolean;
  /** Agency view only */
  intent?: string | null;
  audit?: {
    provider?: string;
    model?: string;
    fallback?: boolean;
    fallback_reason?: string | null;
    tools?: { name: string; ok: boolean; ms: number }[];
    latency_ms?: number;
    ungrounded_numbers?: string[];
    classification?: { intention: string; confidence: number; language: string };
  };
  /** Client-side state for optimistic sends */
  pending?: boolean;
  failed?: boolean;
}

export interface ConversationHeader {
  id: number;
  statut: 'active' | 'handoff' | 'closed';
  handoff_reason: string | null;
  handoff_requested_at: string | null;
  last_message_at: string | null;
  reservation: AssistantReservation | null;
  confirmation: AssistantConfirmation | null;
  customer?: { prenom: string; nom: string } | null;
  last_message?: { role: string; content: string } | null;
}

export interface Conversation extends ConversationHeader {
  messages: AssistantMessage[];
}

export interface AssistantOverview {
  assistant: { name: string; demo_mode: boolean };
  conversations: ConversationHeader[];
  reservations: AssistantReservation[];
}

export interface SendMessageResult {
  customer_message: AssistantMessage;
  assistant_message: AssistantMessage;
  recorded_intention: Intention | null;
  conversation: ConversationHeader;
}

export interface AgencyReservationRow extends AssistantReservation {
  customer: { prenom: string; nom: string } | null;
  conversation: { id: number; statut: string; last_message_at: string | null } | null;
}

export interface AgencyDashboard {
  agence: { id: number; nom: string };
  stats: {
    upcoming_total: number;
    intention_confirmed: number;
    awaiting_response: number;
    change_requests: number;
    uncertain: number;
    handoffs_open: number;
  };
  reservations: AgencyReservationRow[];
  handoffs: ConversationHeader[];
}

interface ApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
}

/**
 * TravelPlan AI API client. The browser never talks to the LLM directly:
 * every call goes to the Laravel backend, which holds the API key.
 */
@Injectable({ providedIn: 'root' })
export class AssistantService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8000/api';

  overview(): Observable<AssistantOverview> {
    return this.http.get<ApiResponse<AssistantOverview>>(`${this.apiUrl}/assistant/conversations`).pipe(map(r => r.data));
  }

  /** Opens (or creates) the conversation of one of my reservations, or the general one (null). */
  open(reservationId: number | null): Observable<Conversation> {
    return this.http
      .post<ApiResponse<Conversation>>(`${this.apiUrl}/assistant/conversations`, { id_reservation: reservationId })
      .pipe(map(r => r.data));
  }

  get(conversationId: number): Observable<Conversation> {
    return this.http.get<ApiResponse<Conversation>>(`${this.apiUrl}/assistant/conversations/${conversationId}`).pipe(map(r => r.data));
  }

  send(conversationId: number, content: string): Observable<SendMessageResult> {
    return this.http
      .post<ApiResponse<SendMessageResult>>(`${this.apiUrl}/assistant/conversations/${conversationId}/messages`, { content })
      .pipe(map(r => r.data));
  }

  // ---- Agency ----

  agencyDashboard(): Observable<AgencyDashboard> {
    return this.http.get<ApiResponse<AgencyDashboard>>(`${this.apiUrl}/agency/assistant/dashboard`).pipe(map(r => r.data));
  }

  agencyConversation(conversationId: number): Observable<Conversation> {
    return this.http
      .get<ApiResponse<Conversation>>(`${this.apiUrl}/agency/assistant/conversations/${conversationId}`)
      .pipe(map(r => r.data));
  }

  agencyReply(conversationId: number, content: string): Observable<AssistantMessage> {
    return this.http
      .post<ApiResponse<AssistantMessage>>(`${this.apiUrl}/agency/assistant/conversations/${conversationId}/messages`, { content })
      .pipe(map(r => r.data));
  }

  resolveHandoff(conversationId: number): Observable<ConversationHeader> {
    return this.http
      .post<ApiResponse<ConversationHeader>>(`${this.apiUrl}/agency/assistant/conversations/${conversationId}/resolve`, {})
      .pipe(map(r => r.data));
  }
}

/** Human-readable error for HTTP failures. */
export function assistantErrorMessage(error: any): string {
  switch (error?.status) {
    case 0:
      return 'Le serveur TravelPlan est injoignable. Vérifiez votre connexion puis réessayez.';
    case 401:
      return 'Votre session a expiré. Reconnectez-vous pour continuer.';
    case 403:
      return "Vous n'avez pas accès à cette conversation.";
    case 404:
      return 'Conversation ou réservation introuvable.';
    case 422:
      return error?.error?.errors?.content?.[0] ?? error?.error?.message ?? 'Message invalide.';
    case 429:
      return 'Vous envoyez beaucoup de messages : patientez une minute avant de réessayer.';
    default:
      return "L'assistant rencontre un problème temporaire. Réessayez dans un instant.";
  }
}
