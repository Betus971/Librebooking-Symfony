# Audit du projet — état & prochaines améliorations

> Revue technique du dépôt en vue de fiabiliser l'application et de la partager.
> Priorités : **P0** = bloquant · **P1** = qualité/robustesse · **P2** = fonctionnalités · **Hygiène** = divers.
> Effort : **S** petit · **M** moyen · **L** gros · Statut : ✅ fait · 🟦 en cours · ⬜ à faire.
> Dernière mise à jour : 2026-06-13.

---

## ✅ Réglé depuis le 1er audit

- **P0.1 — Migration baseline** : une migration unique (`Version20260613194255`) crée tout le schéma (27 tables, cœur + `presta_*` + `waitlist_requests`). Doublons supprimés. Install reproductible : `db:drop` → `db:create` → `migrations:migrate` → `fixtures:load`.
- **P0.2 — Module Presta** : routes chargées (`controllers_presta`), mapping Doctrine `App\Presta\Entity`, 14 templates convertis **DSFR → DaisyUI**, lien « Prestations » au menu. Fonctionnel.
- **DataFixtures complète** : référence (statuts/types), 4 comptes, planning + créneaux, catégories, groupes, 8 ressources, jeu Presta.
- **Switch de thème** clair/sombre (DaisyUI `data-theme`) + **UX réservation** (date du jour, fin=début, créneaux pris grisés).
- **Hygiène** déjà faite : suppression module Visiteurs, refactor (repos/services/voters), validation admin + e-mails, licence GPLv3, README + badges, i18n de l'accueil.

---

## 🟠 P1 — Qualité & robustesse (reste à faire)

### P1.1 — Aucun test automatisé · Effort M–L · ⬜
`tests/` ne contient que `bootstrap.php`. Couvrir d'abord : `ReservationWorkflow`, `AvailabilityChecker`/`AvailabilityService`, les Voters, la concurrence. (booking-D a déjà des tests réutilisables.)

### P1.2 — Pas de CI · Effort S · ⬜
Aucun `.github/workflows`. Ajouter GitHub Actions : `composer install` → `lint:container` + `lint:twig` + `lint:yaml` → PHPStan → PHPUnit, sur push/PR. **Préparable entièrement ici.**

### P1.3 — Pas d'analyse statique ni de style · Effort S · ⬜
`require-dev` = PHPUnit seul. Ajouter **PHPStan** (niveau 5–6) + **PHP-CS-Fixer** (PSR-12). Aurait attrapé les bugs déjà rencontrés (imports `Assert` manquants, `setId()` void chaîné…). **Préparable entièrement ici.**

### P1.4 — DSFR ✅ / i18n partielle · Effort M · 🟦
**DSFR : 100 % converti en DaisyUI** (Presta + les 9 templates restants). Plus aucune classe `fr-*` dans le projet.
Reste : **finir l'i18n** (accueil + admin faits ; réservation/ressources/recherche/calendrier encore en FR en dur dans les libellés).

---

## 🟢 P2 — Fonctionnalités (cf. `docs/roadmap.md`)

- **Quotas** (limite par utilisateur/groupe/ressource) — très pertinent en entreprise. ⬜
- **Attributs personnalisés** (champs dynamiques sans toucher au code). ⬜
- **Accessoires / équipements** rattachés à une réservation. ⬜

---

## 🧹 Hygiène / divers

- **`calendar/index_v2.html.twig`** (page expérimentale V2 + DSFR) : à finaliser ou supprimer. ⬜
- **README « database-agnostic »** vs code PostgreSQL-spécifique (advisory lock, `ON CONFLICT`) : clarifier « PostgreSQL requis ». ⬜
- **Pousser sur GitHub** : tout le travail est dans le dépôt local mais doit être committé/poussé depuis ta machine (`git push origin main` — pas d'identifiants côté assistant). ⬜
- Revue sécurité légère (access_control, CSRF, en-têtes) : globalement OK, à confirmer.

---

## Ordre conseillé

1. **Pousser** ce qui est fait (depuis ta machine).
2. **P1.2 + P1.3** (CI + PHPStan/CS-Fixer) — rapides, je peux les préparer entièrement ici.
3. **P1.4** (convertir les 9 templates DSFR restants + i18n) — finition visuelle.
4. **P1.1** (tests sur les briques critiques).
5. **P2** selon besoins métier (quotas en tête).
