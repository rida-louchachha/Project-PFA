<?php

namespace App\Console\Commands;

use App\Jobs\SendBookingCommunication;
use App\Models\Models\BookingNotification;
use App\Models\Models\Reservation;
use App\Models\Models\ReservationConfirmation;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Scheduled every 15 minutes (routes/console.php). Selects candidate reservations and
 * queues reminders; the job re-checks eligibility and idempotency before sending.
 */
class SendBookingReminders extends Command
{
    protected $signature = 'travelplan:send-reminders {--dry-run : List what would be queued without dispatching}';

    protected $description = 'Queue TravelPlan AI intention requests, follow-ups and arrival reminders';

    public function handle(): int
    {
        $now = Carbon::now();
        $timings = config('ai.reminders');

        $plans = [
            BookingNotification::TYPE_INTENTION_REQUEST => $this->upcoming($now, $timings['intention_request_hours_before'])
                ->where(fn (Builder $q) => $q
                    ->whereDoesntHave('confirmation')
                    ->orWhereHas('confirmation', fn (Builder $c) => $c->where('intention', ReservationConfirmation::PENDING))),

            BookingNotification::TYPE_INTENTION_FOLLOWUP => $this->upcoming($now, null)
                ->whereHas('confirmation', fn (Builder $c) => $c
                    ->where('intention', ReservationConfirmation::PENDING)
                    ->whereNotNull('requested_at')
                    ->where('requested_at', '<=', $now->copy()->subHours($timings['followup_after_hours']))),

            BookingNotification::TYPE_ARRIVAL_REMINDER => $this->upcoming($now, $timings['arrival_reminder_hours_before'])
                ->where(fn (Builder $q) => $q
                    ->whereDoesntHave('confirmation')
                    ->orWhereHas('confirmation', fn (Builder $c) => $c->where('intention', '!=', ReservationConfirmation::DECLINED))),
        ];

        foreach ($plans as $type => $query) {
            $ids = $query
                ->whereDoesntHave('bookingNotifications', fn (Builder $n) => $n
                    ->where('type', $type)
                    ->where('statut', BookingNotification::STATUT_SENT))
                ->pluck('id_reservation');

            foreach ($ids as $id) {
                if (!$this->option('dry-run')) {
                    SendBookingCommunication::dispatch((int) $id, $type);
                }
            }

            $this->line(sprintf('%-20s %d reservation(s)%s', $type, $ids->count(), $this->option('dry-run') ? ' (dry run)' : ' queued'));
        }

        return self::SUCCESS;
    }

    /**
     * Confirmed & paid stays starting in the future (and within $withinHours, if given).
     * Cancelled reservations are excluded here and re-checked in the job.
     */
    private function upcoming(Carbon $now, ?int $withinHours): Builder
    {
        $query = Reservation::query()
            ->where('statut', 'confirme')
            ->whereHas('paiement', fn (Builder $p) => $p->where('statut', 'regle'))
            ->where('date_debut', '>', $now);

        if ($withinHours !== null) {
            $query->where('date_debut', '<=', $now->copy()->addHours($withinHours));
        }

        return $query;
    }
}
