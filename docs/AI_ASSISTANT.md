# TravelPlan AI — Assistant Intelligent de Réservation

Documentation technique de la fonctionnalité IA ajoutée à TravelPlan (PFA, 2ᵉ année du cycle ingénieur, ISGA).

> Diagrammes (sources PlantUML + PNG) : [`PFA ISGA/conception/AI_Assistant/`](../../PFA%20ISGA/conception/AI_Assistant/)

| Livrable | Fichier |
|---|---|
| Cas d'utilisation IA | `01_UseCase_AI.puml` → `UseCase_TravelPlanAI.png` |
| Séquence : confirmation automatique | `02_Sequence_ConfirmationAutomatique.puml` → `SD_ConfirmationAutomatique.png` |
| Séquence : interaction client ↔ IA | `03_Sequence_InteractionClientIA.puml` → `SD_InteractionClientIA.png` |
| Diagramme de classes mis à jour | `04_ClassDiagram_AI.puml` → `ClassDiagram_TravelPlanAI.png` |
| Architecture IA | `05_Architecture_AI.puml` → `Architecture_TravelPlanAI.png` |
| Extension MLD (Merise) | `06_MLD_Extension_AI.puml` → `MLD_Extension_TravelPlanAI.png` |

Régénérer les PNG : `docker run --rm -v "<dossier AI_Assistant>:/data" -w /data plantuml/plantuml -tpng -charset UTF-8 "*.puml"`

---

## 1. Objectif et périmètre du MVP

TravelPlan AI est un agent conversationnel qui accède, via des services Laravel sécurisés, aux données de réservation stockées dans MySQL. Il :

1. **contacte automatiquement le client** après un paiement réussi (e-mail + message dans le chat) ;
2. **demande au client de confirmer sa venue** avant l'arrivée et comprend des réponses naturelles (« Oui, je confirme », « Je ne suis plus sûr », « Je veux modifier mes dates », en français, anglais ou darija) ;
3. **répond aux questions** (dates, montant payé, adresse, annulation, statut) à partir des données réelles ;
4. **envoie des rappels** planifiés (demande d'intention, relance, rappel d'arrivée) sans doublons ;
5. **alimente un tableau de bord agence** et permet la **reprise par un conseiller humain**.

Canaux du MVP : **e-mail (Laravel Mail) + chat authentifié dans Angular**. Il n'y a pas de réception d'e-mails entrants : le client répond dans l'application.

## 2. Audit de l'existant — constats et corrections ciblées

| Constat | Traitement |
|---|---|
| Aucun lien entre un compte `agence` et une ligne `agences` | Ajout de `agences.id_utilisateur` (FK unique), création automatique de l'agence à l'inscription d'un compte agence, middleware `agency` + `AiConversationPolicy`. |
| `ReservationController::cancel` : avec Carbon 3, `diffInDays` est signé ; `$checkIn->diffInDays($now) < 1` refusait **toute** annulation future | Correction ciblée : règle centralisée dans `BookingPolicy::isCancellationWindowOpen()` (même règle des 24 h, signe corrigé). Test de non-régression `CancellationPolicyTest`. |
| Règles d'annulation/remboursement dupliquées et inaccessibles à l'IA | `BookingPolicy` = source unique utilisée par les contrôleurs **et** par l'outil IA (barème inchangé : >7 j 100 %, 3-7 j 50 %, <3 j 25 %). |
| Migrations incompatibles SQLite (`ALTER TABLE … ADD CONSTRAINT`) : la suite PHPUnit ne pouvait pas s'exécuter | Instruction ignorée uniquement sous SQLite ; MySQL garde ses contraintes `CHECK`. |
| `ng build` vide `../public` et supprimait `public/index.php` de Laravel | `"deleteOutputPath": false` dans `angular.json`. |
| Gestion des logements par l'agence : interface présente, routes API absentes | Hors périmètre, non modifié (voir §13). |

Les états `reservations.statut` (`en_attente`, `confirme`, `annule`) et `paiements.statut` **ne sont pas modifiés** par la fonctionnalité IA.

