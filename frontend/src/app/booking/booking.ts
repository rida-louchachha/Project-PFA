import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface ReservationData {
  id_logement: number;
  date_debut: string;
  date_fin: string;
  nb_personnes: number;
  montant_total: number;
}

export interface PaymentData {
  id_reservation: number;
  mode: 'especes' | 'terminal' | 'en_ligne';
  montant: number;
  reference?: string;
}

export interface BookingRequest {
  reservation: ReservationData;
  payment: PaymentData;
}

export interface ReservationResponse {
  id_reservation: number;
  id_utilisateur: number;
  id_logement: number;
  date_debut: string;
  date_fin: string;
  nb_personnes: number;
  statut: string;
  montant_total: number;
  created_at: string;
  /** Arrival intention collected by TravelPlan AI (separate from statut) */
  confirmation?: {
    intention: string;
    requested_at: string | null;
    responded_at: string | null;
  } | null;
  logement?: {
    id_logement: number;
    titre: string;
    type: string;
    adresse: string;
    photo_url: string;
    agence: {
      nom: string;
    };
  };
}

export interface PaymentResponse {
  success: boolean;
  data: {
    payment: {
      id_paiement: number;
      id_reservation: number;
      mode: string;
      montant: number;
      date_paiement: string;
      statut: string;
      reference: string;
      created_at: string;
    };
  };
  message: string;
}

@Injectable({
  providedIn: 'root'
})
export class BookingService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8000/api';

  createReservation(reservationData: Omit<ReservationData, 'montant_total'>): Observable<{success: boolean, data: {reservation: ReservationResponse}, message: string}> {
    return this.http.post<{success: boolean, data: {reservation: ReservationResponse}, message: string}>(`${this.apiUrl}/reservations`, reservationData);
  }

  getUserReservations(): Observable<ReservationResponse[]> {
    return this.http.get<ReservationResponse[]>(`${this.apiUrl}/reservations`);
  }

  getReservation(id: number): Observable<ReservationResponse> {
    return this.http.get<ReservationResponse>(`${this.apiUrl}/reservations/${id}`);
  }

  cancelReservation(id: number): Observable<{success: boolean, message: string}> {
    return this.http.put<{success: boolean, message: string}>(`${this.apiUrl}/reservations/${id}/cancel`, {});
  }

  processPayment(paymentData: PaymentData): Observable<PaymentResponse> {
    return this.http.post<PaymentResponse>(`${this.apiUrl}/payments`, paymentData);
  }

  completeBooking(bookingData: BookingRequest): Observable<{reservation: ReservationResponse, payment: PaymentResponse}> {
    // This will handle the complete booking process
    return this.http.post<{reservation: ReservationResponse, payment: PaymentResponse}>(`${this.apiUrl}/bookings`, bookingData);
  }


  calculateTotal(accommodationPrice: number, days: number, guests: number): number {
    // Calculate total based on price per unit, number of days, and guests
    const basePrice = accommodationPrice * days;
    // Add guest surcharge if more than base capacity (this is just an example)
    const guestSurcharge = guests > 2 ? (guests - 2) * 20 : 0;
    return basePrice + guestSurcharge;
  }

  calculateDaysBetween(startDate: string, endDate: string): number {
    const start = new Date(startDate);
    const end = new Date(endDate);
    const timeDiff = end.getTime() - start.getTime();
    return Math.ceil(timeDiff / (1000 * 3600 * 24));
  }
}
