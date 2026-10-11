import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';

export interface CreateReviewRequest {
  id_agence: number;
  note: number; // 1..5
  commentaire?: string;
}

export interface ReviewResponse {
  success: boolean;
  data?: any;
  message?: string;
  errors?: any;
}

@Injectable({ providedIn: 'root' })
export class ReviewService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8000/api';

  submitReview(payload: CreateReviewRequest): Observable<ReviewResponse> {
    return this.http.post<ReviewResponse>(`${this.apiUrl}/avis`, payload);
  }
}


