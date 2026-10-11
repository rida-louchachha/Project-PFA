<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Model;

class BookingNotification extends Model
{
    public const TYPE_BOOKING_CONFIRMATION = 'booking_confirmation';
    public const TYPE_INTENTION_REQUEST = 'intention_request';
    public const TYPE_INTENTION_FOLLOWUP = 'intention_followup';
    public const TYPE_ARRIVAL_REMINDER = 'arrival_reminder';

    public const TYPES = [
        self::TYPE_BOOKING_CONFIRMATION,
        self::TYPE_INTENTION_REQUEST,
        self::TYPE_INTENTION_FOLLOWUP,
        self::TYPE_ARRIVAL_REMINDER,
    ];

    public const STATUT_PROCESSING = 'processing';
    public const STATUT_SENT = 'sent';
    public const STATUT_FAILED = 'failed';

    protected $table = 'booking_notifications';
    protected $primaryKey = 'id_notification';

    protected $fillable = [
        'id_reservation',
        'type',
        'channel',
        'statut',
        'attempts',
        'content_source',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'id_reservation', 'id_reservation');
    }
}
