# Traitement des photos

Le traitement fonctionne sans worker permanent afin de rester compatible avec o2switch.

## Flux

1. L'original est enregistre sur le disque prive.
2. La ligne `photos` est creee avec `processing_status = pending`.
3. `fotometro:process-photos` lit les photos en attente.
4. L'image est ouverte avec GD.
5. L'orientation EXIF est appliquee lorsque disponible.
6. Une version web et une miniature sont generees sans agrandissement inutile.
7. La photo passe en `ready`.
8. Si `publish_when_ready = true`, elle est publiee immediatement avec `published_at = now()`.
9. En cas d'erreur, les fichiers partiels sont supprimes et la photo passe en `failed` sans publication automatique.

## Commande

```bash
php artisan fotometro:process-photos --limit=10
php artisan fotometro:process-photos --photo=123
php artisan fotometro:process-photos --retry-failed
php artisan fotometro:process-photos --photo=123 --force
```

## Cron o2switch

Exemple a creer manuellement dans le panneau o2switch:

```cron
*/5 * * * * cd /home/COMPTE/apps/fotometro && php artisan fotometro:process-photos --limit=10
```

Sur mutualise, conserver un `--limit` prudent pour respecter le temps d'execution et la memoire disponibles. Verifier que les dossiers `storage/app/private/photos` et `storage/app/public/photos` sont inscriptibles par PHP.

## Configuration

```dotenv
FOTOMETRO_PHOTO_DISK=local
FOTOMETRO_PHOTO_MAX_UPLOAD_MB=40
FOTOMETRO_PHOTO_BATCH_LIMIT=20
FOTOMETRO_PHOTO_MANUAL_PROCESS_LIMIT=5
FOTOMETRO_PHOTO_PROCESS_SYNCHRONOUSLY=false
FOTOMETRO_PHOTO_WEB_MAX_WIDTH=2200
FOTOMETRO_PHOTO_THUMBNAIL_WIDTH=600
FOTOMETRO_PHOTO_WEB_QUALITY=85
FOTOMETRO_PHOTO_THUMBNAIL_QUALITY=82
```

Le filigrane est prepare mais desactive par defaut. Aucun filigrane n'est applique aux originaux.

`FOTOMETRO_PHOTO_MANUAL_PROCESS_LIMIT` limite le nombre de photos que l'interface admin peut traiter immediatement dans une seule requete. Les lots plus grands restent pris en charge par la commande cron.
