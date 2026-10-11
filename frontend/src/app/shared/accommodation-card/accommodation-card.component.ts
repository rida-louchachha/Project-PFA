import { Component, Input, Output, EventEmitter } from '@angular/core';
import { CurrencyLabelPipe } from '../../settings/currency-label.pipe';
import { CommonModule } from '@angular/common';
import { 
  LucideAngularModule, 
  MapPin, 
  Users, 
  Star, 
  Heart, 
  Eye, 
  Calendar, 
  Home, 
  Building, 
  Bed,
  Banknote,
  Edit,
  Trash2
} from 'lucide-angular';
import { Accommodation } from '../../accommodation/accommodation';

export interface AccommodationCardActions {
  onFavorite: (accommodation: Accommodation) => void;
  onViewDetails: (accommodation: Accommodation) => void;
  onBookNow: (accommodation: Accommodation) => void;
}

@Component({
  selector: 'app-accommodation-card',
  standalone: true,
  imports: [CommonModule, LucideAngularModule, CurrencyLabelPipe],
  templateUrl: './accommodation-card.component.html',
  styleUrl: './accommodation-card.component.scss'
})
export class AccommodationCardComponent {
  @Input({ required: true }) accommodation!: Accommodation;
  @Input() showActions: boolean = true;
  @Input() compactMode: boolean = false;
  @Input() isAgencyView: boolean = false;

  @Output() favorite = new EventEmitter<Accommodation>();
  @Output() viewDetails = new EventEmitter<Accommodation>();
  @Output() bookNow = new EventEmitter<Accommodation>();
  @Output() edit = new EventEmitter<Accommodation>();
  @Output() delete = new EventEmitter<Accommodation>();

  // Register Lucide icons
  readonly MapPin = MapPin;
  readonly Users = Users;
  readonly Star = Star;
  readonly Heart = Heart;
  readonly Eye = Eye;
  readonly Calendar = Calendar;
  readonly Home = Home;
  readonly Building = Building;
  readonly Bed = Bed;
  readonly Banknote = Banknote;
  readonly Edit = Edit;
  readonly Trash2 = Trash2;

  protected getTypeDisplayName(type: string): string {
    switch (type) {
      case 'hotel':
        return 'Hôtel';
      case 'maison':
        return 'Riad / Maison';
      case 'studio':
        return 'Studio';
      default:
        return type;
    }
  }

  protected getAccommodationIcon(type: string): any {
    switch (type) {
      case 'hotel':
        return Building;
      case 'maison':
        return Home;
      case 'studio':
        return Bed;
      default:
        return Home;
    }
  }

  protected getDefaultImage(): string {
    return 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=60';
  }

  protected getDescriptionPreview(description: string | null | undefined): string {
    if (!description) return '';
    return description.length > 120 ? description.substring(0, 120) + '...' : description;
  }

  protected getFormattedRating(rating: string | number | undefined): string {
    if (!rating) return '0.0';
    const numRating = typeof rating === 'string' ? parseFloat(rating) : rating;
    return numRating.toFixed(1);
  }

  protected onFavoriteClick(): void {
    this.favorite.emit(this.accommodation);
  }

  protected onViewDetailsClick(): void {
    this.viewDetails.emit(this.accommodation);
  }

  protected onBookNowClick(): void {
    this.bookNow.emit(this.accommodation);
  }

  protected onEditClick(): void {
    this.edit.emit(this.accommodation);
  }

  protected onDeleteClick(): void {
    this.delete.emit(this.accommodation);
  }

  protected onImageError(event: any): void {
    console.warn('Image failed to load:', event.target.src);
    event.target.src = this.getDefaultImage();
  }
}
