<?php

namespace Database\Seeders;

use App\Models\Models\Agence;
use App\Models\Models\Avis;
use App\Models\Models\Logement;
use App\Models\Models\Paiement;
use App\Models\Models\Reservation;
use App\Models\Models\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Coherent Moroccan demo dataset for TravelPlan (agencies, riads/hotels/studios,
 * Moroccan customers, bookings, payments and reviews), with dates relative to today
 * so the TravelPlan AI scenarios (confirmation, arrival reminders...) always work.
 *
 * Idempotent: catalogue rows are matched by email / title, and bookings are only
 * generated for client accounts that have none. It also completes accounts created
 * through the app: clients get sample bookings, agency accounts get an agency.
 *
 *   php artisan db:seed --class=MoroccanDemoSeeder
 *
 * All accounts use the password "password". Booking-confirmation states and AI
 * conversations are NOT seeded: they are produced by the application itself.
 */
class MoroccanDemoSeeder extends Seeder
{
    /** Photos already used by the project (known to load) */
    private const PHOTOS = [
        'hotel' => ['photo-1566073771259-6a8506099945', 'photo-1571003123894-1f0594d2b5d9', 'photo-1574362848149-11496d93a7c7', 'photo-1582063289852-62e3ba2747f8'],
        'maison' => ['photo-1568605114967-8130f3a36994', 'photo-1564013799919-ab600027ffc6', 'photo-1449824913935-59a10b8d2000', 'photo-1561501900-3701fa6a0864'],
        'studio' => ['photo-1522708323590-d24dbb6b0267', 'photo-1502672260266-1c1ef2d93688', 'photo-1545324418-cc1a3fa10c00', 'photo-1586023492125-27b2c045efd7'],
    ];