## 3. Architecture et intégration Laravel / Angular

```
Angular (SPA)                         Laravel 12 (API REST)                         Externe
──────────────                        ─────────────────────────                     ───────
Bouton flottant ─┐                    auth:sanctum · throttle · policy
Page /assistant ─┼─ AssistantService ─▶ AssistantController ─▶ AssistantService ─┬─▶ LanguageModel ─▶ API Claude
Tableau agence ──┘   (HTTP + Bearer)   AgencyAssistantController                 │    (Anthropic | Démo)
                                                                                 ├─▶ IntentClassifier (JSON schema)
                                                                                 ├─▶ ToolRegistry ─▶ MySQL (filtré par utilisateur)
                                                                                 └─▶ GroundingGuard
                                       PaymentController ─▶ Event ─▶ Job (queue) ─▶ BookingCommunicationService ─▶ Mail
                                       Scheduler (15 min) ─▶ travelplan:send-reminders ─▶ Job
```

- **Angular ne contacte jamais le LLM** : il appelle l'API Laravel avec le jeton Sanctum (intercepteur existant). La clé `ANTHROPIC_API_KEY` reste dans le `.env` du backend.
- **Abstraction fournisseur** (`App\Services\Ai\Contracts\LanguageModel`) :
  - `AnthropicLanguageModel` — SDK PHP officiel `anthropic-ai/sdk`, modèle `claude-opus-5-5` par défaut (configurable), effort `low`, outils `strict`, sorties structurées, repli serveur en cas de refus (`fallbacks: "default"`), délai et nombre de tentatives configurables ;
  - `DemoLanguageModel` — moteur déterministe à règles (FR/EN/darija), utilisé **sans clé API** et **comme repli automatique** si l'API échoue. Il passe par les mêmes outils, donc par les mêmes contrôles d'accès.
  - Un autre fournisseur s'ajoute en implémentant l'interface, puis en l'enregistrant dans `AppServiceProvider`.
- **Boucle d'outils** : le modèle propose un appel d'outil ; Laravel valide le nom (liste blanche) et les paramètres, exécute une requête Eloquent filtrée par `id_utilisateur`, puis renvoie le résultat JSON. La boucle est bornée (4 allers-retours) et l'historique n'est jamais réécrit.

## 4. Modèle de données (extension Merise)

### MCD — nouvelles entités et associations

| Entité | Rôle |
|---|---|
| **CONVERSATION_IA** | Fil d'échanges d'un client, général ou lié à une réservation. Porte l'état de reprise humaine (`active`, `handoff`, `closed`). |
| **MESSAGE_IA** | Message du client, de l'IA ou d'un conseiller ; type (`chat`, `booking_confirmation`…), intention détectée, métadonnées d'audit. |
| **CONFIRMATION_RESERVATION** | Intention d'arrivée du client, **distincte du statut de la réservation**. |
| **NOTIFICATION_RESERVATION** | Journal d'envoi des messages automatiques et clé d'idempotence. |

| Association | Cardinalités |
|---|---|
| UTILISATEUR *possède* AGENCE | (0,1) — (0,1) |
| UTILISATEUR *ouvre* CONVERSATION_IA | (0,N) — (1,1) |
| RÉSERVATION *concerne* CONVERSATION_IA | (0,N) — (0,1) |
| CONVERSATION_IA *contient* MESSAGE_IA | (0,N) — (1,1) |
| RÉSERVATION *a* CONFIRMATION_RESERVATION | (0,1) — (1,1) |
| CONFIRMATION_RESERVATION *détectée dans* MESSAGE_IA | (0,1) — (0,N) |
| RÉSERVATION *déclenche* NOTIFICATION_RESERVATION | (0,N) — (1,1) |

### MLD (voir `MLD_Extension_TravelPlanAI.png`)

