import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { LucideAngularModule, Calendar, MapPin, Users, Building, Home, Bed, Star, Sparkles } from 'lucide-angular';
import { ReservationResponse } from '../../booking/booking';
import { Accommodation } from '../../accommodation/accommodation';
import { IntentionBadgeComponent } from '../../assistant/intention-badge/intention-badge.component';

@Component({
  selector: 'app-reservation-card',
  standalone: true,
  imports: [CommonModule, RouterModule, LucideAngularModule, IntentionBadgeComponent],
  templateUrl: './reservation-card.component.html',
  styleUrl: './reservation-card.component.scss'
})
export class ReservationCardComponent {
  @Input({ required: true }) reservation!: ReservationResponse;
  @Input() accommodation?: Accommodation | null;
  @Output() addReview = new EventEmitter<ReservationResponse>();

  readonly Calendar = Calendar;
  readonly MapPin = MapPin;
  readonly Users = Users;
  readonly Building = Building;
  readonly Home = Home;
  readonly Bed = Bed;
  readonly Star = Star;
  readonly Sparkles = Sparkles;

  /** Show the arrival-intention badge only for upcoming confirmed stays */
  protected showIntention(): boolean {
    return this.reservation.statut === 'confirme'
      && !!this.reservation.confirmation
      && new Date(this.reservation.date_fin).getTime() >= Date.now();
  }

  protected getAccommodationTitle(): string {
    return this.accommodation?.titre || this.reservation.logement?.titre || 'Unknown Accommodation';
  }

  protected getAccommodationAddress(): string {
    return this.accommodation?.adresse || this.reservation.logement?.adresse || 'Address not available';
  }

  protected getAgencyName(): string | null {
    return this.accommodation?.agence?.nom || this.reservation.logement?.agence?.nom || null;
  }

  protected getPhotoUrl(): string {
    const url = this.accommodation?.photo_url || this.reservation.logement?.photo_url;
    return url || this.getDefaultImage();
  }

  protected getDefaultImage(): string {
    return 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=60';
  }

  protected onImageError(event: any): void {
    event.target.src = this.getDefaultImage();
  }

  protected getTypeDisplayName(): string {
    const type = (this.accommodation?.type || this.reservation.logement?.type || 'maison');
    switch (type) {
      case 'hotel': return 'Hotel';
      case 'maison': return 'House';
      case 'studio': return 'Studio';
      default: return String(type);
    }
  }

  protected getAccommodationIcon(): any {
    const type = (this.accommodation?.type || this.reservation.logement?.type || 'maison');
    switch (type) {
      case 'hotel': return Building;
      case 'maison': return Home;
      case 'studio': return Bed;
      default: return Home;
    }
  }

  protected formatDate(value: string): string {
    const d = new Date(value);
    if (isNaN(d.getTime())) return value;
    return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
  }

  protected calculateNights(start: string, end: string): number {
    const s = new Date(start).getTime();
    const e = new Date(end).getTime();
    if (isNaN(s) || isNaN(e)) return 0;
    const msPerNight = 1000 * 60 * 60 * 24;
    return Math.max(1, Math.round((e - s) / msPerNight));
  }

  protected getStatusText(): string {
    switch (this.reservation.statut) {
      case 'en_attente': return 'Pending';
      case 'confirme': return 'Confirmed';
      case 'annule': return 'Cancelled';
      default: return this.reservation.statut;
    }
  }
}


