import { bootstrapApplication } from '@angular/platform-browser';
import { registerLocaleData } from '@angular/common';
import localeFr from '@angular/common/locales/fr';
import { appConfig } from './app/app.config';
import { App } from './app/app';

// French number / date formatting ("1 950", "lun. 13 oct.") used by the TravelPlan AI screens
registerLocaleData(localeFr, 'fr');

bootstrapApplication(App, appConfig)
  .catch((err) => console.error(err));
