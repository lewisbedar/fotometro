# Catalogue photographique

Le catalogue photo permet d’associer des images aux stations du métro et, optionnellement, à un accès rattaché à cette station.

## Modèle

`photos` stocke les informations éditoriales, les chemins internes, les métadonnées EXIF, le copyright, la licence, le statut de traitement et la publication.

Une photo appartient obligatoirement à une station. `station_access_id` et `photo_category_id` sont optionnels.

Les originaux sont stockés sur le disque privé configuré par `FOTOMETRO_PHOTO_DISK`, sous `photos/originals`. Les versions publiques sont stockées sur le disque `public`, sous `photos/web` et `photos/thumbnails`.

Les URLs publiques sont produites par les accesseurs `web_url` et `thumbnail_url`, eux-mêmes basés sur `Storage::disk('public')->url(...)`. Elles respectent donc `APP_URL` et la configuration du disque public, sans concaténation manuelle d’hôte.

## Catégories

`photo_categories` gère une arborescence éditable (créer, modifier, supprimer, réordonner par glisser-déposer depuis l'admin) : Extérieur, Intérieur, Entrées et sorties, Signalétique, Architecture et décoration, Détails techniques, Vie et évolution.

Le seeder `PhotoCategorySeeder` est idempotent : il conserve les slugs ASCII existants et met à jour les noms accentués.

## Import

L’administration permet d’importer plusieurs JPEG, PNG ou WebP. Les contrôles portent sur le MIME réel, l’extension, la taille, les dimensions et l’intégrité de l’image.

Le formulaire filtre la localisation dans l’ordre `Ligne -> Station -> Accès`. La ligne sert seulement à filtrer les stations et n’est pas enregistrée sur `photos`. Le sélecteur d’accès affiche uniquement les accès rattachés à la station choisie.

Une petite carte MapLibre reprend la configuration raster existante. Elle affiche la station choisie, les accès géolocalisés et met en évidence l’accès sélectionné. Le select HTML reste la source accessible et le serveur refuse tout accès qui n’appartient pas à la station.

La limite par fichier vient de `FOTOMETRO_PHOTO_MAX_UPLOAD_MB`. La limite de lot vient de `FOTOMETRO_PHOTO_BATCH_LIMIT`.

## Détection automatique de la station (GPS EXIF)

Dès qu'un fichier est choisi sur la page d'import, le front essaie de détecter la station via les coordonnées GPS EXIF (jusqu'à 5 fichiers du lot, dans l'ordre, arrêt au premier succès) en appelant `POST /admin/photos/detect-station`. Cet endpoint réutilise `ExifReader` pour lire le GPS du fichier, puis `App\Services\Stations\NearestStationLocator` pour trouver la station active la plus proche par distance Haversine.

Si une station est trouvée dans le rayon `FOTOMETRO_PHOTO_EXIF_MATCH_RADIUS_METERS` (200 m par défaut), la Ligne et la Station sont présélectionnées automatiquement, avec la distance approximative affichée à l'admin. L'accès reste toujours à choisir manuellement : un point GPS de station ne permet pas de distinguer entre ses différents accès.

En l'absence de GPS exploitable (typique des photos de quai souterraines) ou si aucune station n'est trouvée dans le rayon, la sélection reste entièrement manuelle, comme avant cette fonctionnalité — la détection ne bloque jamais l'import.

## Workflow simplifié

Par défaut, l’administrateur choisit une station, ajoute les fichiers, puis laisse activée l’option `Publier automatiquement une fois les photos prêtes`.

Les photos sont d’abord enregistrées en attente, puis deviennent publiques automatiquement seulement si le traitement réussit. Une photo en erreur n’est jamais publiée.

Le mode `Garder en brouillon` place `publish_when_ready = false` : la photo peut devenir `ready`, mais reste invisible tant qu’un administrateur ne clique pas sur `Publier`.

## Copyright

Priorité du titulaire : formulaire, EXIF exploitable, puis configuration `FOTOMETRO_PHOTO_COPYRIGHT_HOLDER`.

La mention visible est saisie, configurée, ou générée sous la forme `© Titulaire - Tous droits réservés`.

## Publication

Une photo est publique uniquement si `processing_status = ready`, `is_published = true`, `published_at` est nul ou passé, et la station est active.

Les originaux ne sont jamais exposés par les vues publiques.

La dépublication remet `is_published = false` et `published_at = null`; la date de publication est donc la date de la dernière publication active.

## Couverture Station

Règle « suffisante » (`StationPhotoCoverageService::essentialCoverage()`) : une station est considérée comme bien documentée dès qu'elle a une photo de chaque accès actif et au moins une photo de quai (sous-catégorie `interieur-quai`). C'est ce qui pilote `Station::coverage_percentage` et `coverage_status`.

`percentage` est la moyenne de deux composantes : le pourcentage d'accès photographiés (`accessBreakdown`), et 100/0 selon qu'un quai est photographié ou non. Une station sans accès enregistré n'est pas pénalisée : cette composante est alors exclue de la moyenne plutôt que comptée à 0 (mais une station sans aucune photo affiche bien 0 %, pas un plancher artificiel).

`coverage_status` : 0 % donne `not_started` ; entre les deux donne `in_progress` ; `complete` (les deux critères remplis) donne `documented`. `planned` et `complete` (statut manuel) restent manuels et ne sont pas écrasés automatiquement ; `coverage_percentage` continue toutefois à être recalculé même dans ces statuts.

`StationCoverageUpdater` recalcule ceci aux mêmes points d'accroche qu'avant (création, mise à jour, suppression et actions groupées de photos). La commande `fotometro:recalculate-coverage` permet un recalcul rétroactif complet.

Le détail par thématique (`categoryBreakdown()` : Extérieur, Intérieur, Entrées et sorties, Signalétique, Architecture et décoration, Détails techniques, Vie et évolution) et par accès (`accessBreakdown()`) reste calculé et exposé par `summarize()` à titre indicatif (« ce qu'il manque »), mais ne pèse plus dans le pourcentage global.

## Consultation publique

La fiche station est la porte d’entrée principale du catalogue photographique. Elle affiche une mosaïque de photos vedettes (jusqu'à 4 : la photo de couverture en premier si définie, puis `is_featured` en priorité), une galerie paginée avec filtres par catégories représentées et par accès (voir [stations.md](stations.md#galerie) pour le fonctionnement sans rechargement de page).

## Photo de couverture

`Station::cover_photo_id` (nullable, FK vers `photos`, `nullOnDelete`) identifie la photo qui représente la station : elle passe en premier dans la mosaïque et alimente la vignette affichée dans le popup de la carte publique. Seule une photo publiquement visible (`Photo::publiclyVisible()`) peut être définie comme couverture (bouton sur la fiche admin de la photo) ; dépublier la photo de couverture la retire automatiquement (`PhotoPublicationService::unpublish()`).

La page photo conserve la navigation précédente/suivante dans la même station uniquement, en respectant l’ordre public `sort_order`, `taken_at`, `id`. Les métadonnées vides ne sont pas affichées, et les coordonnées GPS EXIF de la photo ne sont pas exposées publiquement.
