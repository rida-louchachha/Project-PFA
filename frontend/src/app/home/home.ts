import { Component, inject, OnInit, signal } from '@angular/core';
import { CurrencyLabelPipe } from '../settings/currency-label.pipe';
import { SmartSearchComponent } from '../discovery/smart-search/smart-search.component';
import { DestinationsComponent } from '../discovery/destinations/destinations.component';
import { RecommendationsComponent } from '../discovery/recommendations/recommendations.component';
import { SmartSearchResult } from '../discovery/discovery.service';
import { AppSettingsService } from '../settings/app-settings.service';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../auth/auth';
import { HeaderComponent } from '../header/header';
import { AccommodationService, Accommodation, AccommodationFilters, FilterOptions } from '../accommodation/accommodation';
import { ConfirmDeleteDialogComponent } from '../shared/dialogs/confirm-delete/confirm-delete-dialog.component';
import { EditAccommodationDialogComponent } from '../shared/dialogs/edit-accommodation/edit-accommodation-dialog.component';
import { AccommodationCardComponent } from '../shared/accommodation-card/accommodation-card.component';
import { AccommodationDetailsDialogComponent } from '../shared/dialogs/accommodation-details/accommodation-details-dialog.component';
import { BookingDialogComponent } from '../shared/dialogs/booking/booking-dialog.component';
import {
  LucideAngularModule,
  Filter,
  Search,
  Plus
} from 'lucide-angular';

@Component({
  selector: 'app-home',
  standalone: true,
  imports: [CommonModule, FormsModule, HeaderComponent, AccommodationCardComponent, AccommodationDetailsDialogComponent, BookingDialogComponent, LucideAngularModule, ConfirmDeleteDialogComponent, EditAccommodationDialogComponent, CurrencyLabelPipe, SmartSearchComponent, DestinationsComponent, RecommendationsComponent],
  templateUrl: './home.html',
  styleUrl: './home.scss'
})
export class HomeComponent implements OnInit {
  private readonly authService = inject(AuthService);
  private readonly appSettings = inject(AppSettingsService);
  private readonly accommodationService = inject(AccommodationService);

  protected currentUser$ = this.authService.currentUser$;

  // Register Lucide icons
  readonly Filter = Filter;
  readonly Search = Search;
  readonly Plus = Plus;
  protected accommodations = signal<Accommodation[]>([]);
  protected isLoading = signal(false);
  protected filterOptions = signal<FilterOptions['data'] | null>(null);

  // Filter states
  protected filters = signal<AccommodationFilters>({
    per_page: 12,
    sort_by: 'created_at',
    sort_order: 'desc'
  });

  // Search and filter form values
  protected searchQuery = signal('');
  protected selectedType = signal('');
  protected selectedCapacity = signal<number | null>(null);
  protected priceRange = signal({ min: 0, max: 1000 });
  protected showFilters = signal(false);

  // TravelPlan AI smart search: what the AI understood + stay details to pre-fill the booking
  protected aiChips = signal<{ icon: string; label: string }[]>([]);
  protected aiProvider = signal<string>('');
  protected aiStay = signal<{ checkIn: string | null; nights: number | null; guests: number | null } | null>(null);

  // Pagination
  protected currentPage = signal(1);
  protected totalPages = signal(1);
  protected totalItems = signal(0);

  // Dialog state
  protected showDetailsDialog = signal(false);
  protected showBookingDialog = signal(false);
  protected selectedAccommodation = signal<Accommodation | null>(null);
  protected showEditDialog = false;
  protected showDeleteDialog = false;

  ngOnInit() {
    // Load user data and accommodations
    this.authService.getCurrentUser().subscribe({
      error: (error) => {
        console.error('Failed to load user data:', error);
      }
    });

    this.loadFilterOptions();
    this.loadAccommodations();
  }

  protected loadFilterOptions(): void {
    this.accommodationService.getFilterOptions().subscribe({
      next: (response) => {
        if (response.success) {
          this.filterOptions.set(response.data);
          this.priceRange.set({
            min: response.data.price_range.min,
            max: response.data.price_range.max
          });
        }
      },
      error: (error) => {
        console.error('Failed to load filter options:', error);
      }
    });
  }

