import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { LucideAngularModule, Star, X, Loader2 } from 'lucide-angular';

@Component({
  selector: 'app-review-dialog',
  standalone: true,
  imports: [CommonModule, FormsModule, LucideAngularModule],
  templateUrl: './review-dialog.component.html',
  styleUrl: './review-dialog.component.scss'
})
export class ReviewDialogComponent {
  @Input() isOpen: boolean = false;
  @Input() agencyName: string = '';
  @Output() close = new EventEmitter<void>();
  @Output() submit = new EventEmitter<{ rating: number; comment: string }>();

  readonly Star = Star;
  readonly X = X;
  readonly Loader2 = Loader2;

  protected hoverRating = 0;
  protected rating = 5;
  protected comment = '';
  protected isSubmitting = false;

  protected setHover(value: number): void {
    this.hoverRating = value;
  }

  protected setRating(value: number): void {
    this.rating = value;
  }

  protected onClose(): void {
    if (!this.isSubmitting) this.close.emit();
  }

  protected async onSubmit(): Promise<void> {
    if (this.isSubmitting) return;
    this.isSubmitting = true;
    try {
      this.submit.emit({ rating: this.rating, comment: this.comment.trim() });
    } finally {
      this.isSubmitting = false;
    }
  }
}


