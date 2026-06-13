# Librebooking — Roadmap fonctionnelle

> Comparaison du schéma historique **Booked / phpScheduleIt** (cf. `ERD`) avec
> les entités actuelles, et plan d'implémentation priorisé des fonctionnalités
> manquantes les plus utiles. Chaque item reste **générique** (cf.
> `docs/12-strategie-open-source.md`) : aucune logique propre à une organisation.

## Déjà en place

Cœur réservation (séries / instances / ressources / statuts / types / participants),
ressources + groupes d'approbation, **catégories de ressources** (ajout maison,
remplace `resource_types` pour le filtrage), plannings / layouts / créneaux,
blackouts, annonces, pièces jointes, **journal d'audit** (ajout maison),
validation admin + notifications e-mail, switch de thème, flux iCal (stub).

## Légende

- **Statut** : ⬜ à faire · 🟦 en cours · ✅ fait
- **Effort** : S (petit) · M (moyen) · L (gros)

---

## P1 — Prioritaire (réutilise l'existant, fort gain, peu de code neuf)

### P1.1 — Liste d'attente · Effort M · Statut ✅
**Table d'origine** : `reservation_waitlist_requests`.
**Objectif** : quand un créneau est complet, l'utilisateur s'inscrit en liste
d'attente ; à l'annulation d'une réservation, le premier de la file est notifié.
**Modèle** : `WaitlistRequest` (user, resource, startDate, endDate, status, createdAt).
**Intégration** : hook dans `ReservationWorkflow::apply('cancel')` → recherche des
demandes en attente chevauchant le créneau libéré → `ReservationNotifier`.
Endpoint pour s'inscrire + bouton sur le formulaire de réservation quand le
créneau est indisponible.

### P1.2 — Abonnement iCal par ressource · Effort S · Statut ✅
**Champs d'origine** : `resources.public_id`, `allow_calendar_subscription`.
**Objectif** : chaque ressource expose son agenda (`/calendar/feed.ics?...`)
abonnable dans Outlook / Google / Thunderbird.
**Intégration** : étendre `IcsGeneratorService` (déjà créé) pour produire les
vrais `VEVENT` depuis les réservations actives ; `public_id` (UUID) sur
`Resource` pour une URL non énumérable ; lien d'abonnement sur la fiche ressource.

### P1.3 — Rappels automatiques · Effort M · Statut ✅
**Tables d'origine** : `reminders`, `reservation_reminders`.
**Objectif** : e-mail de rappel X heures/minutes avant le début.
**Modèle** : `ReservationReminder` (series, minutesPrior, type) ou réglage global.
**Intégration** : commande console `app:reminders:send` (cron / Messenger Scheduler)
qui interroge les instances à venir et appelle `ReservationNotifier`.

### P1.4 — Check-in / no-show + libération auto · Effort M · Statut ✅
**Champs d'origine** : `reservation_instances.checkin_date/checkout_date`,
`resources.enable_check_in`, `auto_release_minutes`.
**Existant** : `Resource` a déjà `autoReleaseMinutes` et `bufferTime`.
**Objectif** : pointer l'arrivée ; libérer automatiquement un créneau non
confirmé après le délai. Ajouter `checkinDate`/`checkoutDate` sur
`ReservationInstance`, un endpoint de check-in, et une commande de libération auto.

---

## P2 — Fort intérêt « open-source » (reste générique)

### P2.1 — Attributs personnalisés · Effort L · Statut ⬜
**Tables d'origine** : `custom_attributes`, `custom_attribute_values`.
**Objectif** : l'admin ajoute des champs dynamiques (réservation / ressource /
utilisateur) sans modifier le code — clé de la généricité du cœur.

### P2.2 — Quotas · Effort M · Statut ⬜
**Table d'origine** : `quotas`.
**Objectif** : limiter le volume réservable par utilisateur / groupe / ressource
sur une période (anti-monopolisation). Vérification dans `ReservationRequestValidator`.

### P2.3 — Accessoires / équipements · Effort M · Statut ⬜
**Tables d'origine** : `accessories`, `resource_accessories`, `reservation_accessories`.
**Objectif** : ajouter vidéoprojecteur, micro, etc. à une réservation, avec
quantités min/max.

---

## P3 — Secondaire / plus tard

- **CGU à accepter** (`terms_of_service`, `terms_date_accepted`). Effort S.
- **État de ressource + motif** (maintenance / hors-service au-delà des blackouts). Effort S.
- **Permissions fines par ressource** (view / book, au-delà du scope par groupe). Effort M.
- **Images multiples par ressource** (`resource_images` ; aujourd'hui un seul `image_name`). Effort S.
- **Invités externes** (`reservation_guests`). Effort S.

## Hors scope du cœur générique (à isoler en bundle si besoin)

- Crédits & paiement (`credit_log`, `payment_*`, Stripe/PayPal).
- Rapports sauvegardés (`saved_reports`).
- Règles de couleur conditionnelles (`reservation_color_rules`).
- « Prestations / services » : changement de paradigme (service-centric vs
  resource-centric) — à cadrer séparément avant implémentation.

---

## Ordre d'exécution retenu

**P1.1 → P1.2 → P1.3 → P1.4**, puis P2. Ces quatre réutilisent directement le
`ReservationWorkflow`, le `ReservationNotifier` et l'`IcsGeneratorService`
existants : peu de code neuf, gros gain utilisateur.

> ⚠️ Chaque ajout d'entité nécessite une migration : après génération du code,
> lancer en local `php bin/console doctrine:migrations:diff` puis
> `doctrine:migrations:migrate`.
