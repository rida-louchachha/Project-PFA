import { Component, OnInit, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule, LUCIDE_ICONS, LucideIconProvider, Calendar, MapPin, Users, DollarSign, Clock, CheckCircle, XCircle, AlertCircle, ArrowLeft, Filter, Search, Eye, X, Building, Home, Bed, ChevronLeft, ChevronRight } from 'lucide-angular';
import { AuthService } from '../auth/auth';
import { BookingService, ReservationResponse } from '../booking/booking';
import { AccommodationService, Accommodation } from '../accommodation/accommodation';
import { HeaderComponent } from '../header/header';
import { AccommodationCardComponent } from '../shared/accommodation-card/accommodation-card.component';
import { ReservationCardComponent } from '../shared/reservation-card/reservation-card.component';
import { ReviewDialogComponent } from '../shared/dialogs/review/review-dialog.component';
import { ReviewService } from '../reviews/review';
import { AppSettingsService } from '../settings/app-settings.service';

@Component({
  selector: 'app-reservations',
  standalone: true,
  // Icons referenced by name in the template (<lucide-icon name="...">) must be registered
  providers: [
    { provide: LUCIDE_ICONS, multi: true, useValue: new LucideIconProvider({ ArrowLeft, Clock, CheckCircle, XCircle, Search }) },
  ],
  imports: [
    CommonModule,
    RouterModule,
    FormsModule,
    ReactiveFormsModule,
    LucideAngularModule,
    HeaderComponent,
    AccommodationCardComponent,
    ReservationCardComponent,
    ReviewDialogComponent
  ],
  template: `
    <div class="home-container">
      <!-- Header Component (same as home page) -->
      <app-header></app-header>

      <!-- Main Content -->
      <main class="main-content">
        <!-- Hero Section -->
        <section class="hero-section">
          <div class="hero-content">
            <div class="hero-header">
              <a routerLink="/home" class="back-button">
                <lucide-icon name="arrowLeft" size="16"></lucide-icon>
                <span>Back to Home</span>
              </a>
              <div class="hero-title-section">
                <h1 class="hero-title">My Reservations</h1>
                <p class="hero-subtitle">Manage your accommodation bookings</p>
              </div>
            </div>

            <!-- Stats Section -->
            <div class="stats-section" *ngIf="!isLoading() && reservations().length > 0">
              <div class="stat-card" *ngIf="getStatusCount('en_attente') > 0">
                <div class="stat-icon pending">
                  <lucide-icon name="clock" size="20"></lucide-icon>
                </div>
                <div class="stat-info">
                  <div class="stat-value">{{ getStatusCount('en_attente') }}</div>
                  <div class="stat-label">Pending</div>
                </div>
              </div>
              <div class="stat-card" *ngIf="getStatusCount('confirme') > 0">
                <div class="stat-icon confirmed">
                  <lucide-icon name="checkCircle" size="20"></lucide-icon>
                </div>
                <div class="stat-info">
                  <div class="stat-value">{{ getStatusCount('confirme') }}</div>
                  <div class="stat-label">Confirmed</div>
                </div>
              </div>
              <div class="stat-card" *ngIf="getStatusCount('annule') > 0">
                <div class="stat-icon cancelled">
                  <lucide-icon name="xCircle" size="20"></lucide-icon>
                </div>
                <div class="stat-info">
                  <div class="stat-value">{{ getStatusCount('annule') }}</div>
                  <div class="stat-label">Cancelled</div>
                </div>
              </div>
            </div>

            <!-- Search and Filters -->
            <div class="search-section" *ngIf="!isLoading()">
              <div class="search-bar">
                <div class="search-field">
                  <label for="search">
                    <lucide-icon name="search" size="16"></lucide-icon>
                    Search
                  </label>
                  <input
                    id="search"
                    type="text"
                    [(ngModel)]="searchTerm"
                    (input)="applyFilters()"
                    placeholder="Search reservations...">
                </div>

                <div class="search-field">
                  <label for="dayFilter">Day Filter</label>
                  <select id="dayFilter" [(ngModel)]="selectedDay" (change)="applyFilters()">
                    <option value="">All Days</option>
                    <option value="monday">Monday</option>
                    <option value="tuesday">Tuesday</option>
                    <option value="wednesday">Wednesday</option>
                    <option value="thursday">Thursday</option>
                    <option value="friday">Friday</option>
                    <option value="saturday">Saturday</option>
                    <option value="sunday">Sunday</option>
                  </select>
                </div>

                <button
                  class="filter-toggle"
                  [class.active]="statusFilter === ''"
                  (click)="setStatusFilter('')">
                  All
                </button>
                <button
                  class="filter-toggle"
                  [class.active]="statusFilter === 'en_attente'"
                  (click)="setStatusFilter('en_attente')">
                  <lucide-icon name="clock" size="16"></lucide-icon>
                  Pending
                </button>
                <button
                  class="filter-toggle"
                  [class.active]="statusFilter === 'confirme'"
                  (click)="setStatusFilter('confirme')">
                  <lucide-icon name="checkCircle" size="16"></lucide-icon>
                  Confirmed
                </button>
                <button
                  class="filter-toggle"
                  [class.active]="statusFilter === 'annule'"
                  (click)="setStatusFilter('annule')">
                  <lucide-icon name="xCircle" size="16"></lucide-icon>
                  Cancelled
                </button>
              </div>
            </div>
          </div>
        </section>

        <!-- Results Section -->
        <section class="results-section">
          <div class="results-header">
            <div class="results-info">
              <h2>Your Reservations</h2>
              <p *ngIf="filteredReservations().length > 0">{{ filteredReservations().length }} reservation(s) found</p>
              <p *ngIf="filteredReservations().length === 0 && !isLoading()">No reservations found</p>
            </div>
          </div>

          <!-- Loading State -->
          <div class="loading-container" *ngIf="isLoading()">
            <div class="loading-spinner"></div>
            <p>Loading your reservations...</p>
          </div>

          <!-- No Results -->
          <div class="no-results" *ngIf="!isLoading() && filteredReservations().length === 0">
            <div class="no-results-icon">📅</div>
            <h3 *ngIf="!searchTerm && !statusFilter">No reservations yet</h3>
            <h3 *ngIf="searchTerm || statusFilter">No reservations found</h3>
            <p *ngIf="!searchTerm && !statusFilter">You haven't made any reservations yet</p>
            <p *ngIf="searchTerm || statusFilter">Try adjusting your search criteria</p>
            <a routerLink="/home" class="btn-primary">
              <lucide-icon name="search" size="16"></lucide-icon>
              Browse Accommodations
            </a>
          </div>

          <!-- Reservations Grid -->
          <div class="accommodation-grid" *ngIf="!isLoading() && filteredReservations().length > 0">
            <app-reservation-card
              *ngFor="let reservation of filteredReservations(); trackBy: trackByReservation"
              [reservation]="reservation"
              [accommodation]="accommodations().get(reservation.id_logement) ?? null"
              (addReview)="onOpenReviewDialog($event)">
            </app-reservation-card>
          </div>

          <!-- Pagination -->
          <div class="pagination" *ngIf="totalPages > 1">
            <button
              class="page-btn"
              [disabled]="currentPage === 1"
              (click)="goToPage(currentPage - 1)">
              Previous
            </button>

            <div class="page-numbers">
              <span class="page-info">
                Page {{ currentPage }} of {{ totalPages }}
              </span>
            </div>

            <button
              class="page-btn"
              [disabled]="currentPage === totalPages"
              (click)="goToPage(currentPage + 1)">
              Next
            </button>
          </div>
        </section>
      </main>

      <!-- Review Dialog -->
      <app-review-dialog
        *ngIf="showReviewDialog"
        [isOpen]="showReviewDialog"
        [agencyName]="reviewAgencyName"
        (close)="onCloseReviewDialog()"
        (submit)="onSubmitReview($event)">
      </app-review-dialog>
    </div>
  `,
  styles: [`
    // Use same base styles as home page
    .home-container {
      min-height: 100vh;
      background: #f8fafc;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    .main-content {
      padding-top: 0;
    }

    // Hero Section (adapted for reservations)
    .hero-section {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 80px 0 100px;
      position: relative;
      overflow: hidden;

      &::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="rgba(255,255,255,.1)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></radialGradient></defs><rect width="11" height="11" rx="2" ry="2" fill="url(%23a)"/></svg>') repeat;
        opacity: 0.3;
      }

      .hero-content {
        max-width: 1200px;
        margin: 0 auto;
        padding: 0 2rem;
        position: relative;
        z-index: 2;
      }

      .hero-header {
        display: flex;
        align-items: center;
        gap: 2rem;
        margin-bottom: 2rem;

        @media (max-width: 768px) {
          flex-direction: column;
          gap: 1rem;
          align-items: flex-start;
        }
      }

      .back-button {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        font-weight: 500;
        padding: 0.5rem 1rem;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 8px;
        transition: all 0.2s ease;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);

        &:hover {
          background: rgba(255, 255, 255, 0.2);
          border-color: rgba(255, 255, 255, 0.3);
        }
      }

      .hero-title-section {
        flex: 1;

        .hero-title {
          font-size: 3.5rem;
          font-weight: 700;
          margin-bottom: 1rem;
          text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);

          @media (max-width: 768px) {
            font-size: 2.5rem;
          }
        }

        .hero-subtitle {
          font-size: 1.25rem;
          opacity: 0.9;
          font-weight: 300;
          margin: 0;
        }
      }
    }

    // Stats Section
    .stats-section {
      display: flex;
      gap: 1rem;
      margin-bottom: 2rem;
      flex-wrap: wrap;

      @media (max-width: 768px) {
        justify-content: center;
      }
    }

    .stat-card {
      display: flex;
      align-items: center;
      gap: 1rem;
      padding: 1rem 1.5rem;
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 12px;
      transition: transform 0.2s ease;

      &:hover {
        transform: translateY(-2px);
      }

      .stat-icon {
        width: 2.5rem;
        height: 2.5rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;

        &.pending {
          background: rgba(245, 158, 11, 0.9);
          color: white;
        }

        &.confirmed {
          background: rgba(16, 185, 129, 0.9);
          color: white;
        }

        &.cancelled {
          background: rgba(239, 68, 68, 0.9);
          color: white;
        }
      }

      .stat-info {
        .stat-value {
          font-size: 1.5rem;
          font-weight: 700;
          color: white;
          margin: 0;
        }

        .stat-label {
          font-size: 0.875rem;
          color: rgba(255, 255, 255, 0.8);
          margin: 0;
        }
      }
    }

    // Search Section (adapted for filters)
    .search-section {
      background: white;
      border-radius: 16px;
      padding: 2rem;
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
      margin: 0 auto;
      max-width: 900px;
    }

    .search-bar {
      display: grid;
      grid-template-columns: 2fr 1fr repeat(4, auto);
      gap: 1rem;
      align-items: end;

      @media (max-width: 768px) {
        grid-template-columns: 1fr;
        gap: 1rem;
      }
    }

    .search-field {
      display: flex;
      flex-direction: column;

      label {
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      input, select {
        padding: 0.75rem 1rem;
        border: 2px solid #e5e7eb;
        border-radius: 8px;
        font-size: 1rem;
        transition: all 0.2s ease;
        background: white;

        &:focus {
          outline: none;
          border-color: #3b82f6;
          box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        &::placeholder {
          color: #9ca3af;
        }
      }

      select {
        cursor: pointer;
      }
    }

    .filter-toggle {
      padding: 0.75rem 1.5rem;
      background: #f3f4f6;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      font-weight: 600;
      color: #374151;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      font-size: 0.875rem;

      &:hover {
        background: #e5e7eb;
      }

      &.active {
        background: #3b82f6;
        color: white;
        border-color: #3b82f6;
      }
    }

    // Results Section
    .results-section {
      max-width: 1200px;
      margin: -50px auto 0;
      padding: 0 2rem 4rem;
      position: relative;
      z-index: 10;
    }

    .results-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      background: white;
      padding: 1.5rem;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);

      @media (max-width: 768px) {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
      }

      .results-info {
        h2 {
          font-size: 1.875rem;
          font-weight: 700;
          color: #1f2937;
          margin-bottom: 0.25rem;
        }

        p {
          color: #6b7280;
          font-size: 0.875rem;
          margin: 0;
        }
      }
    }

    // Loading & No Results (same as home)
    .loading-container {
      text-align: center;
      padding: 4rem 2rem;

      .loading-spinner {
        width: 40px;
        height: 40px;
        border: 4px solid #f3f4f6;
        border-top: 4px solid #3b82f6;
        border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto 1rem;
      }

      p {
        color: #6b7280;
        font-size: 1rem;
      }
    }

    .no-results {
      text-align: center;
      padding: 4rem 2rem;
      background: white;
      border-radius: 12px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);

      .no-results-icon {
        font-size: 4rem;
        margin-bottom: 1rem;
      }

      h3 {
        font-size: 1.5rem;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 0.5rem;
      }

      p {
        color: #6b7280;
        margin-bottom: 2rem;
      }
    }

    // Reservation Cards (using accommodation card component)
    .accommodation-grid {
      display: grid;
      gap: 2rem;
      grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));

      @media (max-width: 768px) {
        grid-template-columns: 1fr;
        gap: 1.5rem;
      }
    }

    .reservation-wrapper {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      transition: all 0.3s ease;
      position: relative;

      &:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
      }
    }

    .reservation-status-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 1.5rem;
      background: #f9fafb;
      border-bottom: 1px solid #e5e7eb;
    }

    .reservation-details {
      padding: 1.5rem;
      background: #f9fafb;
      border-top: 1px solid #e5e7eb;
    }

    .reservation-actions {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 1rem;
      padding-top: 1rem;
      border-top: 1px solid #e5e7eb;

      @media (max-width: 768px) {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
      }
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 1.5rem;
      background: #f9fafb;
      border-bottom: 1px solid #e5e7eb;
    }

    .status-badge {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.25rem 0.75rem;
      border-radius: 20px;
      font-size: 0.875rem;
      font-weight: 600;

      &.status-en_attente {
        background: #fef3c7;
        color: #92400e;
      }

      &.status-confirme {
        background: #d1fae5;
        color: #065f46;
      }

      &.status-annule {
        background: #fee2e2;
        color: #991b1b;
      }
    }

    .reservation-id {
      text-align: right;

      .id-label {
        font-size: 0.75rem;
        color: #9ca3af;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: block;
      }

      .id-value {
        font-size: 0.875rem;
        font-weight: 600;
        color: #374151;
      }
    }

    .accommodation-info {
      display: flex;
      gap: 1rem;
      padding: 1.5rem;
    }

    .accommodation-image {
      width: 80px;
      height: 80px;
      border-radius: 8px;
      overflow: hidden;
      flex-shrink: 0;
      position: relative;

      img {
        width: 100%;
        height: 100%;
        object-fit: cover;
      }

      .image-overlay {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        background: linear-gradient(transparent, rgba(0, 0, 0, 0.7));
        padding: 0.5rem;

        .type-badge {
          display: flex;
          align-items: center;
          gap: 0.25rem;
          color: white;
          font-size: 0.75rem;
          font-weight: 600;
        }
      }
    }

    .accommodation-details {
      flex: 1;
      min-width: 0;

      .accommodation-title {
        font-size: 1.125rem;
        font-weight: 600;
        color: #111827;
        margin: 0 0 0.25rem 0;
        line-height: 1.4;
      }

      .accommodation-agency {
        margin-bottom: 0.5rem;

        .agency-label {
          color: #6b7280;
          font-size: 0.875rem;
        }

        .agency-name {
          color: #3b82f6;
          font-weight: 500;
        }
      }

      .accommodation-location {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6b7280;
        font-size: 0.875rem;
      }
    }

    .booking-details {
      padding: 0 1.5rem 1rem;
    }

    .booking-dates {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1rem;
      padding: 1rem;
      background: #f9fafb;
      border-radius: 8px;

      @media (max-width: 768px) {
        flex-direction: column;
        text-align: center;
      }

      .date-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;

        .date-content {
          .date-label {
            font-size: 0.75rem;
            color: #9ca3af;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: block;
          }

          .date-value {
            font-size: 0.875rem;
            color: #374151;
            font-weight: 500;
          }
        }
      }

      .date-divider {
        text-align: center;

        .nights-count {
          font-size: 0.75rem;
          color: #6b7280;
          font-weight: 500;
        }
      }
    }

    .booking-meta {
      display: flex;
      gap: 1rem;

      @media (max-width: 768px) {
        flex-direction: column;
      }

      .meta-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #6b7280;
        font-size: 0.875rem;
      }
    }

    .card-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1rem 1.5rem;
      background: #f9fafb;
      border-top: 1px solid #e5e7eb;

      @media (max-width: 768px) {
        flex-direction: column;
        gap: 1rem;
        align-items: stretch;
      }

      .price-info {
        .price-amount {
          display: block;
          font-size: 1.25rem;
          font-weight: 700;
          color: #111827;
        }

        .price-label {
          font-size: 0.75rem;
          color: #6b7280;
          text-transform: uppercase;
          letter-spacing: 0.05em;
        }
      }

      .card-actions {
        display: flex;
        gap: 0.5rem;
      }
    }

    // Buttons (same as home page)
    .btn-primary {
      background: #3b82f6;
      color: white;
      border: none;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      font-size: 0.875rem;

      &:hover {
        background: #2563eb;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
      }

      &.btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.8125rem;
      }
    }

    .btn-secondary {
      background: white;
      color: #374151;
      border: 1px solid #d1d5db;
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s ease;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
      font-size: 0.875rem;

      &:hover {
        background: #f9fafb;
        border-color: #9ca3af;
      }

      &.btn-sm {
        padding: 0.375rem 0.75rem;
        font-size: 0.8125rem;
      }
    }

    // Pagination (same as home page)
    .pagination {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 3rem;
      background: white;
      padding: 1rem 1.5rem;
      border-radius: 8px;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);

      @media (max-width: 768px) {
        justify-content: center;
        gap: 1rem;
      }

      .page-btn {
        padding: 0.5rem 1rem;
        background: white;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        color: #374151;
        cursor: pointer;
        transition: all 0.2s ease;
        font-weight: 500;

        &:hover:not(:disabled) {
          background: #f9fafb;
          border-color: #9ca3af;
        }

        &:disabled {
          opacity: 0.5;
          cursor: not-allowed;
        }
      }

      .page-numbers {
        .page-info {
          color: #6b7280;
          font-weight: 500;
        }
      }
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  `]
})
export class ReservationsComponent implements OnInit {
  // Signals
  reservations = signal<ReservationResponse[]>([]);
  filteredReservations = signal<ReservationResponse[]>([]);
  accommodations = signal<Map<number, Accommodation>>(new Map());
  isLoading = signal(true);
  searchTerm = '';
  statusFilter = '';
  selectedDay = ''; // Day filter - all days selected by default

