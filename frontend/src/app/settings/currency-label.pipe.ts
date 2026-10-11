import { Pipe, PipeTransform, inject } from '@angular/core';
import { AppSettingsService } from './app-settings.service';

/**
 * {{ accommodation.devise | currencyLabel }}  ->  "DH" (configured display symbol for MAD)
 */
@Pipe({ name: 'currencyLabel', standalone: true })
export class CurrencyLabelPipe implements PipeTransform {
  private readonly settings = inject(AppSettingsService);

  transform(code: string | null | undefined): string {
    return this.settings.currencyLabel(code);
  }
}
