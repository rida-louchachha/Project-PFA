import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';

export interface SmartSearchFilters {
  ville: string | null;
  type: 'hotel' | 'maison' | 'studio' | null;
  nb_personnes: number | null;
  prix_max: number | null;
  date_arrivee: string | null;
  nuits: number | null;
  mots_cles: string[];
}

export interface SmartSearchResult {
  filters: SmartSearchFilters;
  chips: { icon: string; label: string }[];
  provider: string;
  fallback: boolean;
}

export interface Destination {
  ville: string;
  logements: number;
  prix_min: number;
  devise: string;
  photo_url: string | null;
}

export interface Recommendation {
  id: number;
  titre: string;
  type: string;
  ville: string | null;
  photo_url: string | null;
  tarif_amount: string | number;
  tarif_unit: string;
  devise: string;
  capacite: number;
  agence: string | null;
  note: string | number | null;
  score: number;
  raisons: string[];
}

export interface Recommendations {
  personalised: boolean;
  profile: { types: string[]; villes: string[]; budget_moyen: number | null };
  items: Recommendation[];
}

export interface AgencyInsights {
  devise: string;
  kpis: {
    revenus_prevus_30j: number;
    sejours_30j: number;
    taux_occupation_30j: number;
    demandes_confirmation: number;
    venues_confirmees: number;
    taux_confirmation: number | null;
    en_attente_paiement: number;
    note_moyenne: number;
    nombre_avis: number;
    logements_actifs: number;
    logement_le_plus_reserve: string | null;
  };
  arrivees_14j: { date: string; label: string; count: number }[];
  resume: string;
  resume_source: 'ai' | 'template';
}

interface ApiResponse<T> {
  success: boolean;
  data: T;
}

/** Smart search, destinations, recommendations and agency insights (Laravel API). */
@Injectable({ providedIn: 'root' })
export class DiscoveryService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8000/api';

  smartSearch(query: string): Observable<SmartSearchResult> {
    return this.http.post<ApiResponse<SmartSearchResult>>(`${this.apiUrl}/ai/smart-search`, { query }).pipe(map(r => r.data));
  }

  destinations(): Observable<Destination[]> {
    return this.http.get<ApiResponse<Destination[]>>(`${this.apiUrl}/destinations`).pipe(map(r => r.data));
  }

  recommendations(): Observable<Recommendations> {
    return this.http.get<ApiResponse<Recommendations>>(`${this.apiUrl}/recommendations`).pipe(map(r => r.data));
  }

  agencyInsights(): Observable<AgencyInsights> {
    return this.http.get<ApiResponse<AgencyInsights>>(`${this.apiUrl}/agency/assistant/insights`).pipe(map(r => r.data));
  }
}
