# Catalogue photographique

Le catalogue photo permet d’associer des images aux stations du métro et, optionnellement, à un accès rattaché à cette station.

## Modèle

`photos` stocke les informations éditoriales, les chemins internes, les métadonnées EXIF, le copyright, la licence, le statut de traitement et la publication.

Une photo appartient obligatoirement à une station. `station_access_id` et `photo_category_id` sont optionnels.

Les originaux sont stockés sur le disque privé configuré par `FOTOMETRO_PHOTO_DISK`, sous `photos/originals`. Les versions publiques sont stockées sur le disque `public`, sous `photos/web` et `photos/thumbnails`.

Les URLs publiques sont produites par les accesseurs `web_url` et `thumbnail_url`, eux-mêmes basés sur `Storage::disk('public')->url(...)`. Elles respectent donc `APP_URL` et la configuration du disque public, sans concaténation manuelle d’hôte.

## Catégories

`photo_categories` gère une arborescence éditable : Extérieur, Intérieur, Signalétique, Architecture et décoration, Vie et évolution.

Le seeder `PhotoCategorySeeder` est idempotent : il conserve les slugs ASCII existants et met à jour les noms accentués.

## Import

L’administration permet d’importer plusieurs JPEG, PNG ou WebP. Les contrôles portent sur le MIME réel, l’extension, la taille, les dimensions et l’intégrité de l’image.

Le formulaire filtre la localisation dans l’ordre `Ligne -> Station -> Accès`. La ligne sert seulement à filtrer les stations et n’est pas enregistrée sur `photos`. Le sélecteur d’accès affiche uniquement les accès rattachés à la station choisie.

Une petite carte MapLibre reprend la configuration raster existante. Elle affiche la station choisie, les accès géolocalisés et met en évidence l’accès sélectionné. Le select HTML reste la source accessible et le serveur refuse tout accès qui n’appartient pas à la station.

La limite par fichier vient de `FOTOMETRO_PHOTO_MAX_UPLOAD_MB`. La limite de lot vient de `FOTOMETRO_PHOTO_BATCH_LIMIT`.

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

Règle provisoire : 0 photo publiée donne `not_started`, 1 à 4 donnent `in_progress`, 5 ou plus donnent `documented`.

`planned` et `complete` restent manuels et ne sont pas écrasés automatiquement.

## Consultation publique

La fiche station est la porte d’entrée principale du catalogue photographique. Elle affiche une photo principale, une galerie paginée, les filtres par catégories représentées et les filtres par accès.

La page photo conserve la navigation précédente/suivante dans la même station uniquement, en respectant l’ordre public `sort_order`, `taken_at`, `id`. Les métadonnées vides ne sont pas affichées, et les coordonnées GPS EXIF de la photo ne sont pas exposées publiquement.
