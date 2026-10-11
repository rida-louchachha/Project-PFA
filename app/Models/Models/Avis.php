<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Avis extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\AvisFactory::new();
    }

    protected $table = 'avis';
    protected $primaryKey = 'id_avis';

    protected $fillable = [
        'id_utilisateur',
        'id_agence',
        'note',
        'commentaire',
        'date_avis'
    ];

    protected $casts = [
        'note' => 'integer',
        'date_avis' => 'date',
    ];

    public function utilisateur()
    {
        return $this->belongsTo(Utilisateur::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function agence()
    {
        return $this->belongsTo(Agence::class, 'id_agence', 'id_agence');
    }
}