  // Pagination
  currentPage = 1;
  totalPages = 1;
  itemsPerPage = 6;

  constructor(
    private authService: AuthService,
    private bookingService: BookingService,
    private accommodationService: AccommodationService,
    private reviewService: ReviewService,
    private appSettings: AppSettingsService
  ) {}

  ngOnInit(): void {
    this.loadReservations();
  }

  // Review dialog state
  protected showReviewDialog = false;
  protected reviewTarget: ReservationResponse | null = null;
  protected reviewAgencyName: string = '';

  protected onOpenReviewDialog(reservation: ReservationResponse): void {
    this.reviewTarget = reservation;
    this.reviewAgencyName = reservation.logement?.agence?.nom || '';
    this.showReviewDialog = true;
  }

  protected onCloseReviewDialog(): void {
    this.showReviewDialog = false;
    this.reviewTarget = null;
    this.reviewAgencyName = '';
  }

  protected onSubmitReview(payload: { rating: number; comment: string }): void {
    if (!this.reviewTarget) return;

    const trySubmit = (agencyId: number) => {
      this.reviewService.submitReview({ id_agence: agencyId, note: payload.rating, commentaire: payload.comment })
        .subscribe({
          next: () => {
            // Optionally refresh anything if needed
            this.onCloseReviewDialog();
            // Minimal feedback
            console.log('Review submitted successfully');
          },
          error: (err) => {
            console.error('Review submit failed', err);
          }
        });
    };

    // Prefer agency id from preloaded accommodation
    const preloaded = this.accommodations().get(this.reviewTarget.id_logement);
    if (preloaded?.agence?.id) {
      trySubmit(preloaded.agence.id);
      return;
    }

    // Fallback: fetch accommodation to get agency id before submission
    this.accommodationService.getAccommodation(this.reviewTarget.id_logement).subscribe({
      next: (resp) => {
        const agencyId = resp?.data?.agence?.id || 0;
        if (agencyId) {
          trySubmit(agencyId);
        } else {
          console.error('Agency id not found for accommodation');
        }
      },
      error: (err) => {
        console.error('Failed to load accommodation for review', err);
      }
    });
  }
  async loadReservations(): Promise<void> {
    this.isLoading.set(true);

    try {
      const reservations = await this.bookingService.getUserReservations().toPromise();
      console.log('Loaded reservations:', reservations);
      this.reservations.set(reservations || []);

      // Fetch accommodation data for each reservation
      await this.loadAccommodationData(reservations || []);

      this.currentPage = 1;
      this.applyPagination();
    } catch (error) {
      console.error('Error loading reservations:', error);
      this.reservations.set([]);
      this.filteredReservations.set([]);
    } finally {
      this.isLoading.set(false);
    }
  }

