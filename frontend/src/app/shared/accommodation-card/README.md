# AccommodationCard Component

A reusable Angular component for displaying accommodation listings with modern UI and Lucide icons.

## Features

- ✨ Beautiful card design with hover effects
- 🎨 Lucide icons for better visual hierarchy
- 📱 Responsive design
- 🔧 Highly configurable with inputs
- 📡 Event-driven architecture with outputs
- 🎯 Compact mode for dense layouts

## Usage

### Basic Example

```html
<app-accommodation-card
  [accommodation]="accommodationData"
  [showActions]="true"
  [compactMode]="false"
  (favorite)="onFavorite($event)"
  (viewDetails)="onViewDetails($event)"
  (bookNow)="onBookNow($event)">
</app-accommodation-card>
```

### Component Inputs

| Input | Type | Default | Description |
|-------|------|---------|-------------|
| `accommodation` | `Accommodation` | *required* | The accommodation data to display |
| `showActions` | `boolean` | `true` | Whether to show action buttons (View Details, Book Now) |
| `compactMode` | `boolean` | `false` | Enables compact layout with smaller image and padding |

### Component Outputs

| Output | Type | Description |
|--------|------|-------------|
| `favorite` | `EventEmitter<Accommodation>` | Emitted when favorite button is clicked |
| `viewDetails` | `EventEmitter<Accommodation>` | Emitted when "View Details" button is clicked |
| `bookNow` | `EventEmitter<Accommodation>` | Emitted when "Book Now" button is clicked |

### Examples

#### Favorites List (Compact Mode)
```html
<div class="favorites-grid">
  <app-accommodation-card
    *ngFor="let favorite of favoriteAccommodations"
    [accommodation]="favorite"
    [compactMode]="true"
    [showActions]="false"
    (favorite)="removeFavorite($event)"
    (viewDetails)="navigateToDetails($event)">
  </app-accommodation-card>
</div>
```

#### Search Results with Full Actions
```html
<div class="search-results">
  <app-accommodation-card
    *ngFor="let result of searchResults"
    [accommodation]="result"
    [showActions]="true"
    [compactMode]="false"
    (favorite)="toggleFavorite($event)"
    (viewDetails)="showDetails($event)"
    (bookNow)="startBooking($event)">
  </app-accommodation-card>
</div>
```

#### Related Accommodations (Compact, No Actions)
```html
<div class="related-accommodations">
  <h3>You might also like</h3>
  <div class="compact-grid">
    <app-accommodation-card
      *ngFor="let related of relatedAccommodations"
      [accommodation]="related"
      [compactMode]="true"
      [showActions]="false"
      (viewDetails)="navigateToAccommodation($event)">
    </app-accommodation-card>
  </div>
</div>
```

## Styling

The component includes its own SCSS styles and is fully self-contained. The card adapts to different layouts automatically:

- **Grid Layout**: Works with CSS Grid or Flexbox
- **Compact Mode**: Smaller image (160px vs 220px) and reduced padding
- **Responsive**: Automatically adapts to mobile screens

## Icons Used

- 🏢 Building (Hotels)
- 🏠 Home (Houses)
- 🛏️ Bed (Studios)
- ❤️ Heart (Favorites)
- ⭐ Star (Ratings)
- 📍 MapPin (Location)
- 👥 Users (Capacity)
- 💰 DollarSign (Price)
- 👁️ Eye (View Details)
- 📅 Calendar (Book Now)

## Data Structure

The component expects an `Accommodation` object with this structure:

```typescript
interface Accommodation {
  id: number;
  titre: string;
  description?: string;
  type: 'hotel' | 'maison' | 'studio';
  capacite: number;
  adresse?: string;
  photo_url?: string;
  statut: 'actif' | 'inactif';
  tarif_amount: string | number;
  tarif_unit: 'heure' | 'jour' | 'mois';
  devise: string;
  agence?: {
    nom: string;
    score_classement?: string | number;
  };
}
```
