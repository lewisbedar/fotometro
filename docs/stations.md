# Fiches station

La page `GET /stations/{station:slug}` est la page publique centrale d’une station active.

## Contenu

Elle affiche le nom de la station, les pastilles de lignes cliquables, le statut de couverture, le nombre de photographies publiées, la date de dernière photographie disponible, une carte de localisation, la galerie, les catégories représentées et les entrées/sorties connues.

Les photos affichées passent toujours par `Photo::publiclyVisible()` : les brouillons, traitements en attente et erreurs ne sont pas rendus publiquement.

## Galerie

La galerie utilise les miniatures `thumbnail_url` et reste paginée pour éviter de charger trop de photos dans le DOM. L’ordre est `sort_order`, puis `taken_at`, puis `id`.

Les filtres serveur disponibles sont :

- `?category={slug}` pour une catégorie racine ou une sous-catégorie ;
- `?access={id}` pour les photos associées à un accès précis.

Les catégories racines et sous-catégories affichées sont limitées aux catégories réellement présentes dans les photos publiées de la station.

## Accès

Les accès viennent de `station_accesses` et de la table pivot `access_station`. Les libellés publics utilisent, dans l’ordre, `name`, `reference`, `description`, puis un fallback stable `Accès N`. Les identifiants IDFM bruts ne sont pas affichés.

La section “Entrées et sorties” affiche le nombre de photos par accès, quelques miniatures d’aperçu et un lien de filtre vers la galerie principale. Les photos sans `station_access_id` restent visibles dans la galerie générale.

## Carte des accès

La carte des accès est une aide visuelle MapLibre utilisant le fond raster existant. Elle affiche la station, les accès géolocalisés, les accès photographiés et l’accès sélectionné. La liste HTML reste utilisable sans JavaScript.

## Couverture

`StationPhotoCoverageService` fournit le résumé actuellement utilisé :

- `total_photos` ;
- `represented_categories` ;
- `total_accesses` ;
- `photographed_accesses` ;
- `last_photo_at`.

Ce service est prévu pour accueillir plus tard une couverture détaillée sans modifier la vue station.
