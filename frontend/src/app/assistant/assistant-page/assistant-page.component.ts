import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ActivatedRoute, Router, RouterModule } from '@angular/router';
import { LucideAngularModule, Sparkles, MessageCircle, ArrowLeft } from 'lucide-angular';
import { HeaderComponent } from '../../header/header';
import { AssistantOverview, AssistantReservation, AssistantService, ConversationHeader, assistantErrorMessage } from '../assistant.service';
import { AssistantConversationComponent } from '../assistant-conversation/assistant-conversation.component';
import { IntentionBadgeComponent } from '../intention-badge/intention-badge.component';

/**
 * /assistant and /assistant/:reservationId (target of the emails sent by TravelPlan AI).
 */
@Component({
  selector: 'app-assistant-page',
  standalone: true,
  imports: [CommonModule, RouterModule, LucideAngularModule, HeaderComponent, AssistantConversationComponent, IntentionBadgeComponent],
  templateUrl: './assistant-page.component.html',
  styleUrl: './assistant-page.component.scss',
})
export class AssistantPageComponent implements OnInit {
  private readonly assistant = inject(AssistantService);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  protected readonly overview = signal<AssistantOverview | null>(null);
  protected readonly overviewError = signal<string | null>(null);
  /** undefined = not decided yet, null = general conversation */
  protected readonly selectedReservationId = signal<number | null | undefined>(undefined);

  protected readonly reservations = computed(() => {
    const items = this.overview()?.reservations ?? [];
    const rank = (r: AssistantReservation) => (r.statut === 'annule' ? 2 : new Date(r.date_debut).getTime() < Date.now() ? 1 : 0);
    return [...items].sort((a, b) => rank(a) - rank(b) || new Date(a.date_debut).getTime() - new Date(b.date_debut).getTime());
  });

  readonly Sparkles = Sparkles;
  readonly MessageCircle = MessageCircle;
  readonly ArrowLeft = ArrowLeft;

  ngOnInit(): void {
    this.route.paramMap.subscribe(params => {
      const raw = params.get('reservationId');
      const id = raw ? Number(raw) : NaN;
      if (raw && Number.isInteger(id) && id > 0) {
        this.selectedReservationId.set(id);
      } else if (this.selectedReservationId() === undefined) {
        this.selectedReservationId.set(null);
      }
    });

    this.loadOverview();
  }

  protected loadOverview(): void {
    this.overviewError.set(null);
    this.assistant.overview().subscribe({
      next: overview => this.overview.set(overview),
      error: err => this.overviewError.set(assistantErrorMessage(err)),
    });
  }

  protected select(reservationId: number | null): void {
    this.selectedReservationId.set(reservationId);
    this.router.navigate(reservationId ? ['/assistant', reservationId] : ['/assistant'], { replaceUrl: true });
  }

  /** Keep the sidebar badges in sync with what happens in the chat. */
  protected onConversationChange(header: ConversationHeader): void {
    this.overview.update(o => {
      if (!o) {
        return o;
      }
      return {
        ...o,
        reservations: o.reservations.map(r => (r.id === header.reservation?.id ? { ...r, confirmation: header.confirmation } : r)),
      };
    });
  }

  protected formatRange(r: AssistantReservation): string {
    const s = new Date(r.date_debut);
    const e = new Date(r.date_fin);
    const opts: Intl.DateTimeFormatOptions = { day: 'numeric', month: 'short' };
    return `${s.toLocaleDateString('fr-FR', opts)} → ${e.toLocaleDateString('fr-FR', opts)}`;
  }

  protected isPast(r: AssistantReservation): boolean {
    return new Date(r.date_fin).getTime() < Date.now();
  }
}
