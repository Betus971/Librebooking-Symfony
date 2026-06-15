# Revue de code — Module Presta

> Auteur : Leonard Donavan  
> Date : 2026-06-15  
> Branche : `claude/code-review-tu7a0s`  
> Périmètre : diff `main...HEAD` (ajout du module Presta + migrations)

---

## Résumé

10 problèmes identifiés, dont 2 critiques de sécurité à corriger avant toute mise en production.

---

## Problèmes à corriger

### 🔴 1 — Élévation de privilèges : tout utilisateur connecté devient prestataire

**Fichier :** `src/Presta/Controller/Provider/ProviderTrait.php` ligne 25

`getPrestataire()` crée automatiquement un enregistrement `Prestataire` pour n'importe quel `ROLE_USER` sans aucune vérification de rôle spécifique.

**Scénario :** Un client ordinaire navigue vers `/presta/provider/service/`. Le trait ne trouve pas de `Prestataire` existant, en crée un silencieusement, le persiste en base, et retourne l'accès complet aux fonctionnalités prestataire (créer des services, séances, plages horaires). Aucun `isGranted('ROLE_PRESTATAIRE')` n'est vérifié nulle part dans les contrôleurs provider.

**Correction :**
- Créer un rôle `ROLE_PRESTATAIRE` distinct de `ROLE_USER`
- Supprimer l'auto-création dans `ProviderTrait::getPrestataire()` — lever une exception si aucun prestataire n'est trouvé
- Ajouter dans `security.yaml` :
  ```yaml
  access_control:
      - { path: ^/presta/provider, roles: ROLE_PRESTATAIRE }
  ```
- Ajouter `#[IsGranted('ROLE_PRESTATAIRE')]` sur les contrôleurs provider

---

### 🔴 2 — CSRF désactivé sur le formulaire de login

**Fichier :** `config/packages/security.yaml` ligne 25

`form_login` a `enable_csrf: false` explicitement, désactivant la protection CSRF native de Symfony sur la page de connexion.

**Scénario :** Un attaquant crée une page externe avec un formulaire auto-soumis vers `/login` avec ses propres identifiants. La victime qui visite cette page est connectée dans le compte de l'attaquant. Tout ce qu'elle saisit ensuite (données personnelles, réservations) est visible par l'attaquant (login CSRF / session fixation).

**Correction :**
```yaml
form_login:
    enable_csrf: true  # valeur par défaut Symfony, supprimer la ligne ou mettre true
```

---

### 🔴 3 — Double-réservation par race condition dans `bookIndividual()`

**Fichier :** `src/Presta/Controller/Client/BookingController.php` ligne 261

Aucune re-vérification de disponibilité n'est effectuée avant la persistance. Le code lui-même le reconnaît avec un commentaire ligne 273 : `// Dans un système complet, il faudrait re-vérifier la dispo exacte ici`.

**Scénario :** Deux clients soumettent simultanément le formulaire pour le créneau 14h00. `generateCreneauxForDate()` avait montré le slot libre pour les deux. Sans verrou ni vérification d'overlap au moment du flush, les deux `Session` sont persistées. Le prestataire est double-réservé sans qu'aucune erreur ne soit levée.

**Correction :**
- Ajouter une requête de vérification d'overlap avec `SELECT ... FOR UPDATE` (lock pessimiste) avant la persistance
- Ou ajouter une contrainte unique en base sur `(prestataire_id, date_debut)`
- Lever une exception métier en cas de conflit et afficher un message flash à l'utilisateur

---

### 🔴 4 — Dépassement de capacité par TOCTOU dans `bookGroup()`

**Fichier :** `src/Presta/Controller/Client/BookingController.php` lignes 92–110

La lecture de `nbInscrits` et l'écriture sont effectuées sans verrou base de données. Deux requêtes concurrentes peuvent toutes deux passer le guard de capacité.

**Scénario :** Séance de groupe avec `capaciteMax=5`, `nbInscrits=4`. Deux clients soumettent `bookGroup` simultanément. Les deux lisent `nbInscrits=4 < 5`, les deux passent le contrôle, les deux insèrent une `Inscription` et appellent `setNbInscrits(5)`. La séance se retrouve avec 6 inscriptions. Le guard anti-doublon ligne 106 ne protège que contre le même client, pas deux clients différents en concurrence.

**Correction :**
- Utiliser le lock pessimiste Doctrine : `$em->lock($session, LockMode::PESSIMISTIC_WRITE)` après le `findOrFail`
- Ou remplacer `nbInscrits` par un `COUNT` dynamique depuis la relation `inscriptions` pour éviter l'état dénormalisé

---

### 🟠 5 — Trois migrations recréent le schéma complet, chaîne de migration cassée

**Fichiers :** `migrations/Version20260613190825.php`, `Version20260613194122.php`, `Version20260613194255.php`

Ces trois migrations exécutent chacune des `CREATE TABLE` pour les mêmes tables (announcement, blackout_instances, users, etc.) sans garde `IF NOT EXISTS`.

**Scénario :** Sur une installation fraîche, `doctrine:migrations:migrate` applique `190825` (toutes les tables créées), puis tente `194122` qui exécute exactement les mêmes `CREATE TABLE`. PostgreSQL lève immédiatement `relation already exists`. La chaîne est bloquée et non récupérable sans intervention manuelle.

**Correction :**
- Garder uniquement la migration finale `194255` (la plus complète)
- Supprimer les versions `190752`, `190825` et `194122` qui sont des artefacts de régénérations successives
- S'assurer que la migration restante couvre bien tous les changements cumulés

---

### 🟠 6 — Migration `190752` : `up()` vide, `down()` inversé

