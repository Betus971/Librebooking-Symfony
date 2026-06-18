# Générer la migration « baseline » du schéma

Le projet n'avait pas de migration créant le schéma (les entités avaient été
copiées et le schéma monté à la main via `doctrine:schema:create`). Les anciennes
migrations incohérentes ont été supprimées : le dossier `migrations/` est vide.

Cette procédure génère **une seule migration baseline** qui crée tout le schéma
à partir des entités (`src/Entity`), puis seede les tables de référence
(`reservation_statuses`, `reservation_types`).

> ℹ️ Le mapping Doctrine couvre désormais `src/Entity` **et** `src/Presta/Entity`
> (module prestations, câblé en P0.2) → la baseline inclura aussi les tables
> Presta (`prestataire`, `service`, `session`, `plage_horaire`, `inscription`).
> Ces commandes nécessitent PHP + la base : à lancer **sur ta machine**.

## A. Installation neuve / base de dev jetable (recommandé)

```bash
# 1) Repartir d'une base vide
php bin/console doctrine:database:drop --force --if-exists
php bin/console doctrine:database:create

# 2) Générer la baseline complète depuis les entités
php bin/console doctrine:migrations:diff
#  → crée migrations/VersionYYYYMMDDHHMMSS.php avec tous les CREATE TABLE

# 3) (Seed) Ajouter les lignes de référence À LA FIN du up() de la migration
#    générée (voir le bloc SQL ci-dessous), PUIS :
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load --append   # optionnel : données de démo
```

### Seed à coller à la fin du `up()` de la migration générée

```php
        // --- Données de référence (IDs stables attendus par le code) ---
        $this->addSql(<<<'SQL'
            INSERT INTO reservation_statuses (id, label) VALUES
                (1, 'En attente'), (2, 'Confirmée'), (3, 'Refusée'), (4, 'Annulée')
            ON CONFLICT (id) DO NOTHING
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO reservation_types (id, label) VALUES (1, 'Standard')
            ON CONFLICT (id) DO NOTHING
        SQL);
```

(et le `down()` correspondant, si tu veux la réversibilité :)
```php
        $this->addSql('DELETE FROM reservation_types WHERE id = 1');
        $this->addSql('DELETE FROM reservation_statuses WHERE id IN (1, 2, 3, 4)');
```

## B. Tu veux GARDER ta base actuelle (déjà remplie)

Si ta base contient déjà le schéma et des données que tu ne veux pas perdre :

```bash
# 1) Générer la baseline contre une base TEMPORAIRE vide
#    (configure une 2e connexion vide, ou diffe puis vérifie le SQL),
#    OU plus simple : génère-la sur une base de dev vide (méthode A) puis
#    rapporte le fichier migration dans le projet.

# 2) Sur ta vraie base, marque la baseline comme DÉJÀ appliquée (sans la rejouer) :
php bin/console doctrine:migrations:version --add --all --no-interaction

# 3) Vérifie que statuts/types existent, sinon joue le SQL de seed ci-dessus.
```

## Vérification

```bash
php bin/console doctrine:migrations:status     # 1 migration, à jour
php bin/console doctrine:schema:validate       # mapping ↔ base : OK
```

Après ça, `git clone` + méthode A donnera une installation reproductible — c'est
le point P0.1 de `docs/audit.md`.
