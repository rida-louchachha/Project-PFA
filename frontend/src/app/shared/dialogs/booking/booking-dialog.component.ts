import { Component, Input, Output, EventEmitter, OnInit, OnDestroy, inject } from '@angular/core';
import { CurrencyLabelPipe } from '../../../settings/currency-label.pipe';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, FormBuilder, FormGroup, Validators } from '@angular/forms';
import {
  LucideAngularModule,
  X,
  Calendar,
  Users,
  CreditCard,
  Banknote,
  ChevronRight,
  ChevronLeft,
  Check,
  AlertCircle,
  Loader2
} from 'lucide-angular';
import { Accommodation } from '../../../accommodation/accommodation';
import { BookingService, ReservationData, PaymentData } from '../../../booking/booking';
import { AuthService } from '../../../auth/auth';

export interface BookingStep {
  id: number;
  title: string;
  description: string;
  completed: boolean;
}

@Component({
  selector: 'app-booking-dialog',
  standalone: true,
  imports: [CommonModule, ReactiveFormsModule, LucideAngularModule, CurrencyLabelPipe],
  templateUrl: './booking-dialog.component.html',
  styleUrl: './booking-dialog.component.scss'
})
export class BookingDialogComponent implements OnInit, OnDestroy {
  @Input({ required: true }) accommodation!: Accommodation;
  @Input() isOpen: boolean = false;
  /** Pre-fill from TravelPlan AI smart search (YYYY-MM-DD, nights, guests) */
  @Input() initialCheckIn: string | null = null;
  @Input() initialNights: number | null = null;
  @Input() initialGuests: number | null = null;
  @Output() closeDialog = new EventEmitter<void>();
  @Output() bookingCompleted = new EventEmitter<{reservationId: number, paymentId: number}>();

  private readonly formBuilder = inject(FormBuilder);
  private readonly bookingService = inject(BookingService);
  private readonly authService = inject(AuthService);

  // Register Lucide icons
  readonly X = X;
  readonly Calendar = Calendar;
  readonly Users = Users;
  readonly CreditCard = CreditCard;
  readonly Banknote = Banknote;
  readonly ChevronRight = ChevronRight;
  readonly ChevronLeft = ChevronLeft;
  readonly Check = Check;
  readonly AlertCircle = AlertCircle;
  readonly Loader2 = Loader2;

  // Step management
  currentStep = 1;
  totalSteps = 2;
  isProcessing = false;
  errorMessage = '';
  successMessage = '';

  // Forms
  reservationForm!: FormGroup;
  paymentForm!: FormGroup;

  // Calculated values
  totalDays = 0;
  totalAmount = 0;

  steps: BookingStep[] = [
    {
      id: 1,
      title: 'Reservation Details',
      description: 'Select dates and number of guests',
      completed: false
    },
    {
      id: 2,
      title: 'Payment Information',
      description: 'Choose payment method and complete booking',
      completed: false
    }
  ];

  ngOnInit(): void {
    this.initializeForms();
    if (this.isOpen) {
      document.addEventListener('keydown', this.handleEscapeKey.bind(this));
    }
  }

  ngOnDestroy(): void {
    document.removeEventListener('keydown', this.handleEscapeKey.bind(this));
  }

  private initializeForms(): void {
    // Get current date for minimum date validation
    const today = new Date();
    const todayString = today.toISOString().split('T')[0];
    const tomorrow = new Date(today);
    tomorrow.setDate(tomorrow.getDate() + 1);
    const tomorrowString = tomorrow.toISOString().split('T')[0];

    // Dates / guests suggested by the AI smart search, when provided
    let checkIn = todayString;
    let checkOut = tomorrowString;
    if (this.initialCheckIn && this.initialCheckIn >= todayString) {
      checkIn = this.initialCheckIn;
      const end = new Date(this.initialCheckIn + 'T00:00:00');
      end.setDate(end.getDate() + Math.max(1, this.initialNights ?? 1));
      checkOut = `${end.getFullYear()}-${String(end.getMonth() + 1).padStart(2, '0')}-${String(end.getDate()).padStart(2, '0')}`;
    }
    const guests = Math.min(Math.max(1, this.initialGuests ?? 1), this.accommodation.capacite);

    this.reservationForm = this.formBuilder.group({
      checkIn: [checkIn, [Validators.required]],
      checkOut: [checkOut, [Validators.required]],
      guests: [guests, [Validators.required, Validators.min(1), Validators.max(this.accommodation.capacite)]]
    });

    this.paymentForm = this.formBuilder.group({
      paymentMethod: ['en_ligne', [Validators.required]],
      cardNumber: ['', []],
      expiryDate: ['', []],
      cvv: ['', []],
      cardholderName: ['', []]
    });

    // Watch for form changes to update calculations
    this.reservationForm.valueChanges.subscribe(() => {
      this.updateCalculations();
    });

    // Update payment method validation
    this.paymentForm.get('paymentMethod')?.valueChanges.subscribe(method => {
      this.updatePaymentValidation(method);
    });

    // Initial calculation
    this.updateCalculations();
  }