    private const AGENCIES = [
        [
            'account' => ['email' => 'agence@example.com', 'prenom' => 'Samir', 'nom' => 'Bennis'],
            'nom' => 'Atlas Voyages', 'adresse' => '12 avenue Mohammed V, Guéliz, Marrakech', 'telephone' => '+212 5 24 43 21 10',
            'email' => 'contact@atlasvoyages.example', 'score' => 4.7,
            'description' => "Agence marrakchie spécialisée dans les séjours en riad, en hôtel et dans la Palmeraie.",
            'logements' => [
                ['Hôtel Atlas Marrakech', 'hotel', 4, 'Avenue de la Ménara, Hivernage, Marrakech', 650, "Hôtel 4 étoiles avec piscine et vue sur l'Atlas, à dix minutes de la place Jemaa el-Fna."],
                ['Riad Jardin de la Médina', 'maison', 6, 'Derb Sidi Bouloukat, Médina, Marrakech', 900, 'Riad traditionnel avec patio arboré, fontaine en zellige et terrasse sur les toits de la médina.'],
                ['Studio Guéliz Moderne', 'studio', 2, 'Rue de la Liberté, Guéliz, Marrakech', 380, 'Studio lumineux et climatisé au cœur du quartier moderne, proche des cafés et du tramway.'],
                ['Villa Palmeraie Oasis', 'maison', 8, 'Circuit de la Palmeraie, Marrakech', 2200, 'Villa privée avec jardin de palmiers, piscine chauffée et hammam, idéale pour les familles.'],
            ],
        ],
        [
            'account' => ['email' => 'sahara@example.com', 'prenom' => 'Hamid', 'nom' => 'Ait Lahcen'],
            'nom' => 'Sahara Dunes Expéditions', 'adresse' => '45 avenue Mohammed V, Ouarzazate', 'telephone' => '+212 5 24 88 47 32',
            'email' => 'contact@saharadunes.example', 'score' => 4.8,
            'description' => 'Bivouacs dans les dunes de Merzouga, kasbahs et circuits dans la vallée du Drâa.',
            'logements' => [
                ['Camp Luxe Erg Chebbi', 'hotel', 4, 'Dunes de l\'Erg Chebbi, Merzouga', 1200, 'Tentes berbères de luxe au pied des dunes, dîner sous les étoiles et balade à dos de dromadaire.'],
                ['Kasbah Aït Ben Haddou', 'maison', 6, 'Ksar Aït Ben Haddou, Ouarzazate', 850, 'Kasbah en pisé restaurée face au ksar classé au patrimoine mondial de l\'UNESCO.'],
                ['Hôtel Dar Ouarzazate', 'hotel', 3, 'Boulevard Mohammed V, Ouarzazate', 520, 'Hôtel familial proche des studios de cinéma Atlas et de la kasbah de Taourirt.'],
                ['Maison d\'hôtes Vallée du Dadès', 'maison', 5, 'Route des Gorges du Dadès, Boumalne Dadès', 600, 'Maison d\'hôtes avec vue sur les gorges, petit-déjeuner berbère inclus.'],
            ],
        ],
        [
            'account' => ['email' => 'chefchaouen@example.com', 'prenom' => 'Laila', 'nom' => 'Bakkali'],
            'nom' => 'Chefchaouen Bleu Voyages', 'adresse' => 'Place Outa el Hammam, Chefchaouen', 'telephone' => '+212 5 39 98 61 05',
            'email' => 'contact@chaouenbleu.example', 'score' => 4.6,
            'description' => 'Séjours dans la perle bleue du Rif et randonnées dans le parc national de Talassemtane.',
            'logements' => [
                ['Riad Bleu Chefchaouen', 'maison', 4, 'Médina, Chefchaouen', 550, 'Riad aux murs bleus avec terrasse panoramique sur la médina et les montagnes du Rif.'],
                ['Studio Ras El Ma', 'studio', 2, 'Quartier Ras El Ma, Chefchaouen', 300, 'Petit studio cosy à deux pas de la cascade de Ras El Ma.'],
                ['Hôtel Panorama Rif', 'hotel', 3, 'Avenue Hassan II, Chefchaouen', 450, 'Hôtel avec vue sur la vallée, actuellement en rénovation.', 'inactif'],
            ],
        ],
        [
            'account' => ['email' => 'essaouira@example.com', 'prenom' => 'Yassine', 'nom' => 'Mouline'],
            'nom' => 'Océan Essaouira Travel', 'adresse' => '8 rue Mohammed El Qorry, Essaouira', 'telephone' => '+212 5 24 47 35 18',
            'email' => 'contact@oceanessaouira.example', 'score' => 4.5,
            'description' => 'La cité des alizés : riads de la médina, surf et festival Gnaoua.',
            'logements' => [
                ['Riad Mogador', 'maison', 5, 'Rue Laalouj, Médina, Essaouira', 700, 'Riad de charme dans la médina classée, à deux minutes des remparts et de la plage.'],
                ['Studio Vue Port', 'studio', 2, 'Skala du Port, Essaouira', 420, 'Studio avec vue sur le port de pêche et les bateaux bleus.'],
                ['Hôtel Alizés Océan', 'hotel', 3, 'Boulevard Mohammed V, Essaouira', 600, 'Hôtel face à l\'océan, idéal pour le kitesurf et les balades sur la plage.'],
            ],
        ],
        [
            'account' => ['email' => 'fes@example.com', 'prenom' => 'Zineb', 'nom' => 'Kettani'],
            'nom' => 'Fès Médina Tours', 'adresse' => '3 Talaa Kebira, Fès el Bali, Fès', 'telephone' => '+212 5 35 63 72 41',
            'email' => 'contact@fesmedina.example', 'score' => 4.6,
            'description' => 'Visites guidées de la plus ancienne médina du monde et séjours en riads historiques.',
            'logements' => [
                ['Riad Fès el Bali', 'maison', 6, 'Talaa Kebira, Fès el Bali, Fès', 800, 'Riad historique aux plafonds en cèdre sculpté, proche de la médersa Bou Inania.'],
                ['Hôtel Palais Batha', 'hotel', 4, 'Place Batha, Fès', 750, 'Hôtel élégant à l\'entrée de la médina, avec piscine et restaurant marocain.'],
                ['Studio Ville Nouvelle Fès', 'studio', 2, 'Avenue Hassan II, Fès', 320, 'Studio moderne dans la ville nouvelle, proche de la gare et des commerces.'],
            ],
        ],
        [
            'account' => ['email' => 'agadir@example.com', 'prenom' => 'Brahim', 'nom' => 'Oubella'],
            'nom' => 'Agadir Sun Travel', 'adresse' => 'Boulevard du 20 Août, Agadir', 'telephone' => '+212 5 28 84 29 63',
            'email' => 'contact@agadirsun.example', 'score' => 4.4,
            'description' => 'Séjours balnéaires à Agadir et Taghazout, surf et excursions à Paradise Valley.',
            'logements' => [
                ['Hôtel Baie d\'Agadir', 'hotel', 4, 'Boulevard du 20 Août, Agadir', 900, 'Hôtel en front de mer avec accès direct à la plage et spa.'],
                ['Appartement Marina Agadir', 'studio', 3, 'Marina d\'Agadir, Agadir', 650, 'Appartement avec balcon sur la marina, à pied des restaurants et de la corniche.'],
                ['Villa Taghazout Surf', 'maison', 6, 'Taghazout Bay, Taghazout', 1500, 'Villa face aux spots de surf de Taghazout, terrasse et barbecue.'],
            ],
        ],
        [
            'account' => ['email' => 'tanger@example.com', 'prenom' => 'Soufiane', 'nom' => 'Temsamani'],
            'nom' => 'Tanger Détroit Voyages', 'adresse' => '21 boulevard Pasteur, Tanger', 'telephone' => '+212 5 39 94 10 77',
            'email' => 'contact@tangerdetroit.example', 'score' => 4.3,
            'description' => 'Entre Méditerranée et Atlantique : kasbah, grottes d\'Hercule et cap Spartel.',
            'logements' => [
                ['Hôtel Kasbah Tanger', 'hotel', 3, 'Rue de la Kasbah, Tanger', 680, 'Hôtel dans la kasbah avec terrasse sur le détroit de Gibraltar.'],
                ['Studio Malabata Vue Mer', 'studio', 2, 'Avenue Mohammed VI, Malabata, Tanger', 450, 'Studio avec vue sur la baie de Tanger, proche de la plage de Malabata.'],
                ['Riad Cap Spartel', 'maison', 5, 'Route du Cap Spartel, Tanger', 950, 'Maison de caractère près du phare du cap Spartel et des grottes d\'Hercule.'],
            ],
        ],
        [
            'account' => ['email' => 'casarabat@example.com', 'prenom' => 'Meryem', 'nom' => 'Squalli'],
            'nom' => 'Casa Rabat Business Stays', 'adresse' => 'Boulevard d\'Anfa, Casablanca', 'telephone' => '+212 5 22 36 58 90',
            'email' => 'contact@casarabat.example', 'score' => 4.2,
            'description' => 'Hébergements pour voyages d\'affaires et city-breaks à Casablanca et Rabat.',
            'logements' => [
                ['Hôtel Corniche Aïn Diab', 'hotel', 3, 'Boulevard de la Corniche, Aïn Diab, Casablanca', 1100, 'Hôtel sur la corniche, à quelques minutes de la mosquée Hassan II.'],
                ['Studio Maârif Casablanca', 'studio', 2, 'Rue Ibnou Mounir, Maârif, Casablanca', 500, 'Studio équipé pour les voyages d\'affaires, quartier commerçant du Maârif.'],
                ['Appartement Agdal Rabat', 'studio', 4, 'Avenue Fal Ould Oumeir, Agdal, Rabat', 600, 'Appartement moderne dans le quartier de l\'Agdal, proche du tramway.'],
                ['Riad Oudayas Rabat', 'maison', 4, 'Kasbah des Oudayas, Rabat', 750, 'Riad blanc et bleu dans la kasbah des Oudayas, vue sur l\'embouchure du Bouregreg.'],
            ],
        ],
    ];

