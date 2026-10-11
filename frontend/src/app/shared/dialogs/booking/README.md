# Booking Dialog Component

A comprehensive, multi-step booking dialog that handles the complete reservation and payment process with database integration.

## Features

✅ **Multi-Step Process**
- Step 1: Reservation Details (dates, guests)
- Step 2: Payment Information (method, card details)
- Progress indicator with step completion status

✅ **Smart Form Validation**
- Date validation (check-in before check-out)
- Guest capacity validation
- Payment method conditional validation
- Real-time form state management

✅ **Payment Processing**
- Multiple payment methods: Cash, Card Terminal, Online Payment
- Secure card information handling
- Payment reference generation
- Integration with backend APIs

✅ **Professional UI/UX**
- Modern step-by-step wizard
- Responsive design for all devices
- Loading states and error handling
- Success feedback and auto-close

✅ **Database Integration**
- Creates reservations in database
- Processes payments with status tracking
- Updates reservation status on payment
- Full booking confirmation flow

## Usage

### Basic Implementation

```typescript
// In your component
import { BookingDialogComponent } from '../shared/dialogs/booking/booking-dialog.component';

@Component({
  // ... other config
  imports: [..., BookingDialogComponent]
})
export class YourComponent {
  showBookingDialog = signal(false);
  selectedAccommodation = signal<Accommodation | null>(null);

  openBookingDialog(accommodation: Accommodation) {
    this.selectedAccommodation.set(accommodation);
    this.showBookingDialog.set(true);
  }

  closeBookingDialog() {
    this.showBookingDialog.set(false);
    this.selectedAccommodation.set(null);
  }

  handleBookingCompleted(event: {reservationId: number, paymentId: number}) {
    console.log('Booking completed!', event);
    // Handle successful booking (show confirmation, redirect, etc.)
    this.closeBookingDialog();
  }
}
```

### Template Usage

```html
<app-booking-dialog
  *ngIf="selectedAccommodation() && showBookingDialog()"
  [accommodation]="selectedAccommodation()!"
  [isOpen]="showBookingDialog()"
  (closeDialog)="closeBookingDialog()"
  (bookingCompleted)="handleBookingCompleted($event)">
</app-booking-dialog>
```

## Props

### Inputs
- `accommodation: Accommodation` (required) - The accommodation to book
- `isOpen: boolean` - Controls dialog visibility

### Outputs
- `closeDialog: EventEmitter<void>` - Emitted when dialog should close
- `bookingCompleted: EventEmitter<{reservationId: number, paymentId: number}>` - Emitted on successful booking

## Step-by-Step Process

### Step 1: Reservation Details
**Input Fields:**
- Check-in Date (date picker, minimum today)
- Check-out Date (date picker, after check-in)
- Number of Guests (dropdown, up to property capacity)

**Validation:**
- Check-in must be today or later
- Check-out must be after check-in
- Guests cannot exceed accommodation capacity

**Real-time Calculations:**
- Total days (automatically calculated)
- Total amount (base price + guest surcharges)

### Step 2: Payment Information
**Payment Methods:**
- **Online Payment** - Full card form with validation
- **Card Terminal** - For in-person payment
- **Cash** - For cash payment

**Card Form Fields (Online Payment):**
- Card Number (16 digits, validated)
- Expiry Date (MM/YY format)
- CVV (3-4 digits)
- Cardholder Name (required)

**Payment Summary:**
- Selected payment method
- Check-in/check-out dates
- Total amount to pay

## Backend Integration

### API Endpoints Used

```typescript
// Reservation Creation
POST /api/reservations
{
  "id_logement": number,
  "date_debut": "YYYY-MM-DD",
  "date_fin": "YYYY-MM-DD", 
  "nb_personnes": number
}

// Payment Processing
POST /api/payments
{
  "id_reservation": number,
  "mode": "especes|terminal|en_ligne",
  "montant": number,
  "reference": "string"
}
```

### Database Tables

**Reservations Table:**
- Stores booking details, dates, guests
- Links to user and accommodation
- Tracks reservation status

**Payments Table:**
- Records payment information
- Links to reservation
- Stores payment method and status
- Tracks payment reference

## Form Validation Rules

### Reservation Form
- **Check-in Date**: Required, must be today or later
- **Check-out Date**: Required, must be after check-in date
- **Guests**: Required, between 1 and accommodation capacity

### Payment Form
**Online Payment Mode:**
- **Card Number**: Required, exactly 16 digits
- **Expiry Date**: Required, MM/YY format
- **CVV**: Required, 3-4 digits
- **Cardholder Name**: Required

**Other Payment Modes:**
- No additional validation required

## Error Handling

### Client-Side Validation
- Real-time form validation with error messages
- Step transition blocked on invalid forms
- Clear error feedback for each field

### Server-Side Error Handling
- API error messages displayed to user
- Loading states during processing
- Graceful failure handling

### Common Error Scenarios
- Accommodation unavailable for selected dates
- Guest capacity exceeded
- Payment processing failures
- Network connectivity issues

## Pricing Calculation

### Base Pricing
```typescript
const basePrice = accommodationPrice * numberOfDays;
```

### Guest Surcharges
```typescript
const guestSurcharge = guests > 2 ? (guests - 2) * 20 : 0;
const totalAmount = basePrice + guestSurcharge;
```

### Currency Display
- Shows accommodation's currency (MAD, USD, EUR, etc.)
- Proper formatting with currency symbols

## Responsive Design

### Desktop Experience
- Two-column layout for forms
- Side-by-side step progress
- Full card form visibility

### Mobile Experience
- Single-column layout
- Stacked step progress
- Touch-optimized inputs
- Full-screen modal on small devices

### Breakpoints
- **Desktop**: > 768px
- **Tablet**: 481px - 768px
- **Mobile**: ≤ 480px

## Security Considerations

### Payment Information
- Card details handled securely
- No sensitive data stored locally
- HTTPS required for production

### Authentication
- User must be authenticated
- Reservation tied to authenticated user
- API endpoints protected with auth middleware

### Validation
- Both client and server-side validation
- Input sanitization on backend
- SQL injection prevention

## Customization Options

### Styling
- SCSS variables for easy theme customization
- Component-scoped styles
- Responsive breakpoints configurable

### Business Logic
- Guest surcharge calculation customizable
- Cancellation policy configurable
- Payment methods easily added/removed

### Validation Rules
- Form validation easily extended
- Custom validators can be added
- Error messages customizable

## Dependencies

- **Angular 17+** (Reactive Forms)
- **Lucide Angular** (Icons)
- **Laravel Sanctum** (API Authentication)
- **Modern CSS** (Grid, Flexbox)

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+
- Mobile browsers (iOS Safari, Chrome Mobile)

## Development Notes

### Testing
- Unit tests for form validation
- Integration tests for API calls
- E2E tests for booking flow

### Performance
- Lazy loading for better performance
- Optimized bundle size
- Efficient change detection

### Accessibility
- ARIA labels for screen readers
- Keyboard navigation support
- Focus management
- Color contrast compliance

## File Structure

```
booking/
├── booking-dialog.component.ts       # Main component logic
├── booking-dialog.component.html     # Multi-step template
├── booking-dialog.component.scss     # Styled responsive design
└── README.md                        # Documentation
```

## Future Enhancements

- [ ] Payment gateway integration (Stripe, PayPal)
- [ ] Booking confirmation emails
- [ ] Calendar integration
- [ ] Multi-language support
- [ ] Discount codes/coupons
- [ ] Booking modifications
- [ ] Cancellation handling