**Fichier :** `migrations/Version20260613190752.php` ligne 20

La méthode `up()` est vide (seul un commentaire auto-généré). La méthode `down()` exécute `CREATE SCHEMA public`, ce qui est l'inverse de son rôle.

**Scénario :** Cette migration est marquée comme appliquée dans `doctrine_migration_versions` sans modifier le schéma. Toute migration dépendant d'un état qu'elle était censée créer échoue silencieusement. En cas de rollback, `down()` tente de créer un schéma déjà existant → erreur PostgreSQL.

**Correction :** Supprimer cette migration (elle fait partie du lot à nettoyer — voir point 5).

---

### 🟠 7 — `DateTime::createFromFormat()` non vérifié → 500 sur paramètre malformé

**Fichiers :**
- `src/Presta/Controller/Provider/AgendaController.php` ligne 27
- `src/Presta/Controller/Client/BookingController.php` ligne 40 (paramètre `?week=`)

`DateTime::createFromFormat('Y-m-d', $param)` retourne `false` sur une entrée invalide. La valeur `false` est utilisée directement sans garde, provoquant un `TypeError` fatal.

**Scénario :** Un utilisateur accède à `/presta/provider/agenda/?date=invalid`. `createFromFormat` retourne `false`. L'instruction `clone $currentWeekStart` sur `false` lève un `TypeError` → réponse HTTP 500 sans message d'erreur utilisateur.

À noter : `individualSlots()` dans le même contrôleur gère correctement ce cas avec `if (!$date) $date = new \DateTime();`. Les autres méthodes doivent suivre le même pattern.

**Correction :**
```php
$date = \DateTime::createFromFormat('Y-m-d', $request->query->get('date', ''));
if (!$date) {
    $date = new \DateTime();
}
```

---

### 🟡 8 — Échec de token CSRF silencieux dans `bookGroup()`

**Fichier :** `src/Presta/Controller/Client/BookingController.php` ligne 95

Quand le token CSRF est invalide, le bloc de réservation est sauté sans qu'aucun `addFlash()` ne soit appelé. Le `redirectToRoute()` s'exécute quand même à la fin de la méthode.

**Scénario :** Un utilisateur dont la session a expiré soumet le formulaire. `isCsrfTokenValid()` retourne `false`, aucun message d'erreur n'est affiché, et l'utilisateur est redirigé vers la page du prestataire. Il ne sait pas si sa réservation a réussi ou échoué.

**Correction :**
```php
if (!$this->isCsrfTokenValid('book_group', $request->request->get('_token'))) {
    $this->addFlash('error', 'Session expirée, veuillez réessayer.');
    return $this->redirectToRoute('...');
}
```

---

### 🟡 9 — Accès `session.service.libelle` sans null-guard dans le template

**Fichier :** `templates/presta/provider/session/index.html.twig` ligne 32

`session.service` est accédé directement sans vérification de nullité. Si la relation est rompue (suppression d'un service sans cascade), Twig lève une erreur fatale.

**Scénario :** Un administrateur supprime un `Service`. La FK `Session.service` est `NOT NULL` en base mais sans `ON DELETE CASCADE`. Si la contrainte n'est pas enforced ou si la suppression contourne l'ORM, `getService()` retourne `null`. La page « Mes séances » du prestataire lève alors une erreur Twig et devient inaccessible.

**Correction :**
```twig
{{ session.service ? session.service.libelle : 'Service supprimé' }}
```
Et ajouter `onDelete: "CASCADE"` ou `onDelete: "SET NULL"` sur le `JoinColumn` de `Session::$service`.

---

### 🟡 10 — URL de photo prestataire rendue sans validation de schéma

**Fichiers :**
- `templates/presta/client/prestataire/index.html.twig` ligne 21
- `templates/presta/client/prestataire/show.html.twig` ligne 18

Le champ `photo` est un `TextType` sans contrainte de validation. La valeur est rendue directement en attribut `src=` d'une balise `<img>`.

**Scénario :** Un prestataire malveillant enregistre une `data:` URI comme URL de photo. Certains navigateurs exécutent des data URIs dans des contextes image, permettant du contenu actif. L'auto-escape Twig protège contre le HTML injection mais pas contre les schémas d'URI dangereux.

**Correction :**
- Ajouter une contrainte sur l'entité :
  ```php
  #[Assert\Url(protocols: ['http', 'https'])]
  private ?string $photo = null;
  ```
- Ou utiliser `FileUploadService` existant pour gérer les uploads de photo plutôt qu'une URL libre

---

## Récapitulatif

| # | Sévérité | Fichier | Problème |
|---|----------|---------|----------|
| 1 | 🔴 Critique | `ProviderTrait.php:25` | Tout ROLE_USER devient prestataire |
| 2 | 🔴 Critique | `security.yaml:25` | CSRF désactivé sur le login |
| 3 | 🔴 Élevée | `BookingController.php:261` | Double-réservation race condition |
| 4 | 🔴 Élevée | `BookingController.php:92` | Dépassement capacité TOCTOU |
| 5 | 🟠 Élevée | `migrations/190825.php` | 3 migrations dupliquées, chaîne cassée |
| 6 | 🟠 Moyenne | `migrations/190752.php:20` | `up()` vide, `down()` inversé |
| 7 | 🟠 Moyenne | `AgendaController.php:27` | `createFromFormat` false → 500 |
| 8 | 🟡 Faible | `BookingController.php:95` | Échec CSRF silencieux |
| 9 | 🟡 Faible | `session/index.html.twig:32` | `session.service` sans null-guard |
| 10 | 🟡 Faible | `prestataire/index.html.twig:21` | URL photo sans validation schéma |
