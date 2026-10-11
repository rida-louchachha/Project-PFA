import { CanActivateFn, Router, Routes } from '@angular/router';
import { inject } from '@angular/core';
import { AuthComponent } from './auth/auth.component';
import { HomeComponent } from './home/home';
import { ReservationsComponent } from './reservations/reservations.component';
import { AssistantPageComponent } from './assistant/assistant-page/assistant-page.component';
import { AgencyAssistantDashboardComponent } from './agency-assistant/agency-assistant-dashboard.component';
import { AuthService } from './auth/auth';
import { map, take } from 'rxjs/operators';

// Simple auth guard function. Remembers the requested URL (e.g. an email link to
// /assistant/:reservationId) so the user lands back on it after logging in.
const authGuard: CanActivateFn = (_route, state) => {
  const authService = inject(AuthService);
  const router = inject(Router);
  return authService.isAuthenticated$.pipe(
    take(1),
    map(isAuthenticated => {
      if (!isAuthenticated) {
        authService.rememberReturnUrl(state.url);
        return router.createUrlTree(['/auth']);
      }
      return true;
    })
  );
};

// Login page: an already logged-in user goes straight to the app
const guestGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);
  return authService.isAuthenticated() ? router.createUrlTree(['/home']) : true;
};

// Agency-only pages (the API enforces the same rule server-side)
const agencyGuard: CanActivateFn = () => {
  const authService = inject(AuthService);
  const router = inject(Router);
  return authService.getCurrentUserSync()?.role === 'agence' ? true : router.createUrlTree(['/home']);
};

export const routes: Routes = [
  { path: 'auth', component: AuthComponent, canActivate: [guestGuard] },
  { path: 'home', component: HomeComponent, canActivate: [authGuard] },
  { path: 'reservations', component: ReservationsComponent, canActivate: [authGuard] },
  { path: 'assistant', component: AssistantPageComponent, canActivate: [authGuard] },
  { path: 'assistant/:reservationId', component: AssistantPageComponent, canActivate: [authGuard] },
  { path: 'agence/assistant', component: AgencyAssistantDashboardComponent, canActivate: [authGuard, agencyGuard] },
  // '' and unknown URLs -> /auth, which forwards logged-in users to /home (guestGuard)
  { path: '', pathMatch: 'full', redirectTo: 'auth' },
  { path: '**', redirectTo: 'auth' }
];
