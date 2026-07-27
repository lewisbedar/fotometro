# Déploiement o2switch

Ce projet est prévu pour un hébergement mutualisé o2switch classique: PHP, Composer, MySQL, Apache et cron. La production ne nécessite pas Docker, Redis, serveur Node.js permanent ou accès administrateur.

## Préparer localement

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

Déployez ensuite le code, `vendor/` si Composer n'est pas exécuté sur le serveur, et le dossier `public/build` généré par Vite.

## Configuration serveur

Le domaine doit pointer vers le dossier `public` de Laravel. Si l'interface o2switch impose un dossier public différent, placez le projet hors racine web et faites pointer le domaine ou sous-domaine vers `public`.

Le fichier `.env` de production doit contenir au minimum:

```dotenv
APP_NAME=fotometro
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://example.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nom_base
DB_USERNAME=nom_utilisateur
DB_PASSWORD=mot_de_passe

SESSION_DRIVER=database
CACHE_STORE=file
QUEUE_CONNECTION=database

FOTOMETRO_MAP_BASEMAP_DRIVER=raster
FOTOMETRO_MAP_RASTER_URL=https://fournisseur.example/{z}/{x}/{y}.png
FOTOMETRO_MAP_RASTER_TILE_SIZE=256
FOTOMETRO_MAP_STYLE_URL=
FOTOMETRO_MAP_ATTRIBUTION="© fournisseur du fond de carte"
FOTOMETRO_MAP_CACHE_TTL=300
FOTOMETRO_MAP_MAX_ZOOM=19

ADMIN_NAME="Administrateur fotometro"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=mot-de-passe-fort
```

Ne versionnez jamais le `.env` de production.

Le serveur standard `tile.openstreetmap.org` convient au développement et aux essais, mais ne doit pas être retenu par défaut pour une mise en production publique sans vérifier sa politique d'utilisation. Choisissez un fournisseur de tuiles raster compatible avec le volume et l'usage réel du site.

## Installation sur le serveur

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Si `APP_KEY` n'est pas déjà définie, générez-la avec `php artisan key:generate --force`.

## Carte

MapLibre GL JS est compilé par Vite. Aucun serveur Node.js permanent n'est nécessaire en production. Vérifiez que le fournisseur du style de carte autorise l'usage prévu, que l'attribution est affichée, et qu'aucune clé privée n'est stockée dans le dépôt.

## Cron

Ajoutez une tâche cron Laravel classique:

```cron
* * * * * cd /home/USER/fotometro && php artisan schedule:run >> /dev/null 2>&1
```

Aucun worker permanent n'est requis à cette étape.

## Stockage

Quand la gestion des photographies sera ajoutée:

```bash
php artisan storage:link
```

Vérifiez que les dossiers `storage` et `bootstrap/cache` sont inscriptibles par PHP.

## Mise à jour

```bash
php artisan down
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Compilez les assets localement ou sur le serveur avec `npm run build`, puis conservez uniquement le résultat statique servi par Apache.
# Import IDFM sur o2switch

L'import du reseau metro reste compatible avec l'hebergement mutualise: il s'agit d'une commande Artisan ponctuelle.

```bash
php artisan fotometro:import-network --dry-run
php artisan fotometro:import-network
```

Utiliser `--dry-run` avant toute synchronisation reelle, verifier le rapport, puis lancer l'import sans option si les donnees sont coherentes. Aucun processus resident, Redis, Docker ou worker n'est requis. Le cache de `/api/map` est invalide apres un import reussi.

Le dataset `arrets-lignes` est telecharge via `/exports/csv?limit=-1` dans le repertoire temporaire configure par `FOTOMETRO_IDFM_TEMP_DIR`, puis supprime apres succes lorsque `APP_DEBUG=false`.

Pendant un diagnostic local, `/debug/database` permet de comparer la connexion web avec la connexion CLI. Cette route retourne 404 hors local et doit etre retiree quand le diagnostic MySQL/cache est termine.
