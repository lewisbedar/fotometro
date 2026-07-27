# fotometro

fotometro est une application web Laravel pour cataloguer et présenter des photographies des stations du métro parisien.

Cette première version pose uniquement les fondations: authentification administrateur, lignes, stations, relations, données de démonstration, interface provisoire et documentation.

## Technologies

- PHP 8.3 ou supérieur
- Laravel 13
- MySQL 8 ou MariaDB compatible
- Blade
- Livewire
- Alpine.js
- Tailwind CSS 4
- Vite
- Apache en production

MapLibre GL JS est prévu pour une étape ultérieure.

## Prérequis locaux

- PHP avec les extensions usuelles Laravel: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`
- Composer
- Node.js et npm, uniquement pour compiler les assets
- MySQL ou MariaDB

## Installation locale

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Sous PowerShell, utilisez `npm.cmd install` si l'exécution de scripts bloque `npm.ps1`.

## Configuration MySQL

Créez une base et un utilisateur MySQL, puis renseignez `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fotometro
DB_USERNAME=fotometro
DB_PASSWORD=mot-de-passe-local
```

## Migration et données de démonstration

```bash
php artisan migrate
php artisan db:seed
```

Le seeder ajoute les lignes 1, 4, 6 et 14, quelques stations, et au moins une station de correspondance.

## Compte administrateur

L'inscription publique n'existe pas. Le compte administrateur unique est créé par le seeder à partir de ces variables:

```dotenv
ADMIN_NAME="Administrateur fotometro"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=choisir-un-mot-de-passe
```

Définissez `ADMIN_PASSWORD` avant `php artisan db:seed`. En local, la valeur de secours est `password`, mais elle ne doit pas être utilisée en production.

## Compilation des ressources

```bash
npm run build
```

En développement:

```bash
npm run dev
```

## Tests

```bash
php artisan test
```

Les tests utilisent SQLite en mémoire via `phpunit.xml`.

## Pages disponibles

- `/`: accueil public
- `/login`: connexion administrateur
- `/admin`: tableau de bord administrateur protégé

## Déploiement

Voir [docs/deployment-o2switch.md](docs/deployment-o2switch.md) pour un déploiement sur hébergement mutualisé o2switch sans Docker, sans Redis et sans serveur Node.js permanent.
