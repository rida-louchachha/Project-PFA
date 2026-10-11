import { Component, EventEmitter, Output, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { LucideAngularModule, Sparkles, ArrowRight, LoaderCircle } from 'lucide-angular';
import { DiscoveryService, SmartSearchResult } from '../discovery.service';

const EXAMPLES = [
  'Un riad à Marrakech pour 4 personnes ce week-end, moins de 1000 DH',
  'Studio calme à Fès pour deux, 3 nuits',
  'Nuit dans le désert à Merzouga en famille',
  'Villa avec piscine près de la plage à Taghazout',
];

/**
 * "Recherche intelligente": the traveller describes the trip in natural language,
 * TravelPlan AI turns it into validated search filters.
 */
@Component({
  selector: 'app-smart-search',
  standalone: true,
  imports: [CommonModule, FormsModule, LucideAngularModule],
  template: `
    <form class="smart-search" (ngSubmit)="submit()" [class.busy]="loading()">
      <div class="badge"><lucide-icon [img]="Sparkles" size="14"></lucide-icon> Recherche intelligente IA</div>
      <div class="input-row">
        <lucide-icon class="lead" [img]="Sparkles" size="22"></lucide-icon>
        <input
          name="query"
          [(ngModel)]="query"
          maxlength="300"
          autocomplete="off"
          placeholder="Décrivez votre séjour idéal… ex. un riad à Marrakech pour 4, moins de 1000 DH"
          aria-label="Décrivez votre séjour idéal" />
        <button type="submit" [disabled]="!query.trim() || loading()">
          <lucide-icon *ngIf="!loading()" [img]="ArrowRight" size="18"></lucide-icon>
          <lucide-icon *ngIf="loading()" class="spin" [img]="LoaderCircle" size="18"></lucide-icon>
          <span>{{ loading() ? 'Analyse…' : 'Trouver' }}</span>
        </button>
      </div>
      <div class="examples">
        <span class="try">Essayez :</span>
        <button type="button" class="example" *ngFor="let e of examples" (click)="useExample(e)">{{ e }}</button>
      </div>
      <p class="error" *ngIf="error()">{{ error() }}</p>
    </form>
  `,
  styles: [`
    .smart-search {
      position: relative;
      max-width: 860px;
      margin: 0 auto;
      padding: 18px 18px 14px;
      border-radius: 22px;
      background: rgba(255, 255, 255, 0.16);
      border: 1px solid rgba(255, 255, 255, 0.35);
      backdrop-filter: blur(10px);
      box-shadow: 0 20px 50px rgba(30, 20, 70, 0.25);
    }
    .badge {
      display: inline-flex; align-items: center; gap: 6px;
      margin-bottom: 10px; padding: 4px 10px; border-radius: 999px;
      font-size: 12px; font-weight: 700; letter-spacing: 0.02em;
      color: #4c1d95; background: #fde68a;
    }
    .input-row {
      display: flex; align-items: center; gap: 10px;
      padding: 6px 6px 6px 16px; border-radius: 16px; background: white;
      box-shadow: 0 6px 18px rgba(17, 24, 39, 0.12);
    }
    .lead { color: #764ba2; flex-shrink: 0; }
    input {
      flex: 1; min-width: 0; border: none; outline: none; font: inherit;
      font-size: 16px; color: #111827; background: transparent; padding: 12px 0;
    }
    button[type=submit] {
      display: inline-flex; align-items: center; gap: 6px; flex-shrink: 0;
      height: 48px; padding: 0 20px; border: none; border-radius: 12px; cursor: pointer;
      font: inherit; font-weight: 700; color: white;
      background: linear-gradient(135deg, #f59e0b, #ea580c);
      box-shadow: 0 8px 18px rgba(234, 88, 12, 0.35);
      transition: transform .15s ease, opacity .2s ease;
    }
    button[type=submit]:hover:not(:disabled) { transform: translateY(-1px); }
    button[type=submit]:disabled { opacity: .55; cursor: not-allowed; box-shadow: none; }
    .examples { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 12px; }
    .try { font-size: 12.5px; color: rgba(255, 255, 255, 0.85); font-weight: 600; }
    .example {
      padding: 6px 12px; border-radius: 999px; cursor: pointer; font: inherit; font-size: 12.5px;
      color: white; background: rgba(255, 255, 255, 0.14); border: 1px solid rgba(255, 255, 255, 0.35);
      transition: background .2s ease;
    }
    .example:hover { background: rgba(255, 255, 255, 0.28); }
    .error { margin: 10px 0 0; color: #fee2e2; font-size: 13px; }
    .spin { animation: spin 1s linear infinite; }
    @keyframes spin { to { transform: rotate(360deg); } }
    @media (max-width: 640px) {
      .input-row { flex-wrap: wrap; padding: 10px; }
      input { width: 100%; padding: 6px 0; }
      button[type=submit] { width: 100%; justify-content: center; }
      .examples .example:nth-child(n+4) { display: none; }
    }
  `],
})
export class SmartSearchComponent {
  private readonly discovery = inject(DiscoveryService);

  @Output() result = new EventEmitter<SmartSearchResult>();

  protected query = '';
  protected readonly loading = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly examples = EXAMPLES;

  readonly Sparkles = Sparkles;
  readonly ArrowRight = ArrowRight;
  readonly LoaderCircle = LoaderCircle;

  protected useExample(example: string): void {
    this.query = example;
    this.submit();
  }

  protected submit(): void {
    const query = this.query.trim();
    if (!query || this.loading()) {
      return;
    }
    this.loading.set(true);
    this.error.set(null);
    this.discovery.smartSearch(query).subscribe({
      next: result => {
        this.loading.set(false);
        this.result.emit(result);
      },
      error: err => {
        this.loading.set(false);
        this.error.set(err?.status === 429
          ? 'Trop de recherches : patientez une minute.'
          : "La recherche intelligente est momentanément indisponible. Utilisez les filtres ci-dessous.");
      },
    });
  }
}
