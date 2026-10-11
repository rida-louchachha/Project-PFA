import { Component, EventEmitter, Input, OnChanges, OnDestroy, Output, SimpleChanges, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { Subscription, interval } from 'rxjs';
import { LucideAngularModule, Calendar, Users, Headset, ShieldCheck, CircleCheck } from 'lucide-angular';
import {
  AssistantMessage,
  AssistantService,
  Conversation,
  ConversationHeader,
  assistantErrorMessage,
} from '../assistant.service';
import { ChatThreadComponent } from '../chat-thread/chat-thread.component';
import { IntentionBadgeComponent, intentionLabel } from '../intention-badge/intention-badge.component';

const ARRIVAL_REPLIES = ['Oui, je confirme', 'Je ne suis pas encore sûr', 'Je souhaite modifier mes dates', "Je veux parler à l'agence"];
const RESERVATION_QUESTIONS = [
  'À quelle date commence ma réservation ?',
  'Combien ai-je payé ?',
  'Où se trouve mon logement ?',
  'Est-ce que je peux annuler ?',
  'Est-ce que ma réservation est confirmée ?',
];
const GENERAL_QUESTIONS = ['Montre-moi mes réservations', 'Quelle est la politique de remboursement ?', "Comment contacter l'agence ?"];

/**
 * One TravelPlan AI conversation (reservation-specific or general) for the logged-in customer.
 * Used by the full assistant page and by the floating launcher panel.
 */
@Component({
  selector: 'app-assistant-conversation',
  standalone: true,
  imports: [CommonModule, RouterModule, LucideAngularModule, ChatThreadComponent, IntentionBadgeComponent],
  templateUrl: './assistant-conversation.component.html',
  styleUrl: './assistant-conversation.component.scss',
})
export class AssistantConversationComponent implements OnChanges, OnDestroy {
  private readonly assistant = inject(AssistantService);

  /** null = general conversation */
  @Input() reservationId: number | null = null;
  @Input() compact = false;
  @Output() conversationChange = new EventEmitter<ConversationHeader>();

  protected readonly conversation = signal<Conversation | null>(null);
  protected readonly loading = signal(true);
  protected readonly sending = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly notice = signal<string | null>(null);

  protected readonly quickReplies = computed(() => {
    const conversation = this.conversation();
    if (!conversation) {
      return [];
    }
    const confirmation = conversation.confirmation;
    const awaiting = !!confirmation?.requested_at && ['pending', 'uncertain'].includes(confirmation.intention);
    if (awaiting) {
      return ARRIVAL_REPLIES;
    }
    return conversation.reservation ? RESERVATION_QUESTIONS : GENERAL_QUESTIONS;
  });

  readonly Calendar = Calendar;
  readonly Users = Users;
  readonly Headset = Headset;
  readonly ShieldCheck = ShieldCheck;
  readonly CircleCheck = CircleCheck;

  private loadSub?: Subscription;
  private pollSub?: Subscription;
  private noticeTimer?: ReturnType<typeof setTimeout>;
  private lastFailed: { tempId: number; content: string } | null = null;

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['reservationId']) {
      this.load();
    }
  }

  ngOnDestroy(): void {
    this.loadSub?.unsubscribe();
    this.pollSub?.unsubscribe();
    clearTimeout(this.noticeTimer);
  }

  protected load(): void {
    this.loadSub?.unsubscribe();
    this.stopPolling();
    this.loading.set(true);
    this.error.set(null);
    this.lastFailed = null;

    this.loadSub = this.assistant.open(this.reservationId).subscribe({
      next: conversation => {
        this.conversation.set(conversation);
        this.loading.set(false);
        this.conversationChange.emit(conversation);
        this.syncPolling();
      },
      error: err => {
        this.loading.set(false);
        this.error.set(assistantErrorMessage(err));
      },
    });
  }

  protected onSend(content: string): void {
    const conversation = this.conversation();
    if (!conversation || this.sending()) {
      return;
    }

    const tempId = -Date.now();
    const optimistic: AssistantMessage = {
      id: tempId,
      role: 'customer',
      kind: 'chat',
      content,
      created_at: new Date().toISOString(),
      author: null,
      demo_mode: false,
      automated: false,
      pending: true,
    };

    this.patchMessages(messages => [...messages.filter(m => !m.failed), optimistic]);
    this.sending.set(true);
    this.error.set(null);

    this.assistant.send(conversation.id, content).subscribe({
      next: result => {
        this.patchMessages(messages => [
          ...messages.filter(m => m.id !== tempId),
          result.customer_message,
          result.assistant_message,
        ]);
        this.conversation.update(c => (c ? { ...c, ...result.conversation, messages: c.messages } : c));
        this.sending.set(false);
        this.lastFailed = null;

        if (result.recorded_intention) {
          this.showNotice(`Votre réponse a été enregistrée : ${intentionLabel(result.recorded_intention)}.`);
        }

        const header = this.conversation();
        if (header) {
          this.conversationChange.emit(header);
        }
        this.syncPolling();
      },
      error: err => {
        this.patchMessages(messages => messages.map(m => (m.id === tempId ? { ...m, pending: false, failed: true } : m)));
        this.sending.set(false);
        this.error.set(assistantErrorMessage(err));
        this.lastFailed = { tempId, content };
      },
    });
  }

  protected onRetry(): void {
    if (this.lastFailed) {
      const { tempId, content } = this.lastFailed;
      this.patchMessages(messages => messages.filter(m => m.id !== tempId));
      this.lastFailed = null;
      this.onSend(content);
    } else {
      this.load();
    }
  }

  protected statutLabel(statut: string): string {
    switch (statut) {
      case 'confirme': return 'Confirmée';
      case 'en_attente': return 'En attente de paiement';
      case 'annule': return 'Annulée';
      default: return statut;
    }
  }

  protected formatRange(start: string, end: string): string {
    const opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short' };
    const s = new Date(start);
    const e = new Date(end);
    if (isNaN(s.getTime()) || isNaN(e.getTime())) {
      return '';
    }
    return `${s.toLocaleDateString('fr-FR', opts)} → ${e.toLocaleDateString('fr-FR', { ...opts, year: 'numeric' })}`;
  }

  private patchMessages(fn: (messages: AssistantMessage[]) => AssistantMessage[]): void {
    this.conversation.update(c => (c ? { ...c, messages: fn(c.messages) } : c));
  }

  private showNotice(text: string): void {
    this.notice.set(text);
    clearTimeout(this.noticeTimer);
    this.noticeTimer = setTimeout(() => this.notice.set(null), 6000);
  }

  /** While an agency advisor is involved, refresh periodically to show their replies. */
  private syncPolling(): void {
    const conversation = this.conversation();
    if (conversation?.statut === 'handoff') {
      if (!this.pollSub) {
        this.pollSub = interval(15000).subscribe(() => this.refresh());
      }
    } else {
      this.stopPolling();
    }
  }

  private refresh(): void {
    const current = this.conversation();
    if (!current || this.sending()) {
      return;
    }
    this.assistant.get(current.id).subscribe({
      next: fresh => {
        if (this.sending()) {
          return;
        }
        this.conversation.set(fresh);
        this.syncPolling();
      },
      error: () => {
        /* silent: next tick will retry */
      },
    });
  }

  private stopPolling(): void {
    this.pollSub?.unsubscribe();
    this.pollSub = undefined;
  }
}
