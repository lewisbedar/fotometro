# Base de données

La base cible est MySQL ou MariaDB. Les tests automatisés utilisent SQLite en mémoire, ce qui impose de garder des migrations portables lorsque c'est raisonnable.

## Table `lines`

| Colonne | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned | Clé primaire |
| `code` | varchar(12) | Unique, exemples: `1`, `3bis`, `14` |
| `name` | varchar | Nom affichable |
| `slug` | varchar | Unique |
| `color` | varchar(7) | Couleur hexadécimale de ligne |
| `text_color` | varchar(7) | Couleur de texte adaptée |
| `sort_order` | smallint unsigned | Ordre d'affichage |
| `path_geojson` | json nullable | Tracé GeoJSON facultatif |
| `created_at`, `updated_at` | timestamps |  |

## Table `stations`

| Colonne | Type | Notes |
| --- | --- | --- |
| `id` | bigint unsigned | Clé primaire |
| `external_id` | varchar nullable | Unique, prévu pour un futur import |
| `name` | varchar | Nom de station |
| `slug` | varchar | Unique |
| `latitude` | decimal(10,7) nullable | Coordonnée WGS84 |
| `longitude` | decimal(10,7) nullable | Coordonnée WGS84 |
| `city` | varchar nullable | Commune |
| `postal_code` | varchar(10) nullable | Code postal |
| `district` | varchar nullable | Arrondissement ou secteur |
| `opening_date` | date nullable | Date d'ouverture si connue |
| `description` | text nullable | Texte éditorial |
| `coverage_status` | varchar(32) | Statut photographique |
| `is_active` | boolean | Station visible ou exploitable |
| `created_at`, `updated_at` | timestamps |  |

## Statuts photographiques

Le code PHP expose `App\Enums\CoverageStatus`. En base, la valeur reste une chaîne compatible MySQL:

- `not_started`
- `planned`
- `in_progress`
- `documented`
- `complete`

## Table pivot `station_line`

| Colonne | Type | Notes |
| --- | --- | --- |
| `station_id` | bigint unsigned | FK vers `stations`, suppression en cascade |
| `line_id` | bigint unsigned | FK vers `lines`, suppression en cascade |
| `position` | smallint unsigned | Position de la station sur la ligne |
| `branch` | varchar nullable | Branche éventuelle |
| `is_terminus` | boolean | Indique un terminus |
| `created_at`, `updated_at` | timestamps |  |

La clé primaire composée est `station_id, line_id`. Un index `line_id, position` facilite l'affichage ordonné des stations d'une ligne.
# Synchronisation IDFM

Les tables publiques conservent les champs editoriaux de fotometro et ajoutent des metadonnees d'import: `external_id` quand necessaire, `source`, `source_payload`, `source_updated_at` et `is_active`. Les payloads source sont conserves pour audit technique et ne sont pas exposes par l'API publique.

`station_accesses` stocke les acces et sorties issus des sources IDFM. `access_station` relie un acces a une ou plusieurs stations.

# Catalogue photographique

Le catalogue photo ajoute `photo_categories` et `photos`.

`photos.station_id` est obligatoire. `photos.station_access_id` reste optionnel et doit correspondre a un acces rattache a la station choisie. Les chemins `original_path`, `web_path` et `thumbnail_path` sont des chemins de stockage Laravel, jamais des chemins systeme exposes.
