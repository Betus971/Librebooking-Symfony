# Stratégie Open Source & Refonte Tailwind

Ce document formalise la stratégie pour transformer le projet interne (Booking-D) en un produit open source public, moderne et neutre. Cette transition nécessite d'épurer le code des spécificités institutionnelles, d'adopter une stack UI internationale (TailwindCSS) et de respecter le cadre légal de publication par un agent de l'État.

## 1. Inventaire : Séparation du Public et du Privé

Avant de publier le moindre commit, le code doit être purgé de toutes références à la Gendarmerie ou à ses processus métiers spécifiques.

### 🔴 À supprimer (Code Métier "Law-Enforcement")
Le module Visiteurs, IFPR et Badges est propre au fonctionnement Gendarmerie. Ces éléments n'ont pas leur place dans le cœur open source.
- **Entités :** `Visitor`, `VisitGroup`, `VisitorCategory`, `Ifpr`, `Badge`, `BadgeType`, `BadgeAttribution`
- **Contrôleurs :** `VisitorController`, `GestionVisiteurController`, `IfprController`, `BadgeController`, `BadgeTypeController`, `BadgeAttributionController`, `StatistiqueController`
- **Formulaires :** Tous les types associés (`VisitorType`, `IfprType`, etc.)
- **Services & Repositories :** `VisiteurService`, `IfprService`, et tous les repositories liés.
- **Templates & Migrations :** Dossiers de templates associés (`visitor/`, `ifpr/`, `badge/`, etc.) et les migrations créant ces tables spécifiques.

### 🟡 À extraire ou abstraire
- **SSO LemonLDAP :** `LemonLdapAuthenticator.php` doit être extrait. Le cœur open source proposera une authentification générique (HTTP Headers, Formulaire standard).
- **Visibilité `codeUnite` :** Remplacer le concept de `codeUnite` par une notion abstraite `tenant_id` ou `scope_id` dans `Resource.php`, `ResourceVoter.php`, et les repositories.

### 🟢 À conserver (La valeur de la communauté)
- **Le Core Métier :** `Resource`, `Schedule`, `Layout`, `TimeBlock`, `ReservationSeries`, `ReservationInstance`
- **La Logique de Réservation :** `AvailabilityService`, `ResourceRulesChecker`, `ReservationManager`
- **Composants Techniques :** Asset Mapper Symfony, SecurityHeadersSubscriber, validation des entités.

---

## 2. Plan de Refonte et Sortie (Roadmap)

Le DSFR marquant le projet institutionnellement, le choix a été fait de basculer sur **TailwindCSS** pour garantir une adoption internationale et offrir un design neutre et moderne.

### Phase 0 : Le "Strip" (Nouveau Dépôt Épuré)
- Création d'un **nouveau repository git** pour repartir d'un historique vierge (aucun commit contenant des secrets ou des historiques Gendarmerie).
- Suppression stricte de tous les fichiers 🔴 listés ci-dessus.
- À ce stade, les templates Twig contiennent toujours des classes DSFR (`fr-*`), l'application est fonctionnelle mais le design est cassé/hybride.
- **Objectif :** Obtenir un code technique propre et inoffensif.

### Phase 1 : Tailwind Theme (Release v0.1.0)
- Refonte UI complète de l'application (~50 templates).
- Intégration de TailwindCSS (via Asset Mapper, sans NodeJS).
- Refonte du calendrier interactif, de la pagination et des composants (flashes, formulaires).
- Mise en place d'un système de thème basique : `templates/base.html.twig` et un sous-dossier `_themes/default/`.
- **Objectif :** Un projet open source visuellement attractif, avec une première release officielle `v0.1.0`.

### Phase 2 : Architecture par Plugins (v1.0)
- Sortie de l'authentification en module séparé.
- Côté Gendarmerie, le dépôt interne devient un simple consommateur du dépôt public (dépendance Composer), sur lequel vient se greffer un bundle privé (ex: `BookingDggnSsoBundle`) et le module "Visiteurs".
- **Objectif :** Fini le cauchemar des forks divergents. Le projet central vit sa vie, la Gendarmerie maintient juste sa "surcouche" interne.

---

## 3. Autorisation Hiérarchique & Cadre Légal

La publication de code par un agent de l'État (Loi pour une République Numérique, 2016) est un droit encouragé, mais normé.

> [!IMPORTANT]
> Ne publiez aucun code avant d'avoir validé ces étapes.

- [ ] **Présentation au Maître d'Apprentissage :** Présenter ce document et le plan de découpe pour valider le principe.
- [ ] **Validation Juridique :** Contacter le pôle juridique de l'ANFSI pour s'assurer que le nom du projet, l'origine du code et l'absence de données sensibles sont conformes.
- [ ] **Licence Librebooking :** Librebooking est historiquement sous **GPL v3**. Notre code reprenant sa base de données et sa logique métier (œuvre dérivée), nous devons utiliser une licence compatible, très probablement **GPL v3**. Ce point est à entériner avec le juridique.
- [ ] **Plateforme Code.gouv.fr :** Envisager la publication sous l'égide de l'État via la DINUM, ce qui simplifie souvent l'autorisation hiérarchique en l'inscrivant dans une démarche officielle.

---

## 4. Choix du Nom (Naming)

Pour se détacher de "Booking-D" et s'ouvrir à l'international, voici les pistes (à choisir) :

1. **Bookr** : Court, mémorable.
2. **OpenSeat** : Fait très pro, s'adapte aussi au coworking/bureaux.
3. **Reserva** : International et direct.
4. **SymBook** / **SymfonyBooking** : Mise en avant de la stack technique.
5. **Slotty** : Plus startup/fun.

*Recommandation : **Bookr** ou **OpenSeat**.*

---

## 5. Squelette des documents fondateurs du repo public

Lors de la création du repo, ces fichiers devront être rédigés en **Anglais**.

- **README.md :** Présentation du projet (screenshots), pré-requis, installation en 3 commandes, architecture générale.
- **LICENSE :** Fichier complet de la licence GPL v3 (à vérifier par rapport à l'existant).
- **CONTRIBUTING.md :** Comment lancer les tests, standards de code (PSR-12), et comment proposer une Pull Request.
- **SECURITY.md :** Contact email pour reporter une vulnérabilité de manière privée avant divulgation publique.
