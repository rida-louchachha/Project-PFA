import { Component, Input, Output, EventEmitter, OnInit, inject } from '@angular/core';
import { CurrencyLabelPipe } from '../../../settings/currency-label.pipe';
import { CommonModule } from '@angular/common';
import { 
  LucideAngularModule, 
  X,
  MapPin, 
  Users, 
  Star, 
  Calendar,
  Banknote,
  Home,
  Building,
  Bed,
  Mail,
  Phone,
  Info
} from 'lucide-angular';
import { Accommodation } from '../../../accommodation/accommodation';

@Component({
  selector: 'app-accommodation-details-dialog',
  standalone: true,
  imports: [CommonModule, LucideAngularModule, CurrencyLabelPipe],
  templateUrl: './accommodation-details-dialog.component.html',
  styleUrl: './accommodation-details-dialog.component.scss'
})
export class AccommodationDetailsDialogComponent implements OnInit {
  @Input({ required: true }) accommodation!: Accommodation;
  @Input() isOpen: boolean = false;
  @Output() closeDialog = new EventEmitter<void>();
  @Output() bookNow = new EventEmitter<Accommodation>();

  // Register Lucide icons
  readonly X = X;
  readonly MapPin = MapPin;
  readonly Users = Users;
  readonly Star = Star;
  readonly Calendar = Calendar;
  readonly Banknote = Banknote;
  readonly Home = Home;
  readonly Building = Building;
  readonly Bed = Bed;
  readonly Mail = Mail;
  readonly Phone = Phone;
  readonly Info = Info;

  ngOnInit(): void {
    // Handle escape key to close dialog
    if (this.isOpen) {
      document.addEventListener('keydown', this.handleEscapeKey.bind(this));
    }
  }

  ngOnDestroy(): void {
    document.removeEventListener('keydown', this.handleEscapeKey.bind(this));
  }

  private handleEscapeKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      this.onClose();
    }
  }

  protected onClose(): void {
    this.closeDialog.emit();
  }

  protected onBackdropClick(event: MouseEvent): void {
    // Close dialog when clicking on backdrop (not the dialog content)
    if (event.target === event.currentTarget) {
      this.onClose();
    }
  }

  protected onBookNow(): void {
    this.bookNow.emit(this.accommodation);
    this.onClose();
  }

  protected getTypeDisplayName(type: string): string {
    switch (type) {
      case 'hotel':
        return 'Hotel';
      case 'maison':
        return 'House';
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

  protected onImageError(event: any): void {
    console.warn('Image failed to load:', event.target.src);
    event.target.src = this.getDefaultImage();
  }

  protected getFormattedRating(rating: string | number | undefined): string {
    if (!rating) return '0.0';
    const numRating = typeof rating === 'string' ? parseFloat(rating) : rating;
    return numRating.toFixed(1);
  }

  protected getStatusDisplayName(status: string): string {
    return status === 'actif' ? 'Available' : 'Unavailable';
  }

  protected getStatusClass(status: string): string {
    return status === 'actif' ? 'status-available' : 'status-unavailable';
  }
}
