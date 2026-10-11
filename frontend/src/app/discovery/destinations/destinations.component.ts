import { Component, EventEmitter, OnInit, Output, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { LucideAngularModule, MapPin } from 'lucide-angular';
import { Destination, DiscoveryService } from '../discovery.service';

/** "Destinations populaires": cities with listings, count and starting price (from MySQL). */
@Component({
  selector: 'app-destinations',
  standalone: true,
  imports: [CommonModule, LucideAngularModule],
  template: `
    <section class="destinations" *ngIf="destinations().length">
      <div class="head">
        <h2>Destinations populaires</h2>
        <p>Du désert de Merzouga aux plages de Taghazout, choisissez votre ville.</p>
      </div>
      <div class="strip">
        <button type="button" class="dest" *ngFor="let d of destinations()" (click)="pick.emit(d.ville)"
                [style.background-image]="d.photo_url ? 'url(' + d.photo_url + ')' : null">
          <span class="shade"></span>
          <span class="content">
            <span class="city"><lucide-icon [img]="MapPin" size="14"></lucide-icon>{{ d.ville }}</span>
            <span class="meta">{{ d.logements }} logement{{ d.logements > 1 ? 's' : '' }} · dès {{ d.prix_min | number:'1.0-0':'fr' }} {{ d.devise }}</span>
          </span>
        </button>
      </div>
    </section>
  `,
  styles: [`
    .destinations { margin: 0 0 28px; }
    .head h2 { margin: 0; font-size: 22px; font-weight: 800; color: #111827; }
    .head p { margin: 4px 0 14px; color: #6b7280; font-size: 14px; }
    .strip { display: flex; gap: 14px; overflow-x: auto; padding: 4px 2px 12px; scroll-snap-type: x mandatory; }
    .dest {
      position: relative; flex: 0 0 190px; height: 128px; border: none; border-radius: 18px; cursor: pointer;
      overflow: hidden; scroll-snap-align: start; padding: 0; text-align: left;
      background: linear-gradient(135deg, #667eea, #764ba2) center/cover no-repeat;
      box-shadow: 0 8px 20px rgba(17, 24, 39, 0.12); transition: transform .2s ease, box-shadow .2s ease;
    }
    .dest:hover { transform: translateY(-3px); box-shadow: 0 14px 28px rgba(17, 24, 39, 0.18); }
    .shade { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(17,24,39,0.05) 30%, rgba(17,24,39,0.78)); }
    .content { position: absolute; left: 14px; right: 14px; bottom: 12px; display: flex; flex-direction: column; gap: 2px; color: white; }
    .city { display: inline-flex; align-items: center; gap: 4px; font-size: 17px; font-weight: 800; }
    .meta { font-size: 12px; opacity: .9; }
  `],
})
export class DestinationsComponent implements OnInit {
  private readonly discovery = inject(DiscoveryService);

  @Output() pick = new EventEmitter<string>();

  protected readonly destinations = signal<Destination[]>([]);
  readonly MapPin = MapPin;

  ngOnInit(): void {
    this.discovery.destinations().subscribe({ next: d => this.destinations.set(d), error: () => this.destinations.set([]) });
  }
}
