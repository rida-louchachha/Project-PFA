import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import {
  LucideAngularModule,
  CalendarClock,
  CircleCheck,
  Clock,
  PenLine,
  Headset,
  RefreshCw,
  Sparkles,
  X,
  TriangleAlert,
  MessageSquareText,
} from 'lucide-angular';
import { HeaderComponent } from '../header/header';
import {
  AgencyDashboard,
  AgencyReservationRow,
  AssistantService,
  Conversation,
  assistantErrorMessage,
} from '../assistant/assistant.service';
import { ChatThreadComponent } from '../assistant/chat-thread/chat-thread.component';
import { IntentionBadgeComponent } from '../assistant/intention-badge/intention-badge.component';
import { AgencyInsightsComponent } from './agency-insights/agency-insights.component';

type Tab = 'all' | 'awaiting' | 'confirmed' | 'changes' | 'handoff';

/**
 * /agence/assistant — upcoming stays of the agency, customer answers collected by
 * TravelPlan AI, and conversations that need a human advisor.
 */
@Component({
  selector: 'app-agency-assistant-dashboard',
  standalone: true,
  imports: [CommonModule, RouterModule, LucideAngularModule, HeaderComponent, ChatThreadComponent, IntentionBadgeComponent, AgencyInsightsComponent],
  templateUrl: './agency-assistant-dashboard.component.html',
  styleUrl: './agency-assistant-dashboard.component.scss',
})
export class AgencyAssistantDashboardComponent implements OnInit {
  private readonly assistant = inject(AssistantService);

  protected readonly dashboard = signal<AgencyDashboard | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly tab = signal<Tab>('all');

  protected readonly conversation = signal<Conversation | null>(null);
  protected readonly conversationLoading = signal(false);
  protected readonly conversationError = signal<string | null>(null);
  protected readonly replying = signal(false);

  protected readonly tabs: { id: Tab; label: string }[] = [
    { id: 'all', label: 'Tous les séjours' },
    { id: 'awaiting', label: 'Sans réponse' },
    { id: 'confirmed', label: 'Venue confirmée' },
    { id: 'changes', label: 'Annulation / modification' },
    { id: 'handoff', label: 'Assistance humaine' },
  ];

  protected readonly rows = computed(() => {
    const data = this.dashboard();
    if (!data) {
      return [];
    }
    const handoffIds = new Set(data.handoffs.map(h => h.id));
    const intention = (r: AgencyReservationRow) => r.confirmation?.intention ?? null;

    switch (this.tab()) {
      case 'awaiting':
        return data.reservations.filter(r => r.statut === 'confirme' && (intention(r) === null || intention(r) === 'pending'));
      case 'confirmed':
        return data.reservations.filter(r => intention(r) === 'confirmed');
      case 'changes':
        return data.reservations.filter(r => ['declined', 'modification_requested', 'uncertain'].includes(intention(r) ?? ''));
      case 'handoff':
        return data.reservations.filter(r => r.conversation && handoffIds.has(r.conversation.id));
      default:
        return data.reservations;
    }
  });

  readonly CalendarClock = CalendarClock;
  readonly CircleCheck = CircleCheck;
  readonly Clock = Clock;
  readonly PenLine = PenLine;
  readonly Headset = Headset;
  readonly RefreshCw = RefreshCw;
  readonly Sparkles = Sparkles;
  readonly X = X;
  readonly TriangleAlert = TriangleAlert;
  readonly MessageSquareText = MessageSquareText;

  ngOnInit(): void {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(null);
    this.assistant.agencyDashboard().subscribe({
      next: data => {
        this.dashboard.set(data);
        this.loading.set(false);
      },
      error: err => {
        this.loading.set(false);
        this.error.set(err?.status === 403 ? (err.error?.message ?? 'Accès réservé aux agences.') : assistantErrorMessage(err));
      },
    });
  }

  protected openConversation(conversationId: number): void {
    this.conversationLoading.set(true);
    this.conversationError.set(null);
    this.conversation.set(null);
    this.assistant.agencyConversation(conversationId).subscribe({
      next: conversation => {
        this.conversation.set(conversation);
        this.conversationLoading.set(false);
      },
      error: err => {
        this.conversationLoading.set(false);
        this.conversationError.set(assistantErrorMessage(err));
      },
    });
  }

  protected closeConversation(): void {
    this.conversation.set(null);
    this.conversationError.set(null);
  }

  protected reply(content: string): void {
    const conversation = this.conversation();
    if (!conversation) {
      return;
    }
    this.replying.set(true);
    this.conversationError.set(null);
    this.assistant.agencyReply(conversation.id, content).subscribe({
      next: message => {
        this.conversation.update(c => (c ? { ...c, messages: [...c.messages, message] } : c));
        this.replying.set(false);
      },
      error: err => {
        this.replying.set(false);
        this.conversationError.set(assistantErrorMessage(err));
      },
    });
  }

  protected resolve(): void {
    const conversation = this.conversation();
    if (!conversation) {
      return;
    }
    this.assistant.resolveHandoff(conversation.id).subscribe({
      next: header => {
        this.conversation.update(c => (c ? { ...c, ...header, messages: c.messages } : c));
        this.load();
      },
      error: err => this.conversationError.set(assistantErrorMessage(err)),
    });
  }

  protected handoffFor(row: AgencyReservationRow): boolean {
    return !!row.conversation && row.conversation.statut === 'handoff';
  }

  protected tabCount(tab: Tab): number {
    const data = this.dashboard();
    if (!data) {
      return 0;
    }
    switch (tab) {
      case 'awaiting': return data.stats.awaiting_response;
      case 'confirmed': return data.stats.intention_confirmed;
      case 'changes': return data.stats.change_requests + data.stats.uncertain;
      case 'handoff': return data.stats.handoffs_open;
      default: return data.stats.upcoming_total;
    }
  }

  protected formatDate(value: string): string {
    const d = new Date(value);
    return isNaN(d.getTime()) ? value : d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: 'numeric' });
  }

  protected paymentLabel(status: string | null): string {
    switch (status) {
      case 'regle': return 'Réglé';
      case 'en_attente': return 'En attente';
      case 'echoue': return 'Échoué';
      case null: return 'Aucun';
      default: return status;
    }
  }
}