    private const CLIENTS = [
        ['client@example.com', 'Youssef', 'El Amrani'],
        ['salma.benali@example.com', 'Salma', 'Benali'],
        ['karim.tazi@example.com', 'Karim', 'Tazi'],
        ['imane.chraibi@example.com', 'Imane', 'Chraibi'],
        ['mehdi.alaoui@example.com', 'Mehdi', 'Alaoui'],
        ['nadia.bennani@example.com', 'Nadia', 'Bennani'],
        ['omar.idrissi@example.com', 'Omar', 'Idrissi'],
        ['hajar.lahlou@example.com', 'Hajar', 'Lahlou'],
        ['amine.berrada@example.com', 'Amine', 'Berrada'],
        ['sara.elfassi@example.com', 'Sara', 'El Fassi'],
        ['rachid.ouazzani@example.com', 'Rachid', 'Ouazzani'],
        ['khadija.naciri@example.com', 'Khadija', 'Naciri'],
    ];

    private const REVIEWS = [
        [5, "Accueil chaleureux et logement impeccable, exactement comme sur les photos. Nous reviendrons !"],
        [5, "Séjour inoubliable, l'agence a été très réactive et le petit-déjeuner marocain était délicieux."],
        [4, "Très bon emplacement et personnel attentionné. Seul bémol : un peu de bruit le soir."],
        [4, "Bon rapport qualité-prix, check-in rapide et conseils utiles pour visiter la ville."],
        [5, "Magnifique riad, propre et calme. Merci pour le thé à la menthe à l'arrivée !"],
        [3, "Correct dans l'ensemble, mais la climatisation fonctionnait mal pendant notre séjour."],
    ];

