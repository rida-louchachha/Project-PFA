import {
  AfterViewChecked,
  Component,
  ElementRef,
  EventEmitter,
  Input,
  OnChanges,
  Output,
  SimpleChanges,
  ViewChild,
} from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { LucideAngularModule, Sparkles, Send, Headset, RefreshCw, TriangleAlert, Bot } from 'lucide-angular';
import { AssistantMessage } from '../assistant.service';
import { intentionLabel } from '../intention-badge/intention-badge.component';

/**
 * Presentational conversation thread + composer, shared by the customer chat
 * and the agency dashboard (viewer = 'agency' shows detected intents and audit data).
 */
@Component({
  selector: 'app-chat-thread',
  standalone: true,
  imports: [CommonModule, FormsModule, LucideAngularModule],
  templateUrl: './chat-thread.component.html',
  styleUrl: './chat-thread.component.scss',
})
export class ChatThreadComponent implements OnChanges, AfterViewChecked {
  @Input() messages: AssistantMessage[] = [];
  @Input() viewer: 'customer' | 'agency' = 'customer';
  @Input() sending = false;
  @Input() loading = false;
  @Input() errorMessage: string | null = null;
  @Input() quickReplies: string[] = [];
  @Input() placeholder = 'Écrivez votre message…';
  @Input() maxLength = 1000;
  @Input() disabled = false;
  @Input() compact = false;
  /** Label shown while waiting for the answer */
  @Input() typingLabel = 'TravelPlan AI rédige une réponse';

  @Output() send = new EventEmitter<string>();
  @Output() retry = new EventEmitter<void>();

  @ViewChild('scroller') private scroller?: ElementRef<HTMLDivElement>;

  protected draft = '';
  private shouldScroll = true;

  readonly Sparkles = Sparkles;
  readonly Send = Send;
  readonly Headset = Headset;
  readonly RefreshCw = RefreshCw;
  readonly TriangleAlert = TriangleAlert;
  readonly Bot = Bot;

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['messages'] || changes['sending']) {
      this.shouldScroll = true;
    }
  }

  ngAfterViewChecked(): void {
    if (this.shouldScroll && this.scroller) {
      const el = this.scroller.nativeElement;
      el.scrollTop = el.scrollHeight;
      this.shouldScroll = false;
    }
  }

  protected submit(): void {
    const content = this.draft.trim();
    if (!content || this.sending || this.disabled) {
      return;
    }
    this.send.emit(content);
    this.draft = '';
  }

  protected onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault();
      this.submit();
    }
  }

  protected useQuickReply(reply: string): void {
    if (this.sending || this.disabled) {
      return;
    }
    this.send.emit(reply);
  }

  protected isOwn(message: AssistantMessage): boolean {
    return this.viewer === 'customer' ? message.role === 'customer' : message.role === 'agent';
  }

  protected authorLabel(message: AssistantMessage): string {
    if (message.role === 'customer') {
      return this.viewer === 'customer' ? 'Vous' : 'Client';
    }
    return message.author ?? (message.role === 'agent' ? 'Conseiller' : 'TravelPlan AI');
  }

  protected kindLabel(kind: string): string | null {
    switch (kind) {
      case 'booking_confirmation': return 'Confirmation de réservation';
      case 'intention_request': return 'Demande de confirmation de venue';
      case 'intention_followup': return 'Relance';
      case 'arrival_reminder': return "Rappel d'arrivée";
      default: return null;
    }
  }

  protected intentLabel(intent: string | null | undefined): string {
    return intentionLabel(intent);
  }

  protected toolNames(message: AssistantMessage): string {
    return (message.audit?.tools ?? []).map(t => t.name + (t.ok ? '' : ' ✕')).join(', ');
  }

  protected formatTime(value: string): string {
    const date = new Date(value);
    if (isNaN(date.getTime())) {
      return '';
    }
    const today = new Date();
    const sameDay = date.toDateString() === today.toDateString();
    return sameDay
      ? date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
      : date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short' }) + ' · ' +
        date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }

  protected trackById(_: number, message: AssistantMessage): number {
    return message.id;
  }
}
