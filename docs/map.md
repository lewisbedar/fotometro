# Carte interactive

## Architecture

La page `/` rend l'interface Blade et les compteurs calculés côté Laravel. Les données cartographiques sont ensuite chargées par Alpine.js depuis `/api/map`. MapLibre GL JS est compilé par Vite et chargé dynamiquement uniquement quand une carte doit être affichée.

## Routes

- `GET /api/map`: lignes, stations actives avec coordonnées, statuts et progression
- `GET /api/map/search?q=...`: recherche publique de stations, limitée par middleware `throttle:30,1`
- `GET /stations/{station:slug}`: fiche publique d'une station active
- `GET /lignes/{line:slug}`: fiche publique d'une ligne

## Format des données

`/api/map` retourne:

- `lines`: id, code, nom, slug, couleurs, URL publique, tracé GeoJSON facultatif
- `stations`: id, nom, slug, coordonnées, localisation, statut, URL publique, lignes associées
- `coverage_statuses`: valeurs, libellés, descriptions et couleurs
- `progress`: total, stations documentées, pourcentage, nombre de lignes, stations sans coordonnées

Les données sont sérialisées avec `MapLineResource` et `MapStationResource`.

## Fond de carte

La configuration vient de `config/fotometro.php`:

```dotenv
FOTOMETRO_MAP_BASEMAP_DRIVER=raster
FOTOMETRO_MAP_RASTER_URL=https://tile.openstreetmap.org/{z}/{x}/{y}.png
FOTOMETRO_MAP_RASTER_TILE_SIZE=256
FOTOMETRO_MAP_STYLE_URL=
FOTOMETRO_MAP_ATTRIBUTION=© OpenStreetMap contributors
FOTOMETRO_MAP_CACHE_TTL=300
FOTOMETRO_MAP_MAX_ZOOM=19
```

`FOTOMETRO_MAP_BASEMAP_DRIVER` accepte `raster` ou `style`. Le mode `raster` est la valeur par défaut et construit un style MapLibre local avec une source raster. Le mode `style` charge directement l'URL `FOTOMETRO_MAP_STYLE_URL` pour conserver une voie de test vectorielle.

En développement et pour les essais, `https://tile.openstreetmap.org/{z}/{x}/{y}.png` est utilisé comme fond raster simple. Avant une mise en production publique, il faudra choisir un fournisseur de tuiles raster conforme à l'usage prévu, au trafic attendu et à sa politique d'utilisation.

Si l'URL requise par le driver choisi est vide, l'interface affiche un message de configuration au lieu d'initialiser MapLibre.

La page `/map-diagnostic` reste disponible en local: `?style=raster` teste le fond retenu, `?style=vector` teste le style vectoriel CARTO expérimental.

## Tracés

`lines.path_geojson` stocke un tracé GeoJSON nullable. Quand un tracé est absent, la carte continue d'afficher les stations et la fiche ligne affiche un état vide.

Les tracés du seeder sont des tracés de démonstration fictifs qui relient quelques stations dans leur ordre. Ils ne sont pas les tracés officiels du métro parisien.

## Sélection

La sélection d'une ligne filtre les stations visibles, met en évidence le tracé de la ligne et ajuste le zoom. La sélection d'une station ouvre un panneau contextuel et une popup construite avec des noeuds DOM, sans injection HTML non échappée.

## Recherche

La recherche appelle `/api/map/search`, filtre les noms de stations côté PHP avec normalisation ASCII et casse-insensibilité, puis permet la sélection au clavier avec les flèches et Entrée.

## Cache

`/api/map` utilise `Cache::remember` avec une durée courte configurable. Le cache fonctionne avec `file`, `database` ou `array` et ne dépend pas de Redis.

## Limites actuelles

- Les données de démonstration sont volontairement réduites.
- Les tracés ne sont pas officiels.
- Aucun clustering n'est ajouté dans cette version.
- Aucun import IDFM ni photographie réelle n'est encore géré.

## API du schema de ligne

`/api/map` expose, pour chaque ligne, les stations ordonnees par `station_line.position`. Chaque station de ligne contient `position`, `branch`, `is_terminus`, ses coordonnees, son statut de couverture et ses correspondances sans repeter la ligne active.

Quand `path_geojson` est `null`, le frontend cadre la ligne a partir des coordonnees de ses stations. Les donnees de demonstration ne fournissent plus de faux traces.
# Donnees IDFM

`/api/map` expose maintenant les identifiants `external_id` des lignes et stations, le nombre d'acces connus, et les stations de chaque ligne ordonnees par `station_line.position` avec `branch`, `is_terminus`, statut de couverture et correspondances. `source_payload` reste strictement interne.
