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
CACHE_STORE=database
QUEUE_CONNECTION=database

ADMIN_NAME="Administrateur fotometro"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=mot-de-passe-fort
```

Ne versionnez jamais le `.env` de production.

## Installation sur le serveur

```bash
composer install --no-dev --optimize-autoloader
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Si `APP_KEY` est déjà définie, ne relancez pas `key:generate`.

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
