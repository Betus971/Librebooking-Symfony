@echo off
REM ==============================================================
REM Build production pour deploiement en environnement ferme
REM A lancer depuis la racine du projet : .\build-prod.bat
REM ==============================================================

echo.
echo === [1/5] Nettoyage du cache et logs ===
rmdir /s /q var\cache 2>nul
rmdir /s /q var\log 2>nul
mkdir var\cache
mkdir var\log

echo.
echo === [2/5] Composer install (no-dev, optimized) ===
call composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction
if errorlevel 1 goto :error

echo.
echo === [3/5] Cache clear + warmup PROD ===
set APP_ENV=prod
set APP_DEBUG=0
php bin\console cache:clear --env=prod --no-debug
if errorlevel 1 goto :error
php bin\console cache:warmup --env=prod --no-debug
if errorlevel 1 goto :error

echo.
echo === [4/5] Compilation des assets (asset-map:compile) ===
php -d memory_limit=512M bin\console asset-map:compile --env=prod --no-debug
if errorlevel 1 goto :error

echo.
echo === [5/5] Termine ===
echo.
echo Le projet est pret. Verifie public\assets\ et var\cache\prod\.
echo.
echo PROCHAINES ETAPES :
echo  - scp/rsync le dossier vers le serveur (exclure var\cache, var\log, .git, node_modules)
echo  - Sur le serveur : creer .env.local avec :
echo        APP_ENV=prod
echo        APP_SECRET=^<nouveau secret^>
echo        DATABASE_URL="postgresql://USER:PASS@HOST:5432/booking?serverVersion=17"
echo  - Sur le serveur : php bin/console cache:clear --env=prod
echo  - Sur le serveur : php bin/console doctrine:migrations:migrate --env=prod
echo  - Sur le serveur : reinserer les SEEDS (reservation_statuses, reservation_types, etc.)
echo.
goto :eof

:error
echo.
echo *** ERREUR pendant le build, arret. ***
exit /b 1