- `agences(+ id_utilisateur FK UNIQUE NULL)`
- `ai_conversations(id_conversation, #id_utilisateur, #id_reservation NULL, channel, statut, handoff_reason, handoff_requested_at, handoff_resolved_at, #handled_by NULL, last_message_at)` — UNIQUE(id_utilisateur, id_reservation)
- `ai_messages(id_message, #id_conversation, role, content, kind, intent, #id_auteur NULL, meta JSON)`
- `reservation_confirmations(id_confirmation, #id_reservation UNIQUE, intention, confidence, source, #id_message NULL, requested_at, responded_at)`
- `booking_notifications(id_notification, #id_reservation, type, channel, statut, attempts, content_source, error, sent_at)` — UNIQUE(id_reservation, type, channel)

**Minimisation des données** : `meta` ne contient que des informations techniques (fournisseur, modèle, outils utilisés, latence, repli). Les outils n'envoient jamais au LLM l'e-mail du client, son mot de passe ni la référence de paiement.

## 5. Couche IA

### Outils autorisés (`ToolRegistry`)

| Outil | Type | Garde-fous |
|---|---|---|
| `list_my_reservations` | lecture | réservations de l'utilisateur connecté uniquement |
| `get_reservation_details(reservation_id)` | lecture | validation entier + propriété (sinon `reservation_not_found`) |
| `get_cancellation_policy(reservation_id)` | lecture | calculé par `BookingPolicy` ; indique la procédure, n'annule rien |
| `request_human_assistance(reason)` | signalement | passe la conversation en `handoff`, sans autre effet |

L'IA ne dispose d'**aucun outil** pour annuler, rembourser, modifier des dates, changer un paiement ou exécuter du SQL. Un outil inconnu est rejeté (`unknown_tool`).

### Reconnaissance d'intention (sorties structurées)

`IntentClassifier` envoie le message du client, présenté comme donnée non fiable, avec la question posée juste avant. La réponse est contrainte par un **JSON Schema** (`intention` ∈ {confirmed, declined, uncertain, modification_requested, human_assistance_required, none}, `confidence`, `language`), puis **revalidée côté serveur** avec le validateur Laravel. Une sortie invalide n'est pas enregistrée. `ArrivalIntentionService` :

