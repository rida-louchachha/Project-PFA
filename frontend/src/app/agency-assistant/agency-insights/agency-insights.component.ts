import { Component, OnInit, computed, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { LucideAngularModule, Sparkles, Wallet, BedDouble, CircleCheck, Star, RefreshCw } from 'lucide-angular';
import { AgencyInsights, DiscoveryService } from '../../discovery/discovery.service';

/**
 * Agency AI insights: KPIs computed from MySQL, arrivals for the next 14 days,
 * and a short summary written by TravelPlan AI (rejected if it invents numbers).
 */
@Component({
  selector: 'app-agency-insights',
  standalone: true,
  imports: [CommonModule, LucideAngularModule],
  template: `
    <section class="insights" *ngIf="data() as d; else loadingTpl">
      <div class="tiles">
        <div class="tile">
          <div class="icon"><lucide-icon [img]="Wallet" size="18"></lucide-icon></div>
          <div class="value">{{ d.kpis.revenus_prevus_30j | number:'1.0-0':'fr' }} <small>{{ d.devise }}</small></div>
          <div class="label">Revenus confirmés · 30 jours</div>
        </div>
        <div class="tile">
          <div class="icon"><lucide-icon [img]="BedDouble" size="18"></lucide-icon></div>
          <div class="value">{{ d.kpis.taux_occupation_30j }} <small>%</small></div>
          <div class="label">Taux d'occupation · 30 jours</div>
        </div>
        <div class="tile">
          <div class="icon"><lucide-icon [img]="CircleCheck" size="18"></lucide-icon></div>
          <div class="value">{{ d.kpis.taux_confirmation ?? '–' }} <small *ngIf="d.kpis.taux_confirmation !== null">%</small></div>
          <div class="label">Venues confirmées par l'IA ({{ d.kpis.venues_confirmees }}/{{ d.kpis.demandes_confirmation }})</div>
        </div>
        <div class="tile">
          <div class="icon"><lucide-icon [img]="Star" size="18"></lucide-icon></div>
          <div class="value">{{ d.kpis.note_moyenne | number:'1.1-1':'fr' }} <small>/5</small></div>
          <div class="label">Note moyenne · {{ d.kpis.nombre_avis }} avis</div>
        </div>
      </div>

      <div class="row">
        <figure class="chart-card">
          <figcaption>
            <span class="title">Arrivées prévues · 14 prochains jours</span>
            <span class="sub">{{ totalArrivals() }} arrivée{{ totalArrivals() > 1 ? 's' : '' }} au total</span>
          </figcaption>
          <div class="chart" role="img" [attr.aria-label]="ariaSummary()">
            <div class="plot">
              <div class="bar-slot" *ngFor="let a of d.arrivees_14j; let i = index"
                   (mouseenter)="hover.set(i)" (mouseleave)="hover.set(null)" (focus)="hover.set(i)" (blur)="hover.set(null)" tabindex="0">
                <span class="peak-label" *ngIf="a.count === maxCount() && a.count > 0">{{ a.count }}</span>
                <span class="bar" [style.height.%]="barHeight(a.count)" [class.empty]="a.count === 0"></span>
                <span class="tooltip" *ngIf="hover() === i" [style.bottom]="'calc(' + barHeight(a.count) + '% + 22px)'">{{ a.label }} : {{ a.count }} arrivée{{ a.count > 1 ? 's' : '' }}</span>
              </div>
            </div>
            <div class="axis">
              <span *ngFor="let a of d.arrivees_14j; let i = index" [class.muted]="i % 2 === 1">{{ a.label }}</span>
            </div>
          </div>
          <table class="sr-only">
            <caption>Arrivées par jour</caption>
            <tr *ngFor="let a of d.arrivees_14j"><th scope="row">{{ a.label }}</th><td>{{ a.count }}</td></tr>
          </table>
        </figure>

        <div class="summary-card">
          <div class="summary-head">
            <span class="ai"><lucide-icon [img]="Sparkles" size="14"></lucide-icon> Résumé TravelPlan AI</span>
            <button type="button" class="refresh" (click)="load()" aria-label="Actualiser"><lucide-icon [img]="RefreshCw" size="14"></lucide-icon></button>
          </div>
          <p>{{ d.resume }}</p>
          <span class="source">{{ d.resume_source === 'ai' ? 'Rédigé par l\\'IA à partir de vos indicateurs (chiffres vérifiés)' : 'Généré à partir de vos indicateurs (mode démo)' }}</span>
        </div>
      </div>
    </section>
    <ng-template #loadingTpl>
      <section class="insights loading" *ngIf="!error()"><div class="skeleton"></div></section>
    </ng-template>
  `,
  styles: [`
    :host { display: block; margin-bottom: 20px; }
    .tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; }
    .tile { padding: 16px; border-radius: 18px; background: white; box-shadow: 0 10px 25px rgba(17, 24, 39, 0.07); }
    .icon { width: 34px; height: 34px; border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #6d28d9; background: #f5f3ff; }
    .value { margin-top: 10px; font-size: 26px; font-weight: 800; color: #111827; line-height: 1.1; }
    .value small { font-size: 14px; font-weight: 600; color: #6b7280; }
    .label { margin-top: 2px; font-size: 12.5px; color: #6b7280; }
    .row { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 14px; margin-top: 14px; }
    .chart-card, .summary-card { margin: 0; padding: 16px 18px; border-radius: 18px; background: white; box-shadow: 0 10px 25px rgba(17, 24, 39, 0.07); }
    figcaption { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 10px; }
    .title { font-weight: 700; color: #111827; font-size: 14.5px; }
    .sub { font-size: 12.5px; color: #6b7280; }
    .plot { position: relative; display: flex; align-items: flex-end; gap: 2px; height: 170px; border-bottom: 1px solid #e5e7eb; }
    .bar-slot { position: relative; flex: 1; height: 100%; display: flex; flex-direction: column; justify-content: flex-end; align-items: center; cursor: default; outline: none; }
    .bar-slot:hover .bar, .bar-slot:focus .bar { background: #6d28d9; }
    .bar { width: 70%; max-width: 26px; min-height: 2px; border-radius: 4px 4px 0 0; background: #7c3aed; transition: height .4s ease; }
    .bar.empty { background: #e5e7eb; }
    .peak-label { font-size: 11px; font-weight: 700; color: #374151; margin-bottom: 3px; }
    .tooltip {
      position: absolute; left: 50%; transform: translateX(-50%); z-index: 2;
      white-space: nowrap; padding: 5px 8px; border-radius: 8px; font-size: 12px; color: white; background: #111827;
      box-shadow: 0 6px 14px rgba(17, 24, 39, 0.25); pointer-events: none;
    }
    .axis { display: flex; gap: 2px; margin-top: 6px; }
    .axis span { flex: 1; text-align: center; font-size: 10.5px; color: #6b7280; white-space: nowrap; overflow: hidden; }
    .axis span.muted { color: transparent; }
    .summary-card { display: flex; flex-direction: column; background: linear-gradient(160deg, #f5f3ff, #ffffff 60%); border: 1px solid #ede9fe; }
    .summary-head { display: flex; align-items: center; justify-content: space-between; }
    .ai { display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; color: white; background: linear-gradient(135deg, #667eea, #764ba2); }
    .refresh { width: 28px; height: 28px; border: none; border-radius: 8px; background: white; color: #6d28d9; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .summary-card p { flex: 1; margin: 12px 0; font-size: 14px; line-height: 1.6; color: #1f2937; }
    .source { font-size: 11.5px; color: #6b7280; }
    .sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0 0 0 0); border: 0; }
    .skeleton { height: 120px; border-radius: 18px; background: linear-gradient(90deg, #eef0f6 25%, #f7f8fc 50%, #eef0f6 75%); background-size: 200% 100%; animation: shimmer 1.4s infinite; }
    @keyframes shimmer { from { background-position: 200% 0; } to { background-position: -200% 0; } }
    @media (max-width: 1100px) { .tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } .row { grid-template-columns: 1fr; } }
  `],
})
export class AgencyInsightsComponent implements OnInit {
  private readonly discovery = inject(DiscoveryService);

  protected readonly data = signal<AgencyInsights | null>(null);
  protected readonly error = signal(false);
  protected readonly hover = signal<number | null>(null);

  protected readonly maxCount = computed(() => Math.max(0, ...(this.data()?.arrivees_14j ?? []).map(a => a.count)));
  protected readonly totalArrivals = computed(() => (this.data()?.arrivees_14j ?? []).reduce((s, a) => s + a.count, 0));
  protected readonly ariaSummary = computed(() =>
    'Arrivées par jour sur 14 jours : ' + (this.data()?.arrivees_14j ?? []).map(a => `${a.label} ${a.count}`).join(', '));

  readonly Sparkles = Sparkles;
  readonly Wallet = Wallet;
  readonly BedDouble = BedDouble;
  readonly CircleCheck = CircleCheck;
  readonly Star = Star;
  readonly RefreshCw = RefreshCw;

  ngOnInit(): void {
    this.load();
  }

  protected load(): void {
    this.discovery.agencyInsights().subscribe({
      next: d => { this.data.set(d); this.error.set(false); },
      error: () => this.error.set(true),
    });
  }

  protected barHeight(count: number): number {
    const max = this.maxCount();
    return max === 0 ? 0 : Math.max(4, (count / max) * 68);
  }
}
