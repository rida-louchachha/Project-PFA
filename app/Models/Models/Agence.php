<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agence extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\AgenceFactory::new();
    }

    protected $table = 'agences';
    protected $primaryKey = 'id_agence';

    protected $fillable = [
        'id_utilisateur',
        'nom',
        'adresse',
        'telephone',
        'email',
        'description',
        'score_classement'
    ];

    public function logements()
    {
        return $this->hasMany(Logement::class, 'id_agence', 'id_agence');
    }

    public function avis()
    {
        return $this->hasMany(Avis::class, 'id_agence', 'id_agence');
    }

    /** Agency account (role = agence) that owns this agency */
    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'id_utilisateur', 'id_utilisateur');
    }
}
