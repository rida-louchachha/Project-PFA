import { Component, EventEmitter, Input, Output, OnChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import { Accommodation } from '../../../accommodation/accommodation';

@Component({
  selector: 'app-edit-accommodation-dialog',
  standalone: true,
  imports: [CommonModule, FormsModule, LucideAngularModule],
  templateUrl: './edit-accommodation-dialog.component.html',
  styleUrl: './edit-accommodation-dialog.component.scss'
})
export class EditAccommodationDialogComponent implements OnChanges {
  @Input() isOpen: boolean = false;
  @Input() value?: Accommodation;
  @Input() mode: 'create' | 'edit' = 'edit';
  @Output() cancel = new EventEmitter<void>();
  @Output() save = new EventEmitter<Partial<Accommodation>>();

  protected model: Partial<Accommodation> = {};

  ngOnChanges(): void {
    if (this.value) {
      const { titre, description, type, capacite, adresse, tarif_amount, tarif_unit, devise, statut } = this.value;
      this.model = { titre, description, type, capacite, adresse, tarif_amount, tarif_unit, devise, statut };
    } else {
      // Defaults for create mode
      this.model = {
        titre: '',
        description: '',
        type: 'maison',
        capacite: 1,
        adresse: '',
        tarif_amount: 0,
        tarif_unit: 'jour',
        devise: 'MAD',
        statut: 'actif'
      } as Partial<Accommodation>;
    }
  }
}


