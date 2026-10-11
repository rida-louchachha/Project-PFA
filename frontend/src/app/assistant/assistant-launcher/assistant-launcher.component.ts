import { Component, DestroyRef, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterModule } from '@angular/router';
import { filter, map, startWith } from 'rxjs';
import { LucideAngularModule, Sparkles, X, Maximize2 } from 'lucide-angular';
import { AuthService } from '../../auth/auth';
import { AssistantReservation, AssistantService } from '../assistant.service';
import { AssistantConversationComponent } from '../assistant-conversation/assistant-conversation.component';

/**
 * Floating TravelPlan AI button + chat panel, available on every customer page.
 */
@Component({
  selector: 'app-assistant-launcher',
  standalone: true,
  imports: [CommonModule, RouterModule, LucideAngularModule, AssistantConversationComponent],
  templateUrl: './assistant-launcher.component.html',
  styleUrl: './assistant-launcher.component.scss',
})
export class AssistantLauncherComponent {
  private readonly auth = inject(AuthService);
  private readonly assistant = inject(AssistantService);
  private readonly router = inject(Router);

  protected readonly user = toSignal(this.auth.currentUser$, { initialValue: null });
  protected readonly url = toSignal(
    this.router.events.pipe(
      filter((e): e is NavigationEnd => e instanceof NavigationEnd),
      map(e => e.urlAfterRedirects),
      startWith(this.router.url),
    ),
    { initialValue: this.router.url },
  );

  protected readonly open = signal(false);
  protected readonly reservations = signal<AssistantReservation[]>([]);
  /** null = general conversation */
  protected readonly selected = signal<number | null>(null);
  private loaded = false;

  readonly Sparkles = Sparkles;
  readonly X = X;
  readonly Maximize2 = Maximize2;

  constructor() {
    // Reset when the user logs out
    this.auth.currentUser$.pipe(takeUntilDestroyed(inject(DestroyRef))).subscribe(user => {
      if (!user) {
        this.open.set(false);
        this.loaded = false;
        this.reservations.set([]);
      }
    });
  }

  protected visible(): boolean {
    const url = this.url() ?? '';
    return this.user()?.role === 'client'
      && !url.startsWith('/auth')
      && !url.startsWith('/assistant')
      && !url.startsWith('/agence');
  }

  protected toggle(): void {
    this.open.update(v => !v);
    if (this.open() && !this.loaded) {
      this.loaded = true;
      this.assistant.overview().subscribe({
        next: overview => {
          const upcoming = overview.reservations
            .filter(r => r.statut !== 'annule' && new Date(r.date_fin).getTime() >= Date.now())
            .sort((a, b) => new Date(a.date_debut).getTime() - new Date(b.date_debut).getTime());
          this.reservations.set(upcoming);
          // Default to the next upcoming stay
          if (upcoming.length) {
            this.selected.set(upcoming[0].id);
          }
        },
        error: () => (this.loaded = false),
      });
    }
  }

  protected onSelect(value: string): void {
    this.selected.set(value === 'general' ? null : Number(value));
  }

  protected expand(): void {
    const id = this.selected();
    this.open.set(false);
    this.router.navigate(id ? ['/assistant', id] : ['/assistant']);
  }

  protected optionLabel(r: AssistantReservation): string {
    const date = new Date(r.date_debut).toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' });
    return `${r.logement?.titre ?? 'Réservation'} · ${date} (n°${r.id})`;
  }
}
