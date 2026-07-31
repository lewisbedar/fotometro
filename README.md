# fotometro

fotometro est une application web Laravel pour cataloguer et présenter des photographies des stations du métro parisien.

La page publique principale est désormais un explorateur cartographique: lignes, stations, recherche, filtres de couverture photographique, fiches publiques de stations et fiches publiques de lignes.

## Technologies

- PHP 8.3 ou supérieur
- Laravel 13
- MySQL 8 ou MariaDB compatible
- Blade et Livewire
- Alpine.js
- Tailwind CSS 4
- MapLibre GL JS
- Vite
- Apache en production

Node.js sert uniquement à compiler les assets. Aucun serveur Node permanent, Redis, Docker, WebSocket ou service système spécifique n'est requis.

## Installation locale

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install
php artisan migrate
php artisan db:seed
npm run build
```

Sous PowerShell, utilisez `npm.cmd install` et `npm.cmd run build` si `npm.ps1` est bloqué.

## Configuration MySQL

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fotometro
DB_USERNAME=fotometro
DB_PASSWORD=mot-de-passe-local
```

## Configuration de la carte

```dotenv
FOTOMETRO_MAP_BASEMAP_DRIVER=raster
FOTOMETRO_MAP_RASTER_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png
FOTOMETRO_MAP_RASTER_TILE_SIZE=256
FOTOMETRO_MAP_STYLE_URL=
FOTOMETRO_MAP_ATTRIBUTION=© OpenStreetMap contributors
FOTOMETRO_MAP_CACHE_TTL=300
FOTOMETRO_MAP_CENTER_LATITUDE=48.8566
FOTOMETRO_MAP_CENTER_LONGITUDE=2.3522
FOTOMETRO_MAP_DEFAULT_ZOOM=11.5
FOTOMETRO_MAP_MAX_ZOOM=19
```

Le driver par défaut est `raster`. En développement et pour les essais, le serveur standard `tile.openstreetmap.org` fournit un fond simple sans clé. Avant une mise en production publique, choisissez un fournisseur de tuiles raster adapté au trafic prévu et conforme à sa politique d'utilisation.

Le driver `style` reste disponible pour tester ultérieurement un style vectoriel MapLibre compatible. Si l'URL requise par le driver choisi est vide, l'application affiche un message propre au lieu d'initialiser une carte cassée. Aucune clé secrète ne doit être commitée.

## Compte administrateur

Le seeder crée le compte administrateur initial depuis:

```dotenv
ADMIN_NAME="Administrateur fotometro"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=choisir-un-mot-de-passe
```

L'inscription publique (`/inscription`) existe par ailleurs : tout compte créé reste `pending` jusqu'à approbation par un administrateur depuis `/admin/users`, et le formulaire est protégé par un champ honeypot invisible (`RegisteredUserController::store()`).

## Pages et routes publiques

- `/`: explorateur cartographique
- `/api/map`: données publiques de carte
- `/api/map/search?q=...`: recherche publique limitée (aussi utilisée par la barre de recherche du layout standard, voir `docs/map.md`)
- `/stations/{slug}`: fiche publique d'une station
- `/lignes/{slug}`: fiche publique d'une ligne
- `/photos/{slug}`: fiche publique d'une photo
- `/profil/{username}`: profil public d'un contributeur
- `/sitemap.xml`, `/robots.txt`: générés dynamiquement (`SitemapController`, `routes/web.php`)
- `/login`, `/inscription`: connexion et inscription
- `/televerser`: ajout de photo par un compte approuvé
- `/admin`: tableau de bord administrateur/modérateur

## Tests

```bash
php artisan test
```

Les tests utilisent SQLite en mémoire et désactivent Vite côté requêtes HTTP.

## Documentation

- [Spécification produit](docs/product-specification.md)
- [Base de données](docs/database.md)
- [Carte interactive](docs/map.md)
- [Déploiement o2switch](docs/deployment-o2switch.md)

## Interface cartographique plein ecran

L'accueil utilise une carte MapLibre plein ecran avec panneaux flottants: barre superieure, recherche, progression, filtres, selection de lignes, panneaux contextuels et schema dynamique de ligne. Voir [Interface cartographique plein ecran](docs/ui-map-explorer.md).

Les schemas de ligne utilisent maintenant une topologie dediee issue du GTFS. L'import `php artisan fotometro:import-network --only=gtfs` ou `--only=topology` remplit `line_station_sequences`, applique les orientations canoniques et expose `lines[].topology` dans `/api/map`. Voir [Topologie des lignes](docs/line-topology.md).
# Import du reseau IDFM

Le reseau metro peut etre synchronise depuis les jeux de donnees publics d'Ile-de-France Mobilites avec:

```bash
php artisan fotometro:import-network --dry-run
php artisan fotometro:import-network
```

L'import cree ou met a jour lignes, stations, correspondances, ordre GTFS, terminus, sequences topologiques, branches, traces GeoJSON valides et acces de station si les URLs sont configurees. Il ne supprime jamais automatiquement: les donnees absentes sont desactivees. Les champs editoriaux de fotometro, dont les statuts de couverture photographique, ne sont pas ecrases.

Voir `docs/idfm-import.md` pour les sources, variables d'environnement et procedures o2switch.

`arrets-lignes` utilise automatiquement l'export CSV IDFM (`/exports/csv?limit=-1`) afin d'eviter la limite des 10 000 enregistrements de l'endpoint `/records`.

## Catalogue photographique

Le socle photo ajoute des categories administrables, l'import multi-fichiers, le stockage prive des originaux, la generation de versions web et miniatures, la lecture EXIF tolerante, les licences et la publication publique par station.

Le workflow admin est volontairement simple: choisir une station, importer les photos, garder l'option de publication automatique activee, puis laisser la commande de traitement publier les images des qu'elles sont pretes. Le mode brouillon reste disponible pour preparer des photos sans les rendre publiques.

Commandes utiles:

```bash
php artisan db:seed --class=PhotoCategorySeeder
php artisan fotometro:process-photos --limit=10
```

Voir `docs/photos.md` et `docs/photo-processing.md`.
