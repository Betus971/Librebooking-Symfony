# Audit Librebooking-Symfony — conformité AGENTS.md (16/11/2026)

Contexte : portage de LibreBooking vers Symfony 7.4 LTS (PHP 8.4, Doctrine ORM 3, Twig + Tailwind v4/DaisyUI 5, EasyAdmin 5).
Version communautaire : **aucune référence gendarmerie/DSFR**. Référence d'inspiration : booking-D (qui applique déjà AGENTS.md).

## Résultats des contrôles

| Contrôle | Résultat |
|---|---|
| `lint:container` / `lint:twig` (82) / `lint:yaml` (33) | ✅ OK |
| `doctrine:schema:validate` | ✅ mapping OK — ⚠️ base locale : 2 migrations non jouées |
| PHPStan niveau 5 | ❌ 91 erreurs (dont ~10 vrais bugs, voir P0) |
| PHPUnit | ❌ 2 tests / 10 en erreur — seulement 2 fichiers de test (booking-D : 26) |
| php-cs-fixer `@Symfony` | ⚠️ 83 / 179 fichiers non conformes |
| Routage | ✅ 100 % attributs (`config/routes/*.yaml` = recettes de bundles uniquement) |
| Services | ✅ autowiring, aucune définition YAML manuelle |
| Commandes | ✅ toutes en `#[AsCommand]` |
| Verrou réservation | ✅ `pg_advisory_xact_lock` dans la transaction (correct ; voir P2) |

## P0 — Bugs réels (à corriger tout de suite)

1. **Validation silencieusement désactivée** : `Entity/Layout.php` et `Entity/TimeBlock.php` utilisent `#[Assert\...]` sans `use Symfony\Component\Validator\Constraints as Assert;` → les contraintes (NotBlank, Timezone, Choice, Callback) ne sont **jamais appliquées**.
2. `CalendarController::feed()` appelle `IcsGeneratorService::generate()` qui n'existe pas → `/calendar/feed.ics` en **500**.
3. `GoogleController::connectAction()` : `redirect()` attend 2 arguments → connexion Google en erreur.
4. `AutoReleaseCommand` appelle `ReservationInstanceRepository::findCheckinCandidates()` inexistante → la commande plante.
5. `ReservationManager::createSimpleReservation()` appelle `hasOverlap()` inexistante (méthode jamais appelée → code mort à supprimer).
6. `CreneauGenerator::findNextAvailableDate()` : `setTime()` sur un `DateTimeImmutable` sans réaffectation (sans effet).
7. `tests/Security/Voter/ReservationSeriesVoterTest` utilise `Voter::CANCEL` (la constante est `CANCEL_OWN`).

## P0 — Résidus gendarmerie / DSFR / dev

- `HealthController` renvoie `'application' => 'resadg'`.
- Couleur « Bleu France » `#000091` dans `StatistiqueRessourceController`.
- Classes `fr-*` (DSFR) : `Form/ResourceType`, `Form/Type/DurationUnitType`, `Form/UserGroupType`, `presta/provider/plage_horaire/*`, `_partials/_pagination`.
- Commentaires DSFR / « code unité » / « booking-D » : repositories, `ResourceVoter`, `CalendarSubscriber`, `SecurityHeadersSubscriber`, `Presta/Entity/*`, `assets/app.js`, `config/packages/{asset_mapper,knp_paginator}.yaml`, `.gitignore`, SVG.
- Variables **DaisyUI v4** (`hsl(var(--su))`…) dans `presta/client/booking/group.html.twig` → rendu cassé (même bug que la page individuelle).
- `DevSlotsController` (`/dev/slots`, `/dev/res-check`) accessible en prod ; `calendar/index_v2.html.twig` expérimental.

## P0 — Configuration (AGENTS.md)

- **`.env` n'est pas versionné** (ignoré dans `.gitignore`) alors qu'AGENTS.md exige un `.env` commité avec des valeurs par défaut, les secrets allant dans `.env.local`. C'est la cause de la panne du 17/08 sur le VPS (re-clone sans `.env`).
- Le `.env` local contient de vraies valeurs (mot de passe BDD, `APP_SECRET`) → à déplacer dans `.env.local`.

## P1 — Architecture (ce que booking-D a déjà fait)

- **Requêtes hors repositories** : `Api/PlanningController`, `TimeBlockController`, `Presta/.../PlageHoraireController`, `Presta/Command/SendRemindersCommand`, `Presta/Service/IndividualBookingManager`, `Service/ReservationManager` (booking-D : « plus aucune requête dans les contrôleurs »).
- **Lecture manuelle de la requête** : 121 appels `$request->query/request->get()` / `json_decode()` ; **0** `#[MapRequestPayload]` / `#[MapQueryString]`. Priorité : l'API `Api/PlanningController` et les filtres de listes (DTO `readonly` + `#[MapQueryString]`).
- **Contrôleurs épais** : `ReservationController` (386 l.), `Presta/Provider/AgendaController` (385), `TimeBlockController` (304), `AdminReservationController` (302).
- **Tests** : porter depuis booking-D les tests de Health, Api/Planning, AdminUserRoles, Presta (Voter, ServiceController), Notification ICS, ReservationWorkflow. Règle AGENTS.md : une fonctionnalité n'est finie qu'avec un test qui l'exerce.
- Abonnés d'événements en `EventSubscriberInterface` (3) → `#[AsEventListener]`.

## P2 — Qualité

- php-cs-fixer `@Symfony` sur tout `src/` (83 fichiers) — commit séparé, purement cosmétique.
- PHPStan : types `DateTimeInterface` → `DateTimeImmutable` dans les services de créneaux ; `$id` des entités ; `method_exists()` inutiles dans `ResourceRulesChecker`.
- Verrou : garder `pg_advisory_xact_lock` (lié à la transaction, plus sûr ici) mais le documenter, ou passer par `symfony/lock` + `PostgreSqlStore` comme booking-D si on veut uniformiser.
- `ProviderTrait` jamais utilisé → supprimer.

## Plan proposé

1. P0 (bugs, résidus, `.env`) — fait dans la foulée de cet audit.
2. P1 tests d'abord (porter ceux de booking-D), puis refactors contrôleurs → repositories / DTO `#[MapQueryString]`.
3. P2 cs-fixer + PHPStan au vert, puis montée de niveau PHPStan.