    private const PAYMENT_MODES = ['en_ligne', 'terminal', 'especes'];

    /** @var list<Logement> */
    private array $catalogue = [];

    private int $sequence = 0;

    public function run(): void
    {
        $password = Hash::make('password');

        // 1. Agencies + their accounts + listings
        foreach (self::AGENCIES as $index => $data) {
            $account = Utilisateur::updateOrCreate(
                ['email' => $data['account']['email']],
                ['prenom' => $data['account']['prenom'], 'nom' => $data['account']['nom'], 'mot_de_passe' => $password, 'role' => 'agence'],
            );

            $agency = Agence::where('id_utilisateur', $account->id_utilisateur)->first()
                ?? Agence::firstOrNew(['email' => $data['email']]);
            $agency->fill([
                'id_utilisateur' => $account->id_utilisateur,
                'nom' => $data['nom'],
                'adresse' => $data['adresse'],
                'telephone' => $data['telephone'],
                'email' => $data['email'],
                'description' => $data['description'],
                'score_classement' => $data['score'],
            ])->save();

            foreach ($data['logements'] as $position => $listing) {
                $this->catalogue[] = $this->listing($agency, $listing, $index + $position);
            }
        }

        // 2. Moroccan clients
        $clients = [];
        foreach (self::CLIENTS as [$email, $prenom, $nom]) {
            $clients[] = Utilisateur::updateOrCreate(
                ['email' => $email],
                ['prenom' => $prenom, 'nom' => $nom, 'mot_de_passe' => $password, 'role' => 'client'],
            );
        }

        // 3. Bookings for every client account that has none (seeded clients and app-registered ones)
        $bookable = array_values(array_filter($this->allListings(), fn (Logement $l) => $l->statut === 'actif'));
        Utilisateur::where('role', 'client')
            ->whereDoesntHave('reservations')
            ->orderBy('id_utilisateur')
            ->get()
            ->each(fn (Utilisateur $client) => $this->bookingsFor($client, $bookable));

        // 4. Agency accounts created through the app without an agency: give them one (with bookings)
        Utilisateur::where('role', 'agence')
            ->whereNotIn('id_utilisateur', Agence::whereNotNull('id_utilisateur')->pluck('id_utilisateur'))
            ->get()
            ->each(fn (Utilisateur $account) => $this->agencyFor($account, $password, $clients));

        // 5. Agency rating = average of its reviews
        Agence::with('avis')->get()->each(function (Agence $agency) {
            if ($agency->avis->isNotEmpty()) {
                $agency->forceFill(['score_classement' => round($agency->avis->avg('note'), 2)])->save();
            }
        });
    }

