<?php

namespace Tests\Feature\Assistant;

use App\Models\Models\Agence;
use App\Models\Models\Logement;
use App\Models\Models\Paiement;
use App\Models\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait BuildsBookings
{
    protected function makeClient(string $prenom = 'Youssef'): User
    {
        return User::create([
            'nom' => 'Alaoui',
            'prenom' => $prenom,
            'email' => Str::lower($prenom) . '.' . Str::random(6) . '@example.com',
            'mot_de_passe' => Hash::make('password'),
            'role' => 'client',
        ]);
    }

    /** @return array{0: User, 1: Agence} */
    protected function makeAgency(string $name = 'Atlas Voyages'): array
    {
        $account = User::create([
            'nom' => 'Agent',
            'prenom' => 'Samir',
            'email' => 'agence.' . Str::random(6) . '@example.com',
            'mot_de_passe' => Hash::make('password'),
            'role' => 'agence',
        ]);

        $agency = Agence::create([
            'id_utilisateur' => $account->id_utilisateur,
            'nom' => $name,
            'adresse' => 'Marrakech',
            'telephone' => '+212 5 24 00 00 00',
            'email' => 'contact@atlas.example',
            'score_classement' => 4.5,
        ]);

        return [$account, $agency];
    }

    protected function makeLogement(Agence $agency, array $attributes = []): Logement
    {
        return Logement::create(array_merge([
            'id_agence' => $agency->id_agence,
            'titre' => 'Hôtel Atlas Marrakech',
            'description' => 'Hôtel de test',
            'type' => 'hotel',
            'capacite' => 4,
            'adresse' => 'Avenue de la Ménara, Marrakech',
            'statut' => 'actif',
            'tarif_amount' => 600,
            'tarif_unit' => 'jour',
            'devise' => 'MAD',
        ], $attributes));
    }

    protected function makeReservation(User $client, Logement $logement, array $attributes = []): Reservation
    {
        return Reservation::create(array_merge([
            'id_utilisateur' => $client->id_utilisateur,
            'id_logement' => $logement->id_logement,
            'date_debut' => Carbon::now()->addDays(10)->setTime(14, 0),
            'date_fin' => Carbon::now()->addDays(13)->setTime(11, 0),
            'nb_personnes' => 2,
            'statut' => 'confirme',
            'montant_total' => 1800,
        ], $attributes));
    }

    protected function pay(Reservation $reservation, string $statut = 'regle'): Paiement
    {
        return Paiement::create([
            'id_reservation' => $reservation->id_reservation,
            'mode' => 'en_ligne',
            'montant' => $reservation->montant_total,
            'date_paiement' => Carbon::now(),
            'statut' => $statut,
            'reference' => 'PAY-TEST-' . Str::random(6),
        ]);
    }

    /**
     * Confirmed, paid reservation of a fresh client at a fresh agency.
     *
     * @return array{client: User, agencyAccount: User, agency: Agence, logement: Logement, reservation: Reservation}
     */
    protected function bookedStay(array $reservationAttributes = []): array
    {
        $client = $this->makeClient();
        [$agencyAccount, $agency] = $this->makeAgency();
        $logement = $this->makeLogement($agency);
        $reservation = $this->makeReservation($client, $logement, $reservationAttributes);
        $this->pay($reservation);

        return compact('client', 'agencyAccount', 'agency', 'logement', 'reservation');
    }
}