  private async loadAccommodationData(reservations: ReservationResponse[]): Promise<void> {
    const accommodationMap = new Map<number, Accommodation>();

    // Get unique accommodation IDs
    const accommodationIds = [...new Set(reservations.map(r => r.id_logement))];

    // Fetch accommodation data for each unique ID
    const promises = accommodationIds.map(async (id) => {
      try {
        const response = await this.accommodationService.getAccommodation(id).toPromise();
        if (response?.success && response.data) {
          accommodationMap.set(id, response.data);
        }
      } catch (error) {
        console.error(`Error loading accommodation ${id}:`, error);
      }
    });

    await Promise.all(promises);
    this.accommodations.set(accommodationMap);
  }


  trackByReservation(index: number, reservation: ReservationResponse): number {
    return reservation.id_reservation;
  }

  getStatusIcon(status: string): string {
    switch (status) {
      case 'en_attente': return 'clock';
      case 'confirme': return 'checkCircle';
      case 'annule': return 'xCircle';
      default: return 'alertCircle';
    }
  }

  getStatusText(status: string): string {
    switch (status) {
      case 'en_attente': return 'Pending';
      case 'confirme': return 'Confirmed';
      case 'annule': return 'Cancelled';
      default: return 'Unknown';
    }
  }

  getTypeDisplayName(type: string | undefined): string {
    if (!type) return 'Unknown';
    return type.charAt(0).toUpperCase() + type.slice(1);
  }

