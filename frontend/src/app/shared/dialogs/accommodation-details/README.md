# Accommodation Details Dialog Component

A comprehensive, styled modal dialog for displaying detailed accommodation information with a centered layout and backdrop blur effect.

## Features

✅ **Professional Design**
- Centered modal with backdrop blur
- Modern card-based layout with sections
- Responsive design for all screen sizes
- Smooth animations (fade in, slide up)

✅ **Comprehensive Information Display**
- High-quality accommodation images
- Basic info (capacity, location, type)
- Full description
- Agency information with ratings
- Pricing details with currency
- Property status and additional details

✅ **User Interactions**
- Close dialog (X button, backdrop click, ESC key)
- Book accommodation directly from dialog
- Disabled booking for unavailable properties
- Mobile-friendly responsive actions

✅ **Accessibility**
- Keyboard navigation support
- Screen reader friendly
- Focus management
- Semantic HTML structure

## Usage

### Basic Implementation

```typescript
// In your component
import { AccommodationDetailsDialogComponent } from '../shared/dialogs/accommodation-details/accommodation-details-dialog.component';

@Component({
  // ... other config
  imports: [..., AccommodationDetailsDialogComponent]
})
export class YourComponent {
  showDialog = signal(false);
  selectedAccommodation = signal<Accommodation | null>(null);

  openDialog(accommodation: Accommodation) {
    this.selectedAccommodation.set(accommodation);
    this.showDialog.set(true);
  }

  closeDialog() {
    this.showDialog.set(false);
    this.selectedAccommodation.set(null);
  }

  handleBooking(accommodation: Accommodation) {
    // Handle booking logic
    console.log('Booking:', accommodation.titre);
    this.closeDialog();
  }
}
```

### Template Usage

```html
<app-accommodation-details-dialog
  *ngIf="selectedAccommodation()"
  [accommodation]="selectedAccommodation()!"
  [isOpen]="showDialog()"
  (closeDialog)="closeDialog()"
  (bookNow)="handleBooking($event)">
</app-accommodation-details-dialog>
```

## Props

### Inputs
- `accommodation: Accommodation` (required) - The accommodation data to display
- `isOpen: boolean` - Controls dialog visibility

### Outputs
- `closeDialog: EventEmitter<void>` - Emitted when dialog should close
- `bookNow: EventEmitter<Accommodation>` - Emitted when booking is requested

## Dialog Sections

### 1. Header
- Property type badge with icon
- Property title
- Status indicator (Available/Unavailable)
- Close button

### 2. Image Section
- Full-width accommodation image
- Error handling with fallback image

### 3. Information Sections
- **Basic Information**: Capacity and location with icons
- **Description**: Full property description in styled container
- **Agency Information**: Agency name, rating, and contact details
- **Pricing**: Large price display with currency and billing unit
- **Additional Details**: Property type, status, and listing date

### 4. Footer
- Close button
- Book Now button (disabled if unavailable)

## Styling

### Theme Colors
- Primary: `#3b82f6` (Blue)
- Success: `#166534` (Green)
- Warning: `#92400e` (Amber)
- Danger: `#991b1b` (Red)
- Neutral grays for backgrounds and text

### Responsive Breakpoints
- **Desktop**: Full-featured layout with grid sections
- **Tablet** (768px): Adjusted padding and single-column grids
- **Mobile** (480px): Full-screen dialog with stacked layout

### Animations
- **fadeIn**: Backdrop appears smoothly (0.3s)
- **slideUp**: Dialog slides up from bottom with scale effect (0.3s)

## Browser Support

- Modern browsers with CSS Grid support
- Mobile browsers (iOS Safari, Chrome Mobile)
- Backdrop-filter support for blur effect
- Fallback styling for older browsers

## Dependencies

- Angular 17+ (Standalone components)
- Lucide Angular (Icons)
- Modern CSS features (Grid, Flexbox, backdrop-filter)

## File Structure

```
accommodation-details/
├── accommodation-details-dialog.component.ts    # Component logic
├── accommodation-details-dialog.component.html  # Template
├── accommodation-details-dialog.component.scss  # Styles
└── README.md                                    # Documentation
```
