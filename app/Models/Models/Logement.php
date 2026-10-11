<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Logement extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\LogementFactory::new();
    }

    protected $table = 'logements';
    protected $primaryKey = 'id_logement';

    protected $fillable = [
        'id_agence',
        'titre',
        'description',
        'type',
        'capacite',
        'adresse',
        'photo_url',
        'statut',
        'tarif_amount',
        'tarif_unit',
        'devise'
    ];

    protected $casts = [
        'tarif_amount' => 'decimal:2',
        'capacite' => 'integer',
    ];

    public function agence()
    {
        return $this->belongsTo(Agence::class, 'id_agence', 'id_agence');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'id_logement', 'id_logement');
    }
}
