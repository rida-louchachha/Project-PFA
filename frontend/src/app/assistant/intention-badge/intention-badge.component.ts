import { Component, Input } from '@angular/core';
import { CommonModule } from '@angular/common';

export const INTENTION_META: Record<string, { label: string; tone: string; icon: string }> = {
  pending: { label: 'En attente de réponse', tone: 'neutral', icon: '•' },
  confirmed: { label: 'Venue confirmée', tone: 'success', icon: '✓' },
  declined: { label: 'Ne viendra pas', tone: 'danger', icon: '✕' },
  uncertain: { label: 'Incertain', tone: 'warning', icon: '?' },
  modification_requested: { label: 'Modification demandée', tone: 'info', icon: '↻' },
  human_assistance_required: { label: 'Conseiller demandé', tone: 'violet', icon: '☎' },
};

export function intentionLabel(intention: string | null | undefined): string {
  return intention ? (INTENTION_META[intention]?.label ?? intention) : 'Pas encore demandé';
}

/**
 * Customer arrival intention collected by TravelPlan AI (separate from the booking status).
 */
@Component({
  selector: 'app-intention-badge',
  standalone: true,
  imports: [CommonModule],
  template: `
    <span class="intention-badge" [class]="'intention-badge tone-' + tone" [attr.title]="'Intention d\\'arrivée : ' + label">
      <span class="dot" aria-hidden="true">{{ icon }}</span>
      {{ label }}
    </span>
  `,
  styles: [`
    :host { display: inline-flex; }
    .intention-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 600;
      line-height: 1.2;
      white-space: nowrap;
      border: 1px solid transparent;
    }
    .dot { font-size: 11px; font-weight: 700; }
    .tone-neutral { background: #f3f4f6; color: #4b5563; border-color: #e5e7eb; }
    .tone-success { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
    .tone-danger { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
    .tone-warning { background: #fffbeb; color: #b45309; border-color: #fde68a; }
    .tone-info { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
    .tone-violet { background: #f5f3ff; color: #6d28d9; border-color: #ddd6fe; }
    .tone-none { background: #f9fafb; color: #6b7280; border-color: #e5e7eb; }
  `],
})
export class IntentionBadgeComponent {
  @Input() intention: string | null | undefined = null;

  protected get label(): string {
    return intentionLabel(this.intention);
  }

  protected get tone(): string {
    return this.intention ? (INTENTION_META[this.intention]?.tone ?? 'neutral') : 'none';
  }

  protected get icon(): string {
    return this.intention ? (INTENTION_META[this.intention]?.icon ?? '•') : '–';
  }
}
