<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Customer arrival intention for a reservation (separate from reservations.statut).
 */
class ReservationConfirmation extends Model
{
    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const DECLINED = 'declined';
    public const UNCERTAIN = 'uncertain';
    public const MODIFICATION_REQUESTED = 'modification_requested';
    public const HUMAN_ASSISTANCE_REQUIRED = 'human_assistance_required';

    /** Intentions the AI classifier is allowed to record. */
    public const RECORDABLE = [
        self::CONFIRMED,
        self::DECLINED,
        self::UNCERTAIN,
        self::MODIFICATION_REQUESTED,
        self::HUMAN_ASSISTANCE_REQUIRED,
    ];

    /** Intentions that need an action from the agency. */
    public const NEEDS_AGENCY_ATTENTION = [
        self::DECLINED,
        self::MODIFICATION_REQUESTED,
        self::HUMAN_ASSISTANCE_REQUIRED,
    ];

    protected $table = 'reservation_confirmations';
    protected $primaryKey = 'id_confirmation';

    protected $fillable = [
        'id_reservation',
        'intention',
        'confidence',
        'source',
        'id_message',
        'requested_at',
        'responded_at',
    ];

    protected $casts = [
        'confidence' => 'decimal:2',
        'requested_at' => 'datetime',
        'responded_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class, 'id_reservation', 'id_reservation');
    }

    public function isAnswered(): bool
    {
        return $this->intention !== self::PENDING;
    }
}
