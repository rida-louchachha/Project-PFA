import { AfterViewInit, Component, DestroyRef, ElementRef, HostListener, OnInit, ViewChild, inject, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterModule } from '@angular/router';
import { filter } from 'rxjs';
import {
  LucideAngularModule, House, Search, CalendarCheck, Sparkles, Menu, X, LogOut, Luggage,
  Building2, Heart, ChevronDown, PlaneLanding, Compass, LayoutDashboard,
} from 'lucide-angular';
import { AuthService, User } from '../auth/auth';
import { BookingService } from '../booking/booking';
import { DiscoveryService } from '../discovery/discovery.service';

/** Personal widget shown in the header (next trip for clients, today's arrivals for agencies). */
interface TripWidget {
  icon: 'trip' | 'arrivals' | 'explore';
  title: string;
  subtitle: string;
  link: (string | number)[];
  fragment?: string;
}

// The header is re-created on every page: keep the widget for a minute to avoid refetching
let widgetCache: { userId: number; at: number; widget: TripWidget } | null = null;

const GREETINGS = ['Marhaba', 'Bonjour', 'Salam', 'Hello', 'Ahlan'];

@Component({
  selector: 'app-header',
  standalone: true,
  imports: [CommonModule, RouterModule, LucideAngularModule],
  templateUrl: './header.html',
  styleUrl: './header.scss'
})
export class HeaderComponent implements OnInit, AfterViewInit {
  private readonly authService = inject(AuthService);
  private readonly bookingService = inject(BookingService);
  private readonly discovery = inject(DiscoveryService);
  private readonly router = inject(Router);
  private readonly host = inject(ElementRef<HTMLElement>);
  private readonly destroyRef = inject(DestroyRef);

  @ViewChild('navPill') private navPill?: ElementRef<HTMLElement>;

  protected currentUser$ = this.authService.currentUser$;
  protected showDropdown = false;
  protected readonly mobileOpen = signal(false);
  protected readonly scrolled = signal(false);
  protected readonly greeting = signal(GREETINGS[0]);
  protected readonly indicator = signal<{ left: number; width: number } | null>(null);
  protected readonly widget = signal<TripWidget | null>(null);

  readonly House = House;
  readonly Search = Search;
  readonly CalendarCheck = CalendarCheck;
  readonly Sparkles = Sparkles;
  readonly Menu = Menu;
  readonly X = X;
  readonly LogOut = LogOut;
  readonly Luggage = Luggage;
  readonly Building2 = Building2;
  readonly Heart = Heart;
  readonly ChevronDown = ChevronDown;
  readonly PlaneLanding = PlaneLanding;
  readonly Compass = Compass;
  readonly LayoutDashboard = LayoutDashboard;

  ngOnInit(): void {
    this.scrolled.set(window.scrollY > 8);

    // Rotating multilingual greeting (Darija / French / English)
    let index = 0;
    const timer = setInterval(() => {
      index = (index + 1) % GREETINGS.length;
      this.greeting.set(GREETINGS[index]);
    }, 3500);
    this.destroyRef.onDestroy(() => clearInterval(timer));

    this.loadWidget();
  }

  ngAfterViewInit(): void {
    this.moveIndicator();
    this.router.events
      .pipe(filter(e => e instanceof NavigationEnd), takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.mobileOpen.set(false);
        this.moveIndicator();
      });
  }

  @HostListener('window:scroll')
  protected onScroll(): void {
    this.scrolled.set(window.scrollY > 8);
  }

  @HostListener('window:resize')
  protected onResize(): void {
    this.moveIndicator();
  }

  /** Close menus when clicking outside the header */
  @HostListener('document:click', ['$event'])
  protected onDocumentClick(event: MouseEvent): void {
    if (!this.host.nativeElement.contains(event.target as Node)) {
      this.showDropdown = false;
      this.mobileOpen.set(false);
    }
  }

  protected toggleDropdown(): void {
    this.showDropdown = !this.showDropdown;
  }

  protected closeDropdown(): void {
    this.showDropdown = false;
  }

  protected logout(): void {
    widgetCache = null;
    this.authService.logout().subscribe({
      next: (response) => {
        console.log('Logout successful:', response);
      },
      error: (error) => {
        console.error('Logout error:', error);
        // Force logout even if API call fails
        localStorage.removeItem('auth_token');
        localStorage.removeItem('current_user');
        window.location.href = '/auth';
      }
    });
    this.closeDropdown();
  }

  protected getInitials(user: User): string {
    return `${user.prenom.charAt(0)}${user.nom.charAt(0)}`.toUpperCase();
  }

  protected getRoleDisplayName(role: string): string {
    switch (role) {
      case 'client':
        return 'Voyageur';
      case 'agence':
        return 'Agence de voyage';
      default:
        return role;
    }
  }

  /** Slide the highlight pill under the active nav link */
  private moveIndicator(): void {
    setTimeout(() => {
      const pill = this.navPill?.nativeElement;
      const active = pill?.querySelector<HTMLElement>('.nav-link.active');
      this.indicator.set(active ? { left: active.offsetLeft, width: active.offsetWidth } : null);
    }, 30);
  }

  private loadWidget(): void {
    const user = this.authService.getCurrentUserSync();
    if (!user) {
      return;
    }
    if (widgetCache && widgetCache.userId === user.id && Date.now() - widgetCache.at < 60_000) {
      this.widget.set(widgetCache.widget);
      return;
    }

    const save = (widget: TripWidget) => {
      widgetCache = { userId: user.id, at: Date.now(), widget };
      this.widget.set(widget);
    };

    if (user.role === 'agence') {
      this.discovery.agencyInsights().subscribe({
        next: insights => {
          const today = insights.arrivees_14j[0]?.count ?? 0;
          const week = insights.arrivees_14j.slice(0, 7).reduce((s, a) => s + a.count, 0);
          save({
            icon: 'arrivals',
            title: today > 0 ? `${today} arrivée${today > 1 ? 's' : ''} aujourd'hui` : `${week} arrivée${week > 1 ? 's' : ''} cette semaine`,
            subtitle: 'Voir le tableau IA',
            link: ['/agence/assistant'],
          });
        },
        error: () => {},
      });
      return;
    }

    this.bookingService.getUserReservations().subscribe({
      next: reservations => {
        const now = Date.now();
        const next = (reservations ?? [])
          .filter(r => r.statut !== 'annule' && new Date(r.date_fin).getTime() >= now)
          .sort((a, b) => new Date(a.date_debut).getTime() - new Date(b.date_debut).getTime())[0];

        if (!next) {
          save({ icon: 'explore', title: 'Où partir ?', subtitle: 'Recherche intelligente', link: ['/home'], fragment: 'search' });
          return;
        }

        const city = (next.logement?.adresse ?? '').split(',').pop()?.trim() || next.logement?.titre || 'Votre séjour';
        const start = new Date(next.date_debut);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const startDay = new Date(start);
        startDay.setHours(0, 0, 0, 0);
        const days = Math.round((startDay.getTime() - today.getTime()) / 86_400_000);

        save({
          icon: 'trip',
          title: days <= 0 ? `En séjour · ${city}` : `${city} · J-${days}`,
          subtitle: days <= 0 ? 'Votre assistant est là' : days === 1 ? 'Départ demain !' : 'Prochain séjour',
          link: ['/assistant', next.id_reservation],
        });
      },
      error: () => {},
    });
  }
}
