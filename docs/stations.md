# Fiches station

La page `GET /stations/{station:slug}` est la page publique centrale d’une station active.

## Contenu

Elle affiche le nom de la station, les pastilles de lignes cliquables, le statut de couverture, le nombre de photographies publiées, la date de dernière photographie disponible, une carte de localisation, la galerie, les catégories représentées et les entrées/sorties connues.

Les photos affichées passent toujours par `Photo::publiclyVisible()` : les brouillons, traitements en attente et erreurs ne sont pas rendus publiquement.

## Galerie

La galerie est un composant Livewire (`App\Livewire\StationGallery`, vue `livewire.station-gallery`) : le filtrage par catégorie/sous-catégorie et par accès, ainsi que la pagination, se font par requêtes AJAX sans recharger la page. Elle utilise les miniatures `thumbnail_url`. L’ordre est `sort_order`, puis `taken_at`, puis `id`.

Le composant garde deux propriétés synchronisées avec la query string (`#[Url]`) :

- `category` : slug d'une catégorie racine ou d'une sous-catégorie ;
- `access` : identifiant d'un accès de la station.

Les liens restent donc partageables/consultables au chargement initial (rendu serveur classique), seules les interactions ultérieures passent par Livewire. Les catégories racines et sous-catégories affichées sont limitées aux catégories réellement présentes dans les photos publiées de la station.

Le filtre par accès peut aussi être déclenché depuis la carte ou la liste des accès (en dehors du composant Livewire) via `Livewire.dispatch('filterByAccess', { accessId })`, écouté par le composant via `#[On('filterByAccess')]`.

## Accès

Les accès viennent de `station_accesses` et de la table pivot `access_station`. Les libellés publics utilisent, dans l’ordre, `name`, `reference`, `description`, puis un fallback stable `Accès N`. Les identifiants IDFM bruts ne sont pas affichés.

Quand `number` est renseigné (numéro de sortie officiel IDFM, voir [idfm-import.md](idfm-import.md)), il est affiché comme badge numéroté à côté du libellé, à la fois dans la liste des accès et sur les marqueurs de la carte. Les accès sont triés par numéro croissant, puis par libellé pour ceux sans numéro.

La section “Station et accès” affiche le nombre de photos par accès et quelques miniatures d’aperçu. Cliquer sur un accès (carte ou liste) le met en évidence sur la carte et filtre la galerie sur cet accès. Les photos sans `station_access_id` restent visibles dans la galerie générale.

## Carte

Une seule carte MapLibre sert à la fois de localisation de la station et de carte des accès (fusion de deux cartes redondantes). Elle affiche le marqueur de la station coloré selon `coverage_status`, les accès géolocalisés, les accès photographiés et l’accès sélectionné. La liste HTML reste utilisable sans JavaScript.

## Couverture

`StationPhotoCoverageService::summarize()` fournit :

- `total_photos` ;
- `represented_categories` ;
- `total_accesses`, `photographed_accesses` ;
- `last_photo_at` ;
- `category_breakdown` : couverture par thématique (Extérieur, Intérieur, Signalétique, Architecture et décoration, Vie et évolution), à titre indicatif ;
- `access_breakdown` : couverture entrées-sorties, à titre indicatif ;
- `essential_coverage` : les deux critères qui définissent réellement une station « bien documentée » (tous les accès + un quai) ;
- `overall_percentage` : identique à `essential_coverage.percentage`.

Voir [photos.md](photos.md#couverture-station) pour le détail du calcul.

## Lightbox

Les liens vers les photos (mosaïque, galerie, miniatures d'accès) passent par le composant Blade `<x-photo-link>` (`resources/views/components/photo-link.blade.php`), qui porte des attributs `data-lightbox-*` (image, titre, description, catégorie, copyright, crédit, licence, date). Le composant Alpine `fotometroLightbox()` (délégation de clic sur `<article x-data="fotometroLightbox()">`) intercepte ces clics et ouvre un aperçu en overlay au lieu de naviguer vers `/photos/{slug}`. Ce composant fonctionne aussi bien pour les liens statiques que pour ceux rendus par Livewire (délégation sur un ancêtre stable). Un lien « Voir la fiche complète » dans la lightbox mène vers la page dédiée (EXIF, localisation, navigation précédente/suivante).