  getAccommodationImage(logement: any): string {
    return logement?.photo_url || 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=60';
  }

  onImageError(event: any): void {
    event.target.src = 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=60';
  }

  formatDate(dateString: string): string {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  }

  formatPrice(amount: number): string {
    return this.appSettings.formatAmount(amount);
  }

  async cancelReservation(reservationId: number): Promise<void> {
    if (!confirm('Are you sure you want to cancel this reservation?')) {
      return;
    }

    try {
      await this.bookingService.cancelReservation(reservationId).toPromise();
      await this.loadReservations(); // Reload the list
    } catch (error) {
      console.error('Error cancelling reservation:', error);
      alert('Failed to cancel reservation. Please try again.');
    }
  }

  getStatusCount(status: string): number {
    return this.reservations().filter(reservation => reservation.statut === status).length;
  }

  setStatusFilter(status: string): void {
    this.statusFilter = status;
    this.currentPage = 1;
    this.applyFilters();
  }

  getAccommodationTypeIcon(type: string | undefined): string {
    switch (type) {
      case 'hotel': return 'building';
      case 'maison': return 'home';
      case 'studio': return 'bed';
      default: return 'home';
    }
  }

  calculateNights(checkIn: string, checkOut: string): number {
    const start = new Date(checkIn);
    const end = new Date(checkOut);
    const timeDiff = end.getTime() - start.getTime();
    return Math.ceil(timeDiff / (1000 * 3600 * 24));
  }

