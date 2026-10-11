import { Component, EventEmitter, OnInit, Output, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { LucideAngularModule, Sparkles, Star, MapPin } from 'lucide-angular';
import { DiscoveryService, Recommendations } from '../discovery.service';

/** "Recommandé pour vous": explainable recommendations based on the customer's bookings. */
@Component({
  selector: 'app-recommendations',
  standalone: true,
  imports: [CommonModule, LucideAngularModule],
  template: `
    <section class="reco" *ngIf="data()?.items?.length">
      <div class="head">
        <div>
          <h2><lucide-icon [img]="Sparkles" size="20"></lucide-icon> {{ data()!.personalised ? 'Recommandé pour vous' : 'Nos coups de cœur' }}</h2>
          <p *ngIf="data()!.personalised">Sélection calculée à partir de vos séjours précédents — chaque suggestion explique pourquoi.</p>
          <p *ngIf="!data()!.personalised">Réservez un premier séjour et TravelPlan apprendra vos préférences.</p>
        </div>
      </div>
      <div class="grid">
        <article class="item" *ngFor="let r of data()!.items" (click)="open.emit(r.id)" tabindex="0" (keydown.enter)="open.emit(r.id)">
          <div class="photo" [style.background-image]="r.photo_url ? 'url(' + r.photo_url + ')' : null">
            <span class="note" *ngIf="r.note"><lucide-icon [img]="Star" size="12"></lucide-icon>{{ r.note | number:'1.1-1':'fr' }}</span>
          </div>
          <div class="body">
            <h3>{{ r.titre }}</h3>
            <div class="where"><lucide-icon [img]="MapPin" size="12"></lucide-icon>{{ r.ville }} · {{ r.agence }}</div>
            <div class="reasons">
              <span class="reason" *ngFor="let reason of r.raisons">{{ reason }}</span>
            </div>
            <div class="price"><strong>{{ r.tarif_amount | number:'1.0-0':'fr' }} {{ r.devise }}</strong> / {{ r.tarif_unit }}</div>
          </div>
        </article>
      </div>
    </section>
  `,
  styles: [`
    .reco { margin: 0 0 28px; }
    .head h2 { display: flex; align-items: center; gap: 8px; margin: 0; font-size: 22px; font-weight: 800; color: #111827; }
    .head h2 lucide-icon { color: #7c3aed; }
    .head p { margin: 4px 0 14px; color: #6b7280; font-size: 14px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 16px; }
    .item {
      background: white; border-radius: 18px; overflow: hidden; cursor: pointer;
      box-shadow: 0 8px 22px rgba(17, 24, 39, 0.08); border: 1px solid #f1f1f6;
      transition: transform .2s ease, box-shadow .2s ease;
    }
    .item:hover, .item:focus { transform: translateY(-3px); box-shadow: 0 14px 30px rgba(17, 24, 39, 0.14); outline: none; }
    .photo { position: relative; height: 140px; background: linear-gradient(135deg, #667eea, #764ba2) center/cover no-repeat; }
    .note {
      position: absolute; top: 10px; right: 10px; display: inline-flex; align-items: center; gap: 3px;
      padding: 3px 8px; border-radius: 999px; font-size: 12px; font-weight: 700; color: #92400e; background: #fef3c7;
    }
    .body { padding: 12px 14px 14px; }
    h3 { margin: 0; font-size: 15.5px; font-weight: 700; color: #111827; }
    .where { display: flex; align-items: center; gap: 4px; margin-top: 3px; font-size: 12.5px; color: #6b7280; }
    .reasons { display: flex; flex-wrap: wrap; gap: 5px; margin: 10px 0; }
    .reason { padding: 3px 8px; border-radius: 8px; font-size: 11.5px; font-weight: 600; color: #5b21b6; background: #f5f3ff; }
    .price { font-size: 13px; color: #6b7280; }
    .price strong { font-size: 16px; color: #111827; }
  `],
})
export class RecommendationsComponent implements OnInit {
  private readonly discovery = inject(DiscoveryService);

  @Output() open = new EventEmitter<number>();

  protected readonly data = signal<Recommendations | null>(null);
  readonly Sparkles = Sparkles;
  readonly Star = Star;
  readonly MapPin = MapPin;

  ngOnInit(): void {
    this.discovery.recommendations().subscribe({ next: d => this.data.set(d), error: () => this.data.set(null) });
  }
}
