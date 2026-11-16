#!/usr/bin/env bash
#
# Mise à jour de Librebooking en production (à lancer SUR le serveur).
#
#   sudo bash /var/www/librebooking/deploy/update.sh
#
# Étapes : git pull → composer → npm (si besoin) → migrations → Tailwind →
# assets → cache → permissions → redémarrage PHP-FPM → vérification /health.
# Les vraies valeurs (BDD, APP_SECRET…) restent dans .env.local, jamais touché.
#
set -euo pipefail
# Bloc { … } : bash lit tout le script avant de l'exécuter, ce qui permet au
# git pull ci-dessous de mettre ce fichier à jour sans casser l'exécution en cours.
{

APP_DIR="${APP_DIR:-/var/www/librebooking}"
BRANCH="${BRANCH:-main}"
WEB_USER="${WEB_USER:-ubuntu}"
WEB_GROUP="${WEB_GROUP:-www-data}"
# Service PHP-FPM détecté automatiquement (php8.4-fpm, php8.5-fpm…), forçable via PHP_FPM=…
PHP_FPM="${PHP_FPM:-$(systemctl list-units --type=service --state=running --no-legend 'php*-fpm.service' 2>/dev/null | awk '{print $1}' | head -1)}"
HEALTH_URL="${HEALTH_URL:-https://librebooking.demo.ubikd.com/health}"

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
fail() { printf '\n\033[1;31m✗ %s\033[0m\n' "$*" >&2; exit 1; }
# bin/console sans le bruit des logs « deprecation » (le code retour de PHP est conservé)
console() { php bin/console "$@" 2>&1 | { grep -v 'deprecation\.' || true; }; return "${PIPESTATUS[0]}"; }

[[ $EUID -eq 0 ]] || fail "Lancer avec sudo."
cd "$APP_DIR" || fail "Dossier $APP_DIR introuvable."
[[ -f .env.local ]] || fail ".env.local absent : créer-le avant (DATABASE_URL, APP_SECRET, APP_ENV=prod…)."

git config --global --add safe.directory "$APP_DIR" >/dev/null 2>&1 || true

step "Code ($BRANCH)"
before=$(git rev-parse --short HEAD)
git diff --quiet --ignore-cr-at-eol || fail "Modifs locales sur le serveur (git status) : à commiter ou annuler avant la mise à jour."
git pull --ff-only --quiet origin "$BRANCH" || fail "git pull impossible (historique divergent ?)."
after=$(git rev-parse --short HEAD)
echo "$before → $after"
git log --oneline "$before..$after" 2>/dev/null | head -20 || true

export APP_ENV=prod COMPOSER_ALLOW_SUPERUSER=1

step "Dépendances PHP"
composer install --no-dev --optimize-autoloader --no-interaction --quiet

step "Dépendances front"
if [[ ! -x node_modules/.bin/tailwindcss ]] || ! git diff --quiet "$before" "$after" -- package.json package-lock.json 2>/dev/null; then
    corepack npm install --no-audit --no-fund --silent
else
    echo "inchangées"
fi

step "Migrations"
console doctrine:migrations:migrate --no-interaction --allow-no-migration

step "CSS + assets"
./node_modules/.bin/tailwindcss -i assets/styles/app.css -o var/tailwind/app.built.css --minify 2>&1 | tail -1
console asset-map:compile --quiet
console cache:clear --quiet

step "Permissions + PHP-FPM"
chown -R "$WEB_USER:$WEB_GROUP" var public
chmod -R 775 var
[[ -n "$PHP_FPM" ]] || fail "Aucun service PHP-FPM actif trouvé."
echo "$PHP_FPM"
systemctl restart "$PHP_FPM"

step "Vérification"
console doctrine:schema:validate --skip-mapping | grep -E 'OK|ERROR' || true
sleep 1
code=$(curl -s -o /tmp/lb-health.json -w '%{http_code}' "$HEALTH_URL" || echo 000)
cat /tmp/lb-health.json 2>/dev/null; echo
[[ "$code" == "200" ]] || fail "/health répond $code"
printf '\n\033[1;32m✓ Librebooking à jour (%s)\033[0m\n' "$after"
exit 0
}
