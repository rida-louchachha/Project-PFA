<?php

namespace Database\Seeders;

use App\Models\Models\Agence;
use App\Models\Models\Logement;
use App\Models\Models\Utilisateur;
use Illuminate\Database\Seeder;

/**
 * Links agency accounts to agences (ownership model used by TravelPlan AI) and makes
 * sure the demo agency (agence@example.com / password) has bookable listings.
 *
 * Idempotent: safe to run on an already seeded database:
 *   php artisan db:seed --class=AgencyOwnershipSeeder
 *
 * It only creates catalogue data (agency + listings). Reservations, payments and
 * confirmations are created through the application during the demo.
 */
class AgencyOwnershipSeeder extends Seeder
{
    public function run(): void
    {
        $demoAccount = Utilisateur::where('email', 'agence@example.com')->first();

        if ($demoAccount) {
            $demoAgency = Agence::where('id_utilisateur', $demoAccount->id_utilisateur)->first()
                ?? Agence::whereNull('id_utilisateur')->orderBy('id_agence')->first()
                ?? new Agence();

            $demoAgency->fill([
                'id_utilisateur' => $demoAccount->id_utilisateur,
                'nom' => 'Atlas Voyages',
                'adresse' => '12 avenue Mohammed V, Marrakech',
                'telephone' => '+212 5 24 00 00 00',
                'email' => 'contact@atlas-voyages.example',
                'description' => "Agence de démonstration TravelPlan (séjours à Marrakech et dans l'Atlas).",
                'score_classement' => $demoAgency->score_classement ?? 4.6,
            ])->save();

            $listings = [
                ['titre' => 'Hôtel Atlas Marrakech', 'type' => 'hotel', 'capacite' => 4, 'adresse' => 'Avenue de la Ménara, Marrakech', 'tarif_amount' => 650, 'photo' => 'photo-1566073771259-6a8506099945'],
                ['titre' => 'Riad Jardin de la Médina', 'type' => 'maison', 'capacite' => 6, 'adresse' => 'Derb Sidi Bouloukat, Médina, Marrakech', 'tarif_amount' => 900, 'photo' => 'photo-1568605114967-8130f3a36994'],
                ['titre' => 'Studio Gueliz Moderne', 'type' => 'studio', 'capacite' => 2, 'adresse' => 'Rue de la Liberté, Gueliz, Marrakech', 'tarif_amount' => 380, 'photo' => 'photo-1522708323590-d24dbb6b0267'],
            ];

            foreach ($listings as $listing) {
                Logement::updateOrCreate(
                    ['id_agence' => $demoAgency->id_agence, 'titre' => $listing['titre']],
                    [
                        'description' => 'Logement de démonstration proposé par Atlas Voyages.',
                        'type' => $listing['type'],
                        'capacite' => $listing['capacite'],
                        'adresse' => $listing['adresse'],
                        'photo_url' => "https://images.unsplash.com/{$listing['photo']}?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=60",
                        'statut' => 'actif',
                        'tarif_amount' => $listing['tarif_amount'],
                        'tarif_unit' => 'jour',
                        'devise' => 'MAD',
                    ],
                );
            }
        }

        // Other agency accounts: link each to a free agence (one-to-one)
        Utilisateur::where('role', 'agence')
            ->whereNotIn('id_utilisateur', Agence::whereNotNull('id_utilisateur')->pluck('id_utilisateur'))
            ->orderBy('id_utilisateur')
            ->get()
            ->each(function (Utilisateur $account) {
                $agency = Agence::whereNull('id_utilisateur')->orderBy('id_agence')->first();
                $agency?->forceFill(['id_utilisateur' => $account->id_utilisateur])->save();
            });
    }
}