  protected loadAccommodations(): void {
    this.isLoading.set(true);

    const currentFilters = this.filters();
    const request = this.isAgency()
      ? this.accommodationService.getMyAccommodations(currentFilters)
      : this.accommodationService.getAccommodations(currentFilters);
    request.subscribe({
      next: (response) => {
        if (response.success) {
          this.accommodations.set(response.data.data);
          this.currentPage.set(response.meta.current_page);
          this.totalPages.set(response.meta.last_page);
          this.totalItems.set(response.meta.total);
        }
        this.isLoading.set(false);
      },
      error: (error) => {
        console.error('Failed to load accommodations:', error);
        this.isLoading.set(false);
      }
    });
  }

  protected applyFilters(): void {
    const newFilters: AccommodationFilters = {
      ...this.filters(),
      page: 1,
      search: this.searchQuery() || undefined,
      type: this.selectedType() || undefined,
      capacite: this.selectedCapacity() || undefined,
      min_price: this.priceRange().min || undefined,
      max_price: this.priceRange().max || undefined,
    };

    this.filters.set(newFilters);
    this.loadAccommodations();
  }

  protected hasActiveSearch(): boolean {
    return !!(this.searchQuery() || this.selectedType() || this.selectedCapacity() || this.aiChips().length);
  }

  /** Natural-language search -> filters validated by the backend -> normal MySQL search */
  protected onSmartSearch(result: SmartSearchResult): void {
    const f = result.filters;
    this.aiChips.set(result.chips);
    this.aiProvider.set(result.provider);
    this.aiStay.set({ checkIn: f.date_arrivee, nights: f.nuits, guests: f.nb_personnes });

    this.searchQuery.set(f.ville ?? f.mots_cles[0] ?? '');
    this.selectedType.set(f.type ?? '');
    this.selectedCapacity.set(f.nb_personnes);
    const options = this.filterOptions();
    this.priceRange.set({
      min: options?.price_range.min ?? 0,
      max: f.prix_max ?? options?.price_range.max ?? 100000,
    });
    this.applyFilters();
    this.scrollToResults();
  }

  protected onDestinationPick(city: string): void {
    this.aiChips.set([]);
    this.aiStay.set(null);
    this.searchQuery.set(city);
    this.applyFilters();
    this.scrollToResults();
  }

  protected onOpenRecommendation(id: number): void {
    this.accommodationService.getAccommodation(id).subscribe({
      next: response => {
        if (response?.success && response.data) {
          this.selectedAccommodation.set(response.data);
          this.showDetailsDialog.set(true);
        }
      },
      error: error => console.error('Failed to open recommendation:', error),
    });
  }

  private scrollToResults(): void {
    setTimeout(() => document.querySelector('.results-header')?.scrollIntoView({ behavior: 'smooth', block: 'start' }), 50);
  }

  protected clearFilters(): void {
    this.aiChips.set([]);
    this.aiStay.set(null);
    this.searchQuery.set('');
    this.selectedType.set('');
    this.selectedCapacity.set(null);
    const filterOpts = this.filterOptions();
    if (filterOpts) {
      this.priceRange.set({
        min: filterOpts.price_range.min,
        max: filterOpts.price_range.max
      });
    }

    this.filters.set({
      per_page: 12,
      sort_by: 'created_at',
      sort_order: 'desc'
    });

    this.loadAccommodations();
  }

  protected toggleFilters(): void {
    this.showFilters.set(!this.showFilters());
  }

  protected onSearch(event: Event): void {
    const target = event.target as HTMLInputElement;
    this.searchQuery.set(target.value);
    this.applyFilters();
  }