    /**
     * @param  array{0: string, 1: string, 2: int, 3: string, 4: int, 5: string, 6?: string}  $listing
     */
    private function listing(Agence $agency, array $listing, int $photoIndex): Logement
    {
        [$titre, $type, $capacite, $adresse, $tarif, $description] = $listing;
        $photos = self::PHOTOS[$type];
        $photo = $photos[$photoIndex % count($photos)];

        return Logement::updateOrCreate(
            ['id_agence' => $agency->id_agence, 'titre' => $titre],
            [
                'description' => $description,
                'type' => $type,
                'capacite' => $capacite,
                'adresse' => $adresse,
                'photo_url' => "https://images.unsplash.com/{$photo}?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=60",
                'statut' => $listing[6] ?? 'actif',
                'tarif_amount' => $tarif,
                'tarif_unit' => 'jour',
                'devise' => 'MAD',
            ],
        );
    }

    /** @return list<Logement> */
    private function allListings(): array
    {
        return Logement::whereIn('id_agence', Agence::whereNotNull('id_utilisateur')->pluck('id_agence'))
            ->orderBy('id_logement')
            ->get()
            ->all();
    }

    /**
     * Agency created for an app-registered agency account, with listings and a few bookings
     * so its TravelPlan AI dashboard is not empty.
     *
     * @param  list<Utilisateur>  $clients
     */
    private function agencyFor(Utilisateur $account, string $password, array $clients): void
    {
        $agency = Agence::create([
            'id_utilisateur' => $account->id_utilisateur,
            'nom' => 'Agence ' . trim($account->prenom . ' ' . $account->nom),
            'adresse' => 'Avenue Mohammed VI, Hay Riad, Rabat',
            'telephone' => '+212 5 37 71 20 45',
            'email' => $account->email,
            'description' => 'Agence de voyage à Rabat : séjours à Rabat, Salé et Kénitra.',
            'score_classement' => 4.5,
        ]);

        $listings = [
            $this->listing($agency, ['Riad Médina de Salé', 'maison', 5, 'Bab Lamrissa, Médina, Salé', 620, 'Riad familial dans la médina de Salé, face à la marina du Bouregreg.'], 1),
            $this->listing($agency, ['Studio Hay Riad Rabat', 'studio', 2, 'Avenue Annakhil, Hay Riad, Rabat', 450, 'Studio moderne et calme dans le quartier d\'affaires de Hay Riad.'], 2),
            $this->listing($agency, ['Hôtel Sebou Kénitra', 'hotel', 3, 'Boulevard Mohammed V, Kénitra', 480, 'Hôtel confortable proche de la gare LGV et de la plage de Mehdia.'], 3),
        ];

        // A few seeded customers booked this agency (upcoming soon, later, past)
        foreach ([[0, 2, 3], [1, 15, 2], [2, -30, 3]] as $k => [$listingIndex, $startInDays, $nights]) {
            $client = $clients[(8 + $k) % count($clients)];
            $this->book($client, $listings[$listingIndex], $startInDays, $nights, 2, 'confirme', true);
        }
    }

