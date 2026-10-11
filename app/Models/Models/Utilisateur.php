<?php

namespace App\Models\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // optional if you want to auth this model
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Utilisateur extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\UtilisateurFactory::new();
    }

    protected $table = 'utilisateurs';
    protected $primaryKey = 'id_utilisateur';
    public $incrementing = true;
    protected $keyType = 'int';

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'mot_de_passe',
        'role'
    ];

    // If you use this model for auth, map password + hidden attributes
    protected $hidden = ['mot_de_passe', 'remember_token'];
    protected $casts = [];

    // Tell Laravel which attribute is the password column
    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    // Relationships
    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'id_utilisateur', 'id_utilisateur');
    }

    public function avis()
    {
        return $this->hasMany(Avis::class, 'id_utilisateur', 'id_utilisateur');
    }
}
