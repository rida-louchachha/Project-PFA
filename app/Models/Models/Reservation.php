<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\ReservationFactory::new();
    }

    protected $table = 'reservations';
    protected $primaryKey = 'id_reservation';

    protected $fillable = [
        'id_utilisateur',
        'id_logement',
        'date_debut',
        'date_fin',
        'nb_personnes',
        'statut',
        'montant_total'
    ];

    protected $casts = [
        'date_debut' => 'datetime',
        'date_fin'   => 'datetime',
        'montant_total' => 'decimal:2',
        'nb_personnes' => 'integer',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function logement()
    {
        return $this->belongsTo(Logement::class, 'id_logement', 'id_logement');
    }

    public function paiement()
    {
        return $this->hasOne(Paiement::class, 'id_reservation', 'id_reservation');
    }

    /** Customer arrival intention collected by TravelPlan AI */
    public function confirmation()
    {
        return $this->hasOne(ReservationConfirmation::class, 'id_reservation', 'id_reservation');
    }

    public function conversations()
    {
        return $this->hasMany(AiConversation::class, 'id_reservation', 'id_reservation');
    }

    public function bookingNotifications()
    {
        return $this->hasMany(BookingNotification::class, 'id_reservation', 'id_reservation');
    }
}