    /**
     * Sample bookings for one client: a stay in 1-3 days (AI arrival confirmation),
     * a later stay, a past stay with a review, and sometimes an unpaid or cancelled one.
     *
     * @param  list<Logement>  $listings
     */
    private function bookingsFor(Utilisateur $client, array $listings): void
    {
        $i = $this->sequence++;
        $n = count($listings);
        $pick = fn (int $offset) => $listings[($i * 3 + $offset) % $n];

        // Demo client "Youssef": the scenario of the specification (Hôtel Atlas Marrakech)
        if ($client->email === 'client@example.com') {
            $byTitle = fn (string $t) => collect($listings)->firstWhere('titre', $t) ?? $pick(0);
            $this->book($client, $byTitle('Hôtel Atlas Marrakech'), 2, 3, 2, 'confirme', true);
            $this->book($client, $byTitle('Riad Mogador'), 25, 4, 3, 'confirme', true);
            $this->book($client, $byTitle('Camp Luxe Erg Chebbi'), 40, 2, 2, 'en_attente', false);
            $past = $this->book($client, $byTitle('Riad Fès el Bali'), -60, 3, 2, 'confirme', true);
            $this->review($client, $past, 0);

            return;
        }

        $this->book($client, $pick(1), 1 + $i % 3, 2 + $i % 3, 2 + $i % 2, 'confirme', true);
        $this->book($client, $pick(5), 12 + $i * 2, 3, 2, 'confirme', true);
        $past = $this->book($client, $pick(9), -(20 + $i * 5), 2 + $i % 3, 2, 'confirme', true);
        $this->review($client, $past, $i);

        if ($i % 3 === 0) {
            $this->book($client, $pick(13), 30 + $i, 2, 2, 'annule', false);
        }
        if ($i % 4 === 1) {
            $this->book($client, $pick(17), 18 + $i, 2, 1, 'en_attente', false);
        }
    }

    /**
     * Creates a reservation (+ payment when paid), shifting by a week until the dates are free,
     * and computes the total exactly like ReservationController (price × nights + 20 MAD per guest above 2).
     */
    private function book(Utilisateur $client, Logement $logement, int $startInDays, int $nights, int $guests, string $statut, bool $paid): Reservation
    {
        $guests = min($guests, $logement->capacite);
        $start = Carbon::today()->addDays($startInDays)->setTime(15, 0);

        for ($attempt = 0; $attempt < 10 && $this->overlaps($logement, $start, $nights); $attempt++) {
            $start->addDays($startInDays >= 0 ? 7 : -7);
        }

        $end = $start->copy()->addDays($nights)->setTime(11, 0);
        $total = (float) $logement->tarif_amount * $nights + max(0, $guests - 2) * 20;
        $bookedAt = $startInDays >= 0
            ? Carbon::now()->subDays(3 + ($client->id_utilisateur % 9))
            : $start->copy()->subDays(21);

        $reservation = new Reservation([
            'id_utilisateur' => $client->id_utilisateur,
            'id_logement' => $logement->id_logement,
            'date_debut' => $start,
            'date_fin' => $end,
            'nb_personnes' => $guests,
            'statut' => $statut,
            'montant_total' => $total,
        ]);
        $reservation->created_at = $bookedAt;
        $reservation->updated_at = $bookedAt;
        $reservation->save();

        if ($paid) {
            $paidAt = $bookedAt->copy()->addMinutes(12);
            $payment = new Paiement([
                'id_reservation' => $reservation->id_reservation,
                'mode' => self::PAYMENT_MODES[$reservation->id_reservation % 3],
                'montant' => $total,
                'date_paiement' => $paidAt,
                'statut' => 'regle',
                'reference' => 'PAY-' . $paidAt->format('Ymd') . '-' . strtoupper(substr(md5('travelplan-' . $reservation->id_reservation), 0, 8)),
            ]);
            $payment->created_at = $paidAt;
            $payment->updated_at = $paidAt;
            $payment->save();
        }

        return $reservation;
    }

    private function overlaps(Logement $logement, Carbon $start, int $nights): bool
    {
        $end = $start->copy()->addDays($nights)->setTime(11, 0);

        return Reservation::where('id_logement', $logement->id_logement)
            ->where('statut', '!=', 'annule')
            ->where('date_debut', '<', $end)
            ->where('date_fin', '>', $start)
            ->exists();
    }

    private function review(Utilisateur $client, Reservation $reservation, int $index): void
    {
        [$note, $commentaire] = self::REVIEWS[$index % count(self::REVIEWS)];
        $agencyId = $reservation->logement()->value('id_agence');

        Avis::firstOrCreate(
            ['id_utilisateur' => $client->id_utilisateur, 'id_agence' => $agencyId, 'date_avis' => Carbon::parse($reservation->date_fin)->addDay()->toDateString()],
            ['note' => $note, 'commentaire' => $commentaire],
        );
    }
}
