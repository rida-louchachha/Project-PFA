import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, catchError, map, of, tap } from 'rxjs';

export interface AppSettings {
  currency: { code: string; symbol: string };
}

/** Defaults used until (or if) GET /api/settings cannot be reached. */
const DEFAULT_SETTINGS: AppSettings = {
  currency: { code: 'MAD', symbol: 'DH' },
};

/**
 * Application settings served by the backend (config/travelplan.php, .env).
 * Loaded once at startup (see app.config.ts).
 */
@Injectable({ providedIn: 'root' })
export class AppSettingsService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = 'http://localhost:8000/api';

  readonly settings = signal<AppSettings>(DEFAULT_SETTINGS);

  load(): Observable<void> {
    return this.http.get<{ success: boolean; data: AppSettings }>(`${this.apiUrl}/settings`).pipe(
      tap(response => {
        if (response?.data?.currency) {
          this.settings.set(response.data);
        }
      }),
      map(() => undefined),
      catchError(() => of(undefined)),
    );
  }

  /** "DH" for the configured currency code (MAD), otherwise the code itself. */
  currencyLabel(code?: string | null): string {
    const { currency } = this.settings();
    return !code || code.toUpperCase() === currency.code.toUpperCase() ? currency.symbol : code;
  }

  /** e.g. 1950 -> "1 950,00 DH" */
  formatAmount(amount: number | string, code?: string | null): string {
    const value = Number(amount);
    const formatted = isNaN(value)
      ? String(amount)
      : value.toLocaleString('fr-MA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    return `${formatted} ${this.currencyLabel(code)}`;
  }
}