  viewReservationDetails(reservation: ReservationResponse): void {
    // This could open a detailed modal or navigate to a details page
    console.log('View details for reservation:', reservation.id_reservation);
    // For now, just log - you could implement a details modal here
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages) return;
    this.currentPage = page;
    this.applyPagination();
  }

  private applyPagination(): void {
    const startIndex = (this.currentPage - 1) * this.itemsPerPage;
    const endIndex = startIndex + this.itemsPerPage;
    const allFiltered = this.getFilteredReservations();
    this.filteredReservations.set(allFiltered.slice(startIndex, endIndex));
    this.totalPages = Math.ceil(allFiltered.length / this.itemsPerPage);
  }

  private getFilteredReservations(): ReservationResponse[] {
    let filtered = [...this.reservations()];

    if (this.searchTerm) {
      const term = this.searchTerm.toLowerCase();
      filtered = filtered.filter(reservation =>
        reservation.logement?.titre?.toLowerCase().includes(term) ||
        reservation.logement?.adresse?.toLowerCase().includes(term) ||
        reservation.id_reservation.toString().includes(term)
      );
    }

    if (this.statusFilter) {
      filtered = filtered.filter(reservation => reservation.statut === this.statusFilter);
    }

    // Apply day filter
    if (this.selectedDay) {
      filtered = filtered.filter(reservation => {
        const checkInDate = new Date(reservation.date_debut);
        const dayOfWeek = checkInDate.toLocaleDateString('en-US', { weekday: 'long' }).toLowerCase();
        return dayOfWeek === this.selectedDay;
      });
    }

    return filtered;
  }

  // Update applyFilters to include pagination
  applyFilters(): void {
    this.currentPage = 1;
    this.applyPagination();
  }

  // Map reservation to accommodation for the accommodation card component
  mapReservationToAccommodation(reservation: ReservationResponse): Accommodation {
    const accommodation = this.accommodations().get(reservation.id_logement);

    if (accommodation) {
      // Use complete accommodation data if available
      return accommodation;
    } else {
      // Fallback to reservation data if accommodation not loaded yet
      return {
        id: reservation.logement?.id_logement || 0,
        titre: reservation.logement?.titre || 'Unknown Accommodation',
        description: null,
        type: (reservation.logement?.type as 'hotel' | 'maison' | 'studio') || 'hotel',
        capacite: reservation.nb_personnes,
        adresse: reservation.logement?.adresse || null,
        photo_url: reservation.logement?.photo_url || null,
        statut: 'actif' as 'actif' | 'inactif',
        tarif_amount: reservation.montant_total.toString(),
        tarif_unit: 'jour' as 'heure' | 'jour' | 'mois',
        devise: 'MAD',
        created_at: reservation.created_at,
        agence: {
          id: 0,
          nom: reservation.logement?.agence?.nom || 'Unknown Agency',
          email: '',
          score_classement: '0'
        }
      };
    }
  }

  // Event handlers for accommodation card
  onFavoriteAccommodation(accommodation: Accommodation): void {
    console.log('Favorite accommodation:', accommodation);
    // Implement favorite functionality if needed
  }

  onViewAccommodationDetails(accommodation: Accommodation): void {
    console.log('View accommodation details:', accommodation);
    // Implement view details functionality if needed
  }

  onBookAccommodation(accommodation: Accommodation): void {
    console.log('Book accommodation:', accommodation);
    // Implement booking functionality if needed
  }

  // Check if accommodation data is loaded for a reservation
  isAccommodationDataLoaded(reservation: ReservationResponse): boolean {
    return this.accommodations().has(reservation.id_logement);
  }

  // Review Dialog
  // Placed at root to avoid clipping
  public get reviewDialogTemplate(): string {
    return '';
  }
}