  private updatePaymentValidation(method: string): void {
    const cardNumber = this.paymentForm.get('cardNumber');
    const expiryDate = this.paymentForm.get('expiryDate');
    const cvv = this.paymentForm.get('cvv');
    const cardholderName = this.paymentForm.get('cardholderName');

    if (method === 'en_ligne') {
      // Add validation for online payment
      cardNumber?.setValidators([Validators.required, Validators.pattern(/^\d{16}$/)]);
      expiryDate?.setValidators([Validators.required, Validators.pattern(/^(0[1-9]|1[0-2])\/\d{2}$/)]);
      cvv?.setValidators([Validators.required, Validators.pattern(/^\d{3,4}$/)]);
      cardholderName?.setValidators([Validators.required]);
    } else {
      // Remove validation for cash or terminal payment
      cardNumber?.clearValidators();
      expiryDate?.clearValidators();
      cvv?.clearValidators();
      cardholderName?.clearValidators();
    }

    // Update form validation
    cardNumber?.updateValueAndValidity();
    expiryDate?.updateValueAndValidity();
    cvv?.updateValueAndValidity();
    cardholderName?.updateValueAndValidity();
  }

  private updateCalculations(): void {
    const checkIn = this.reservationForm.get('checkIn')?.value;
    const checkOut = this.reservationForm.get('checkOut')?.value;
    const guests = this.reservationForm.get('guests')?.value || 1;

    if (checkIn && checkOut) {
      this.totalDays = this.bookingService.calculateDaysBetween(checkIn, checkOut);
      const price = typeof this.accommodation.tarif_amount === 'string'
        ? parseFloat(this.accommodation.tarif_amount)
        : this.accommodation.tarif_amount;

      this.totalAmount = this.bookingService.calculateTotal(price, this.totalDays, guests);
    }
  }

  private handleEscapeKey(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
      this.onClose();
    }
  }

  protected onClose(): void {
    this.closeDialog.emit();
    this.resetDialog();
  }

  private resetDialog(): void {
    this.currentStep = 1;
    this.isProcessing = false;
    this.errorMessage = '';
    this.successMessage = '';
    this.steps.forEach(step => step.completed = false);
    this.initializeForms();
  }

  protected onBackdropClick(event: MouseEvent): void {
    if (event.target === event.currentTarget) {
      this.onClose();
    }
  }

  protected nextStep(): void {
    if (this.currentStep === 1) {
      if (this.reservationForm.valid) {
        this.steps[0].completed = true;
        this.currentStep = 2;
        this.errorMessage = '';
      } else {
        this.errorMessage = 'Please fill in all required fields correctly.';
      }
    }
  }

  protected previousStep(): void {
    if (this.currentStep > 1) {
      this.currentStep--;
      this.errorMessage = '';
    }
  }

  protected async completeBooking(): Promise<void> {
    if (!this.paymentForm.valid) {
      this.errorMessage = 'Please fill in all required payment information.';
      return;
    }

    this.isProcessing = true;
    this.errorMessage = '';

    try {
      // Check authentication
      const currentUser = this.authService.getCurrentUserSync();
      const token = this.authService.getToken();
      
      if (!currentUser || !token) {
        this.errorMessage = 'Please log in again to make a reservation.';
        this.isProcessing = false;
        return;
      }

      const reservationData: Omit<ReservationData, 'montant_total'> = {
        id_logement: this.accommodation.id,
        date_debut: this.reservationForm.get('checkIn')?.value,
        date_fin: this.reservationForm.get('checkOut')?.value,
        nb_personnes: this.reservationForm.get('guests')?.value
      };

      // Step 1: Create reservation
      const reservationResponse = await this.bookingService.createReservation(reservationData).toPromise();

      if (!reservationResponse?.success) {
        throw new Error(reservationResponse?.message || 'Failed to create reservation');
      }

      const paymentData: PaymentData = {
        id_reservation: reservationResponse.data.reservation.id_reservation,
        mode: this.paymentForm.get('paymentMethod')?.value,
        montant: this.totalAmount,
        reference: this.generatePaymentReference()
      };

      // Step 2: Process payment
      const paymentResponse = await this.bookingService.processPayment(paymentData).toPromise();

      if (!paymentResponse?.success) {
        throw new Error(paymentResponse?.message || 'Payment processing failed');
      }

      // Success!
      this.steps[1].completed = true;
      this.successMessage = 'Booking completed successfully!';
      this.bookingCompleted.emit({
        reservationId: reservationResponse.data.reservation.id_reservation,
        paymentId: paymentResponse.data.payment.id_paiement
      });

      // Auto-close after success
      setTimeout(() => {
        this.onClose();
      }, 2000);

    } catch (error: any) {
      this.errorMessage = error.message || 'An error occurred while processing your booking.';
    } finally {
      this.isProcessing = false;
    }
  }

  private generatePaymentReference(): string {
    return 'PAY-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9).toUpperCase();
  }

  protected getStepStatus(stepId: number): 'completed' | 'current' | 'pending' {
    const step = this.steps.find(s => s.id === stepId);
    if (step?.completed) return 'completed';
    if (stepId === this.currentStep) return 'current';
    return 'pending';
  }

  protected getDefaultImage(): string {
    return 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=60';
  }

  protected onImageError(event: any): void {
    event.target.src = this.getDefaultImage();
  }

  protected getPaymentMethodDisplayName(method: string): string {
    switch (method) {
      case 'especes': return 'Cash';
      case 'terminal': return 'Card Terminal';
      case 'en_ligne': return 'Online Payment';
      default: return method;
    }
  }
}
