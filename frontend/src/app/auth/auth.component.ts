import { Component, signal, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormBuilder, FormGroup, ReactiveFormsModule, Validators, AbstractControl, ValidationErrors } from '@angular/forms';
import { LucideAngularModule, Mail, Lock, User, Eye, EyeOff, Sparkles, ShieldCheck, Search, MessageCircle, Building2, Luggage, CircleCheck } from 'lucide-angular';
import { AuthService } from './auth';

const FIELD_LABELS: Record<string, string> = {
  nom: 'Nom',
  prenom: 'Prénom',
  email: 'Email',
  mot_de_passe: 'Mot de passe',
  password_confirmation: 'Confirmation',
  role: 'Type de compte',
};

@Component({
  selector: 'app-auth',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, LucideAngularModule],
  templateUrl: './auth.component.html',
  styleUrl: './auth.component.scss'
})
export class AuthComponent {
  private readonly authService = inject(AuthService);
  private readonly formBuilder = inject(FormBuilder);

  protected activeTab = signal<'signin' | 'signup'>('signin');
  protected errorMessage = signal<string>('');
  protected successMessage = signal<string>('');
  protected showPassword = signal(false);
  protected showSignupPassword = signal(false);

  protected signInForm: FormGroup;
  protected signUpForm: FormGroup;

  protected readonly isLoading$ = this.authService.isLoading$;

  readonly Mail = Mail;
  readonly Lock = Lock;
  readonly User = User;
  readonly Eye = Eye;
  readonly EyeOff = EyeOff;
  readonly Sparkles = Sparkles;
  readonly ShieldCheck = ShieldCheck;
  readonly Search = Search;
  readonly MessageCircle = MessageCircle;
  readonly Building2 = Building2;
  readonly Luggage = Luggage;
  readonly CircleCheck = CircleCheck;

  constructor() {
    this.signInForm = this.formBuilder.group({
      email: ['', [Validators.required, Validators.email]],
      password: ['', [Validators.required, Validators.minLength(6)]],
    });

    this.signUpForm = this.formBuilder.group({
      nom: ['', [Validators.required, Validators.minLength(2)]],
      prenom: ['', [Validators.required, Validators.minLength(2)]],
      email: ['', [Validators.required, Validators.email]],
      mot_de_passe: ['', [Validators.required, Validators.minLength(6)]],
      password_confirmation: ['', [Validators.required, Validators.minLength(6)]],
      role: ['', [Validators.required]],
    }, { validators: this.passwordMatchValidator });
  }

  // Custom validator to check if passwords match
  private passwordMatchValidator(control: AbstractControl): ValidationErrors | null {
    const password = control.get('mot_de_passe');
    const confirmPassword = control.get('password_confirmation');

    if (!password || !confirmPassword) {
      return null;
    }

    if (password.value !== confirmPassword.value) {
      return { passwordMismatch: true };
    }

    return null;
  }

  protected switchTab(tab: 'signin' | 'signup'): void {
    this.activeTab.set(tab);
    this.errorMessage.set('');
    this.successMessage.set('');
  }

  /** Demo accounts (seeded by MoroccanDemoSeeder) — handy during the PFA presentation */
  protected fillDemo(kind: 'client' | 'agence'): void {
    this.switchTab('signin');
    this.signInForm.setValue({
      email: kind === 'client' ? 'client@example.com' : 'agence@example.com',
      password: 'password',
    });
    this.signInForm.markAsDirty();
  }

  protected submitSignIn(): void {
    if (this.signInForm.invalid) {
      this.signInForm.markAllAsTouched();
      return;
    }

    const credentials = this.signInForm.value;
    this.authService.login(credentials).subscribe({
      next: (response) => {
        if (response.success) {
          this.successMessage.set('Connexion réussie, redirection…');
          this.errorMessage.set('');
        } else {
          this.errorMessage.set(response.message || 'Connexion impossible.');
          this.successMessage.set('');
        }
      },
      error: (error) => {
        console.error('Login error:', error);
        if (error.status === 401) {
          this.errorMessage.set('Email ou mot de passe incorrect.');
        } else if (error.status === 0) {
          this.errorMessage.set('Le serveur TravelPlan est injoignable. Réessayez dans un instant.');
        } else if (error.error?.errors) {
          const firstError = Object.values(error.error.errors)[0] as string[];
          this.errorMessage.set(firstError[0] || 'Connexion impossible.');
        } else {
          this.errorMessage.set(error.error?.message || 'Connexion impossible. Réessayez.');
        }
        this.successMessage.set('');
      }
    });
  }

  protected submitSignUp(): void {
    if (this.signUpForm.invalid) {
      this.signUpForm.markAllAsTouched();
      return;
    }

    const userData = this.signUpForm.value;
    this.authService.register(userData).subscribe({
      next: (response) => {
        if (response.success) {
          this.successMessage.set('Compte créé, bienvenue sur TravelPlan !');
          this.errorMessage.set('');
        } else {
          this.errorMessage.set(response.message || 'Inscription impossible.');
          this.successMessage.set('');
        }
      },
      error: (error) => {
        console.error('Registration error:', error);
        if (error.error?.errors) {
          const errors = Object.entries(error.error.errors)
            .map(([field, messages]: [string, any]) => `${FIELD_LABELS[field] ?? field} : ${messages[0]}`)
            .join(' · ');
          this.errorMessage.set(errors);
        } else if (error.status === 0) {
          this.errorMessage.set('Le serveur TravelPlan est injoignable. Réessayez dans un instant.');
        } else {
          this.errorMessage.set(error.error?.message || 'Inscription impossible. Réessayez.');
        }
        this.successMessage.set('');
      }
    });
  }
}
