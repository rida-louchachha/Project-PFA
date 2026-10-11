import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { AssistantLauncherComponent } from './assistant/assistant-launcher/assistant-launcher.component';

@Component({
  selector: 'app-root',
  imports: [RouterOutlet, AssistantLauncherComponent],
  templateUrl: './app.html',
  styleUrl: './app.scss'
})
export class App {
  protected title = 'frontend';
}
