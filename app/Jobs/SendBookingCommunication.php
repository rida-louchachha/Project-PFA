<?php

namespace App\Jobs;

use App\Services\Ai\Communication\BookingCommunicationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued TravelPlan AI booking communication. Carries ids only; the service re-reads
 * MySQL and checks eligibility + idempotency before sending anything.
 */
class SendBookingCommunication implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 3;

    /** Seconds between retries */
    public array $backoff = [60, 300];

    /** Keep the uniqueness lock for one hour */
    public int $uniqueFor = 3600;

    public function __construct(
        public readonly int $reservationId,
        public readonly string $type,
    ) {
    }

    public function uniqueId(): string
    {
        return "{$this->reservationId}:{$this->type}";
    }

    public function handle(BookingCommunicationService $service): void
    {
        $service->send($this->reservationId, $this->type);
    }
}
