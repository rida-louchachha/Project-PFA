import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface Accommodation {
  id: number;
  titre: string;
  description: string | null;
  type: 'hotel' | 'maison' | 'studio';
  capacite: number;
  adresse: string | null;
  photo_url: string | null;
  statut: 'actif' | 'inactif';
  tarif_amount: string | number; // API returns string
  tarif_unit: 'heure' | 'jour' | 'mois';
  devise: string;
  created_at: string;
  agence: {
    id: number;
    nom: string;
    email: string;
    score_classement: string | number; // API returns string
  };
}

export interface AccommodationFilters {
  type?: string;
  statut?: string;
  min_price?: number;
  max_price?: number;
  capacite?: number;
  search?: string;
  sort_by?: 'created_at' | 'tarif_amount' | 'capacite' | 'titre';
  sort_order?: 'asc' | 'desc';
  per_page?: number;
  page?: number;
}

export interface AccommodationResponse {
  success: boolean;
  data: {
    data: Accommodation[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

export interface FilterOptions {
  success: boolean;
  data: {
    types: string[];
    price_range: {
      min: number;
      max: number;
    };
    capacity_range: {
      min: number;
      max: number;
    };
  };
}

@Injectable({
  providedIn: 'root'
})
export class AccommodationService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8000/api';

  getAccommodations(filters: AccommodationFilters = {}): Observable<AccommodationResponse> {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, value.toString());
      }
    });

    const url = `${this.apiUrl}/logements${params.toString() ? '?' + params.toString() : ''}`;
    return this.http.get<AccommodationResponse>(url);
  }

  /** Agency accounts: only the accommodations of the logged-in agency (same filters). */
  getMyAccommodations(filters: AccommodationFilters = {}): Observable<AccommodationResponse> {
    const params = new URLSearchParams();

    Object.entries(filters).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== '') {
        params.append(key, value.toString());
      }
    });

    const url = `${this.apiUrl}/agency/logements${params.toString() ? '?' + params.toString() : ''}`;
    return this.http.get<AccommodationResponse>(url);
  }

  getAccommodation(id: number): Observable<{success: boolean, data: Accommodation}> {
    return this.http.get<{success: boolean, data: Accommodation}>(`${this.apiUrl}/logements/${id}`);
  }

  getFilterOptions(): Observable<FilterOptions> {
    return this.http.get<FilterOptions>(`${this.apiUrl}/logements/filters/options`);
  }

  // Update an accommodation (agency only)
  updateAccommodation(id: number, payload: Partial<Accommodation>): Observable<{success: boolean, data: Accommodation, message?: string}> {
    return this.http.put<{success: boolean, data: Accommodation, message?: string}>(`${this.apiUrl}/logements/${id}`, payload);
  }

  // Delete an accommodation (agency only)
  deleteAccommodation(id: number): Observable<{success: boolean, message?: string}> {
    return this.http.delete<{success: boolean, message?: string}>(`${this.apiUrl}/logements/${id}`);
  }

  // Create an accommodation (agency only)
  createAccommodation(payload: Partial<Accommodation>): Observable<{success: boolean, data: Accommodation, message?: string}> {
    return this.http.post<{success: boolean, data: Accommodation, message?: string}>(`${this.apiUrl}/logements`, payload);
  }
}