- n'enregistre rien en dessous du seuil de confiance (0,6 par défaut), ni pour un séjour annulé ou passé ;
- enregistre la dernière intention dans `reservation_confirmations` (source `ai_classifier`, message d'origine) ;
- passe la conversation en `handoff` pour `modification_requested` et `human_assistance_required` ;
- ne modifie **jamais** la réservation ni le paiement. Un « Non, je souhaite annuler » est enregistré comme `declined` ; l'IA explique alors la procédure.

### Exactitude (anti-hallucination)

- Les faits viennent uniquement des outils, et le prompt système l'impose.
- **E-mails** : les dates, montants et statuts sont rendus par des templates depuis MySQL. Le LLM n'écrit qu'**une phrase d'accueil**, rejetée si elle contient un chiffre, un lien ou dépasse 220 caractères ; la phrase du template est alors utilisée.
- **Chat** : `GroundingGuard` compare chaque nombre de la réponse aux données récupérées pendant le tour. Les nombres non retrouvés sont journalisés et visibles dans l'audit agence.
- Un e-mail de confirmation n'est envoyé que si, **au moment de l'envoi**, `reservations.statut = confirme` **et** `paiements.statut = regle`.

### Injection de prompt

Le texte client est envoyé en tant que message utilisateur et n'est jamais concaténé aux instructions. Le prompt système déclare ces messages comme des données. Surtout, même un modèle « convaincu » ne peut pas agir au-delà de ce que permettent les outils : l'identité de l'utilisateur vient de Sanctum, pas du modèle (test `test_a_prompt_injection_cannot_reach_another_customers_data`).

## 6. Automatisation des communications

| Message | Déclencheur | Condition (vérifiée à l'envoi) |
|---|---|---|
| `booking_confirmation` | événement `ReservationPaymentSettled` (paiement `regle`) | réservation confirmée + paiement réglé |
| `intention_request` | planificateur | arrivée ≤ `AI_INTENTION_REQUEST_HOURS_BEFORE` (72 h), pas encore répondu |
| `intention_followup` | planificateur | demande envoyée depuis ≥ `AI_FOLLOWUP_AFTER_HOURS` (24 h), toujours sans réponse |
| `arrival_reminder` | planificateur | arrivée ≤ `AI_ARRIVAL_REMINDER_HOURS_BEFORE` (24 h), client n'ayant pas décliné |

- **Idempotence** : il y a trois niveaux. Le job est `ShouldBeUnique`, la ligne `booking_notifications` est unique par (réservation, type, canal), et le message du chat est unique par type dans la conversation.
- **Anti-spam** : pas deux messages automatiques à moins de `AI_MIN_HOURS_BETWEEN_MESSAGES` (6 h) pour un même séjour.
- Les séjours annulés, commencés ou terminés ne reçoivent aucun rappel.
- Échec d'envoi : statut `failed`, erreur journalisée, puis 3 tentatives (après 60 s et 300 s).

## 7. Sécurité et fiabilité

| Exigence | Mise en œuvre |
|---|---|
| Authentification | `auth:sanctum` sur toutes les routes IA |
| Propriété des réservations | contrôle dans chaque outil, à l'ouverture de conversation et via la policy `participate` |
| Données agence | middleware `agency` (rôle + agence possédée) et policy `handleAsAgency` (le logement appartient à l'agence) |
| Clé API | backend uniquement (`config/ai.php`), jamais exposée au navigateur |
| Actions sensibles | aucune via l'IA ; annulation par le bouton existant (`PUT /reservations/{id}/cancel`, règles inchangées) |
| Limitation de débit | `throttle:assistant`, 12 messages/min/utilisateur par défaut ; 1000 caractères max par message |
| Délais / erreurs | timeout 30 s, 1 tentative supplémentaire, exceptions SDK typées → `AiProviderException`, journalisation |
| Indisponibilité de l'IA | repli automatique sur le moteur déterministe (même accès aux données) |
| Tests sans envoi réel | `phpunit.xml` force `AI_PROVIDER=demo`, clé vide, `MAIL_MAILER=array` |

## 8. API

| Méthode | Route | Accès |
|---|---|---|
| GET | `/api/assistant/conversations` | client : ses conversations + ses réservations |
| POST | `/api/assistant/conversations` `{id_reservation?}` | ouvre ou crée la conversation (404 si la réservation n'est pas à lui) |
| GET | `/api/assistant/conversations/{id}` | propriétaire |
| POST | `/api/assistant/conversations/{id}/messages` `{content}` | propriétaire, limité en débit |
| GET | `/api/agency/assistant/dashboard` | agence propriétaire |
| GET | `/api/agency/assistant/conversations/{id}` | agence du logement concerné (avec audit) |
| POST | `/api/agency/assistant/conversations/{id}/messages` | réponse du conseiller humain |
| POST | `/api/agency/assistant/conversations/{id}/resolve` | clôture de la reprise humaine |

`GET /api/reservations` inclut désormais `confirmation` (ajout non cassant).

## 9. Interface Angular

- `assistant/assistant-launcher` — bouton flottant « Assistant IA » et panneau de chat (clients), en plein écran sur mobile.
- `assistant/assistant-page` — `/assistant` et `/assistant/:reservationId`, cible des liens e-mail : liste des séjours et chat.
- `assistant/assistant-conversation` — envoi optimiste, indicateur de saisie, erreurs lisibles avec « Réessayer », réponses rapides (« Oui, je confirme »…), bannière de reprise humaine, rafraîchissement automatique pendant la reprise.
- `assistant/chat-thread` — fil de messages réutilisé côté client et agence : l'IA est clairement identifiée (badge « IA », « mode démo », message automatique) ; côté agence, intentions détectées, outils utilisés et replis.
- `assistant/intention-badge` — badge « Venue confirmée / Sans réponse / … », aussi affiché sur les cartes de réservation.
- `agency-assistant` — tableau de bord `/agence/assistant` : indicateurs, onglets, conversation, réponse et clôture.
- Le garde d'authentification mémorise l'URL demandée : un lien e-mail mène au chat après la connexion.

## 10. Configuration et lancement

Prérequis : **PHP ≥ 8.2** (le PHP 7.3 de XAMPP ne convient pas à Laravel 12), Composer, MySQL, Node 20+.

```bash
cd TravelPlan
composer install
php artisan migrate                                  # nouvelles tables
php artisan db:seed --class=AgencyOwnershipSeeder    # base existante : lie agence@example.com à « Atlas Voyages »
#   (ou base neuve : php artisan migrate:fresh --seed)

php artisan serve          # API http://127.0.0.1:8000
php artisan queue:work     # QUEUE_CONNECTION=database : traite les jobs de communication
php artisan schedule:work  # lance travelplan:send-reminders toutes les 15 min

cd frontend && npm install && npm start   # http://localhost:4200
```

> **Windows :** `angular.json` désactive le pré-bundling Vite (`"prebundle": false`). Sinon, sous Windows, Vite échoue (`EPERM … rename deps_temp`) et l'application s'affiche en page blanche. En cas de page blanche, supprimez `frontend/.angular/cache` et relancez `npm start`.

Variables `.env` (voir `.env.example`) :

```
ANTHROPIC_API_KEY=            # vide = mode démo
AI_MODEL=claude-opus-5-5
AI_EFFORT=low
AI_TIMEOUT_SECONDS=30
AI_RATE_LIMIT_PER_MINUTE=12
AI_INTENTION_REQUEST_HOURS_BEFORE=72
AI_FOLLOWUP_AFTER_HOURS=24
AI_ARRIVAL_REMINDER_HOURS_BEFORE=24
AI_MIN_HOURS_BETWEEN_MESSAGES=6
FRONTEND_URL=http://localhost:4200
```

E-mails en développement : `MAIL_MAILER=log` les écrit dans `storage/logs/laravel.log`. Pour une boîte de réception visuelle : `docker run -d -p 8025:8025 -p 1025:1025 axllent/mailpit`, puis `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`, et ouvrir http://localhost:8025. Aucun e-mail réel n'est envoyé dans ces deux modes.

## 11. Scénario de démonstration (soutenance)

Données de démonstration marocaines (`MoroccanDemoSeeder`, appelé par `php artisan migrate:fresh --seed`) :

- 8 agences, chacune avec un compte : `agence@example.com` (Atlas Voyages, Marrakech), `sahara@`, `chefchaouen@`, `essaouira@`, `fes@`, `agadir@`, `tanger@`, `casarabat@example.com` ;
- 27 riads, hôtels et studios dans 12 villes ;
- 12 clients (`client@example.com` = Youssef El Amrani, `salma.benali@example.com`…), tous avec le mot de passe `password` ;
- réservations, paiements et avis aux dates relatives à aujourd'hui : chaque client a un séjour dans 1 à 3 jours, un séjour plus tard et un séjour passé.

Le seeder est idempotent. Il complète aussi les comptes créés dans l'application : un client sans réservation reçoit des séjours, et un compte agence sans agence reçoit une agence avec ses logements.

Comptes de démonstration : client `client@example.com` / `password`, agence `agence@example.com` / `password` (agence « Atlas Voyages »).
Pour enchaîner les messages sans attendre 6 h, mettez `AI_MIN_HOURS_BETWEEN_MESSAGES=0` dans `.env` pendant la démo.

1. Connecté comme client, réservez **« Hôtel Atlas Marrakech »** avec une arrivée **dans 1 à 3 jours** et payez (paiement simulé).
2. Le paiement déclenche l'événement, puis le job : un e-mail est envoyé (Mailpit ou `laravel.log`) avec le bouton **« Ouvrir l'assistant TravelPlan »** et un lien `/assistant/{id}`.
3. Cliquez sur le lien (connexion demandée si nécessaire) : le chat s'ouvre sur la réservation, avec le message de confirmation.
4. Posez : « À quelle date commence ma réservation ? », « Combien ai-je payé ? », « Où se trouve mon hôtel ? », « Est-ce que je peux annuler ? ». Les réponses proviennent de MySQL.
5. Lancez la planification à la demande : `php artisan travelplan:send-reminders`. L'IA demande : « Confirmez-vous que vous prévoyez toujours de venir ? »
6. Répondez naturellement : « Oui, je confirme. » Le backend enregistre `confirmed` et le badge **Venue confirmée** apparaît.
7. Optionnel : « Je veux modifier mes dates » ou « Je veux parler à l'agence » font passer la conversation en reprise humaine.
8. Connectez-vous comme agence, puis ouvrez **Tableau IA** : indicateurs, séjour « Venue confirmée », conversation complète avec intentions détectées et outils utilisés ; répondez au client et clôturez la reprise.

Aucune donnée n'est insérée à la main : la réservation, le paiement, les messages et la confirmation sont tous créés par l'application.

## 12. Tests automatisés

```bash
php artisan test
# ou sans PHP local :
docker build -t travelplan-php:8.3 -f docker/php.Dockerfile docker
docker run --rm -v "$PWD:/app" -w /app travelplan-php:8.3 php artisan test
```

56 tests ajoutés, 190 assertions dans la suite complète (SQLite en mémoire, aucun appel réseau) :

| Fichier | Couverture |
|---|---|
| `Unit/IntentHeuristicsTest` | phrases FR/EN/darija du cahier des charges, questions ≠ décisions, « oui » isolé |
| `Unit/GroundingGuardTest` | chiffres ancrés ou inventés, détection de chiffres dans les e-mails |
| `Assistant/AssistantAuthorizationTest` | invités, réservation ou conversation d'autrui, liste blanche d'outils, cloisonnement entre agences |
| `Assistant/ArrivalIntentionTest` | confirmation enregistrée sans toucher la réservation, refus sans annulation, sortie structurée invalide, seuil de confiance, séjour annulé |
| `Assistant/AssistantResponseHandlingTest` | outils et audit, repli si l'API est indisponible, signalement de chiffres inventés, validation et limitation de débit, injection de prompt |
| `Assistant/BookingCommunicationTest` | paiement → job, envoi unique (idempotence), réservation non payée, garde sur la phrase IA, planificateur, relance, séjour décliné, anti-spam |
| `Assistant/AgencyDashboardTest` | indicateurs, historique avec audit, réponse humaine, clôture |
| `Assistant/AnthropicLanguageModelTest` | **vrai SDK Anthropic** avec transport HTTP simulé : forme des requêtes (outils strict, effort, fallbacks), boucle d'outils, sortie structurée, erreurs 429 |
| `Feature/CancellationPolicyTest` | non-régression du bug d'annulation |

`tests/Feature/ExampleTest` échouait déjà avant ces changements : `routes/web.php` sert `public/spa/index.html`, qui n'existe pas.

## 13. Limites et perspectives

- **Modèle réel** : l'intégration est testée avec le SDK officiel et des réponses simulées. Une démonstration avec Claude nécessite une clé dans `ANTHROPIC_API_KEY` ; la qualité de compréhension (darija notamment) doit être évaluée sur des conversations réelles.
- **Le mode démo** comprend des formulations courantes par mots-clés et répond en français ; la compréhension fine relève du LLM.
- **Hors périmètre, non modifiés** :
  - le statut `rembourse` utilisé par `PaymentController::refund` n'existe pas dans l'énumération `paiements.statut` ;
  - les routes CRUD des logements agence sont absentes ;
  - le contrôle de chevauchement inclut les bornes (un départ et une arrivée le même jour sont refusés) ;
  - `web.php` pointe vers `public/spa`.
- **Modification de dates** : pas de flux en ligne ; l'IA transfère la demande à l'agence.
- **WhatsApp Business (évolution)** : le schéma prévoit déjà `channel` (conversations et notifications). Il faudrait :
  - un canal de notification WhatsApp ;
  - le recueil du **consentement** (opt-in) ;
  - des **modèles de messages approuvés** pour les envois hors fenêtre de 24 h ;
  - un webhook entrant rattaché à la conversation ;
  - des webhooks de statut de livraison pour mettre à jour `booking_notifications` ;
  - le respect des politiques de la plateforme.
  
  Aucun message réel ne doit être envoyé en test (bac à sable / numéro de test).

## 14. Fonctionnalités IA complémentaires (découverte & pilotage)

| Fonctionnalité | Où | Principe | Garde-fous |
|---|---|---|---|
| **Recherche intelligente** | Accueil client (zone « Recherche intelligente IA ») | Le voyageur décrit son séjour en langage naturel (FR / EN / darija). Le LLM **extrait** des critères (sortie structurée JSON : ville, type, voyageurs, budget/nuit, date d'arrivée, nuits, envies). Les résultats viennent de la recherche MySQL existante. Les dates et le nombre de voyageurs pré-remplissent le formulaire de réservation. | Validation serveur de chaque champ face au catalogue réel (ville existante, type autorisé, bornes de prix et de voyageurs, date future). Texte traité comme donnée non fiable. Repli heuristique sans clé API. Limitation de débit (`throttle:smart-search`). |
| **Recommandé pour vous** | Accueil client | Moteur de recommandation **explicable**, basé sur le contenu : profil (types préférés, villes connues, budget moyen, taille du groupe), score transparent, diversité (2 logements maximum par ville). Chaque suggestion affiche ses raisons (« Vous aimez les riads », « Nouvelle destination : Tanger », « Agence très bien notée »). | Aucun LLM : déterministe et testable. Les logements déjà réservés et les logements inactifs sont exclus. |
| **Destinations populaires** | Accueil client | Villes du catalogue, nombre de logements, prix d'entrée. Un clic lance la recherche sur la ville. | Données issues de MySQL. |
| **Insights IA agence** | Tableau IA (agence) | Indicateurs calculés en SQL/PHP : revenus confirmés à 30 jours, taux d'occupation, taux de venues confirmées par l'IA, note moyenne, arrivées des 14 prochains jours (graphique), plus un **résumé rédigé par l'IA** avec une action conseillée. | Le résumé est rejeté s'il contient un nombre absent des indicateurs (`GroundingGuard`). Un résumé gabarit est utilisé sinon. |

API : `POST /api/ai/smart-search`, `GET /api/destinations`, `GET /api/recommendations` (client connecté), `GET /api/agency/assistant/insights` (agence), `GET /api/agency/logements` (logements de l'agence, recherche incluse).

Tests : `tests/Feature/Assistant/DiscoveryTest.php` (7 tests) couvre :
- l'analyse FR/EN/darija ;
- le rejet des sorties LLM invalides (ville inexistante, type inconnu, prix ou date absurdes) ;
- le repli si l'IA est indisponible ;
- les destinations ;
- les recommandations expliquées ;
- les indicateurs agence et le rejet d'un résumé IA contenant un chiffre inventé.

### Démonstration (2 minutes)

1. Client `client@example.com` → page d'accueil : montrer les **destinations** et **« Recommandé pour vous »** (lire une raison à voix haute).
2. Cliquer l'exemple « Un riad à Marrakech pour 4 personnes ce week-end, moins de 1000 DH » : la bannière **« TravelPlan AI a compris »** affiche les critères extraits, puis le résultat.
3. Cliquer « Réserver » : les dates du week-end et 4 voyageurs sont **pré-remplis**.
4. Agence `agence@example.com` → **Tableau IA** : indicateurs, survol du graphique des arrivées, **résumé IA** et action conseillée.

### Idées à valoriser pendant la soutenance

- **IA ancrée dans les données** : le LLM extrait et rédige ; les faits (prix, dates, disponibilités) viennent toujours de MySQL.
- **Défense en profondeur** : sorties structurées par schéma, puis validation serveur, liste blanche d'outils, détection de chiffres non ancrés, limitation de débit, repli déterministe.
- **IA explicable** : les recommandations affichent leurs raisons ; la recherche affiche ce qu'elle a compris, et chaque critère peut être effacé.
- **Multilingue marocain** : français, anglais et darija, par exemple « bghit camp f sahara 3 nfar ghda ».
- **Perspectives** :
  - embeddings pour une recherche sémantique sur les descriptions ;
  - tarification dynamique à partir du taux d'occupation ;
  - résumé IA des avis par agence ;
  - canal WhatsApp Business ;
  - application mobile.