  protected onTypeChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    this.selectedType.set(target.value);
    this.applyFilters();
  }

  protected onCapacityChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    this.selectedCapacity.set(target.value ? Number(target.value) : null);
    this.applyFilters();
  }

  protected getTypeDisplayName(type: string): string {
    switch (type) {
      case 'hotel': return 'Hôtel';
      case 'maison': return 'Riad / Maison';
      case 'studio': return 'Studio';
      default: return type;
    }
  }

  protected getDefaultImage(): string {
    return 'https://images.unsplash.com/photo-1566073771259-6a8506099945?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1000&q=80';
  }

  protected formatPrice(amount: number, unit: string): string {
    return `${amount} ${this.appSettings.currencyLabel()}/${unit}`;
  }

  protected trackByAccommodation(index: number, accommodation: Accommodation): number {
    return accommodation?.id || index;
  }

  // Event handlers for template
  protected onMinPriceChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const newMin = +target.value;
    this.priceRange.set({
      min: newMin,
      max: this.priceRange().max
    });
  }

  protected onMaxPriceChange(event: Event): void {
    const target = event.target as HTMLInputElement;
    const newMax = +target.value;
    this.priceRange.set({
      min: this.priceRange().min,
      max: newMax
    });
  }

  protected onSortChange(event: Event): void {
    const target = event.target as HTMLSelectElement;
    const sortBy = target.value as 'created_at' | 'tarif_amount' | 'capacite' | 'titre';
    const newFilters: AccommodationFilters = {
      ...this.filters(),
      page: 1,
      sort_by: sortBy,
      sort_order: sortBy === 'tarif_amount' || sortBy === 'titre' ? 'asc' : 'desc'
    };
    this.filters.set(newFilters);
    this.loadAccommodations();
  }

  protected onPreviousPage(): void {
    if (this.currentPage() > 1) {
      const newFilters = {
        ...this.filters(),
        page: this.currentPage() - 1
      };
      this.filters.set(newFilters);
      this.loadAccommodations();
    }
  }

  protected onNextPage(): void {
    if (this.currentPage() < this.totalPages()) {
      const newFilters = {
        ...this.filters(),
        page: this.currentPage() + 1
      };
      this.filters.set(newFilters);
      this.loadAccommodations();
    }
  }


  // Card event handlers
  protected onFavoriteAccommodation(accommodation: Accommodation): void {
    console.log('Favorite clicked:', accommodation.titre);
    // TODO: Implement favorite functionality
  }

  protected onViewAccommodationDetails(accommodation: Accommodation): void {
    console.log('View details clicked:', accommodation.titre);
    this.selectedAccommodation.set(accommodation);
    this.showDetailsDialog.set(true);
  }

  protected onBookAccommodation(accommodation: Accommodation): void {
    console.log('Book now clicked:', accommodation.titre);
    this.selectedAccommodation.set(accommodation);
    this.showBookingDialog.set(true);
  }

  // Dialog event handlers
  protected onCloseDetailsDialog(): void {
    this.showDetailsDialog.set(false);
    this.selectedAccommodation.set(null);
  }

  protected onBookFromDialog(accommodation: Accommodation): void {
    console.log('Booking from dialog:', accommodation.titre);
    this.onCloseDetailsDialog();
    this.selectedAccommodation.set(accommodation);
    this.showBookingDialog.set(true);
  }

  protected onCloseBookingDialog(): void {
    this.showBookingDialog.set(false);
    this.selectedAccommodation.set(null);
  }

  protected onBookingCompleted(event: {reservationId: number, paymentId: number}): void {
    console.log('Booking completed successfully!', event);
    // You could show a success message, redirect to reservations page, etc.
    // For now, we'll just close the dialog
    this.onCloseBookingDialog();
  }

  // Agency-specific methods
  protected isAgency(): boolean {
    const currentUser = this.authService.getCurrentUserSync();
    return currentUser?.role === 'agence';
  }

  protected openCreateForm(): void {
    this.selectedAccommodation.set(null);
    this.showEditDialog = true;
  }

  protected onEditAccommodation(accommodation: Accommodation): void {
    this.selectedAccommodation.set(accommodation);
    this.showEditDialog = true;
  }

  protected onDeleteAccommodation(accommodation: Accommodation): void {
    this.selectedAccommodation.set(accommodation);
    this.showDeleteDialog = true;
  }

  protected onSaveAccommodation(payload: Partial<Accommodation>): void {
    const current = this.selectedAccommodation();
    const done = () => {
      this.showEditDialog = false;
      this.selectedAccommodation.set(null);
      this.updateLoadAccommodationsForAgency();
    };

    if (current) {
      this.accommodationService.updateAccommodation(current.id, payload).subscribe({
        next: (resp) => { if (resp?.success && resp.data) done(); },
        error: (err) => console.error('Failed to update accommodation', err)
      });
    } else {
      this.accommodationService.createAccommodation(payload).subscribe({
        next: (resp) => { if (resp?.success && resp.data) done(); },
        error: (err) => console.error('Failed to create accommodation', err)
      });
    }
  }

  protected onConfirmDelete(): void {
    const current = this.selectedAccommodation();
    if (!current) return;
    this.accommodationService.deleteAccommodation(current.id).subscribe({
      next: (resp) => {
        if (resp?.success) {
          this.showDeleteDialog = false;
          this.selectedAccommodation.set(null);
          this.updateLoadAccommodationsForAgency();
        }
      },
      error: (err) => {
        console.error('Failed to delete accommodation', err);
      }
    });
  }

  // Reload after create / edit / delete (agencies: their own listings via GET /api/agency/logements)
  private updateLoadAccommodationsForAgency(): void {
    this.loadAccommodations();
  }
}
