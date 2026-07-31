# Déploiement o2switch

Ce projet est prévu pour un hébergement mutualisé o2switch classique : PHP, Composer, MySQL, Apache et cron. La production ne nécessite pas Docker, Redis, serveur Node.js permanent ou accès administrateur.

## 1. Prérequis côté cPanel

- **PHP 8.3** via cPanel > « Sélecteur de version de PHP », avec au minimum les extensions `gd`, `pdo_mysql`, `mbstring`, `xml`, `ctype`, `tokenizer`, `bcmath`, `fileinfo`, `openssl`, `curl`, `zip`. Vérifier `gd` en particulier (utilisé pour le traitement des photos) : elle n'est pas toujours active par défaut.
- **Réglages PHP (MultiPHP INI Editor)**, à augmenter par rapport aux valeurs par défaut o2switch, sans quoi les imports de photos échouent silencieusement :
  - `upload_max_filesize` → `40M` (aligné sur `FOTOMETRO_PHOTO_MAX_UPLOAD_MB`)
  - `post_max_size` → `100M` (une importation groupée envoie plusieurs fichiers dans une seule requête)
  - `memory_limit` → `256M`
  - `max_execution_time` → `120`
- **Base MySQL** : créer une base et un utilisateur dédiés via cPanel > « Bases de données MySQL » (le nom sera préfixé par l'identifiant cPanel, ex. `moncompte_fotometro`).
- **SSL** : activer AutoSSL (Let's Encrypt gratuit) sur le domaine dès sa création.

## 2. Préparer localement

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
```

`public/build/` (généré par Vite) **n'est pas suivi par Git** (`.gitignore` l'exclut, comme `node_modules/`). Après un `git clone`/`git pull` sur le serveur, il faut donc systématiquement envoyer ce dossier séparément par SFTP — il n'apparaît jamais tout seul sur le serveur. Déployez le code, `vendor/` si Composer n'est pas exécuté sur le serveur, et `public/build/`.

`public/vendor/maplibre-gl/` (les fichiers `maplibre-gl-worker.mjs` et `maplibre-gl-shared.mjs`, copiés par le plugin Vite défini dans `vite.config.js`) est **également exclu de Git** (`.gitignore` là aussi) et doit être envoyé par SFTP au même titre que `public/build/`. Sans ce dossier, le worker MapLibre répond en 404 et la carte ne s'initialise pas du tout : ni fond de carte, ni tracés de lignes, ni stations n'apparaissent, avec une erreur "Erreur MapLibre" côté client.

`public/.htaccess` (suivi par Git, donc déployé automatiquement par `git pull`) déclare `AddType application/javascript .mjs`. Sans cette déclaration, Apache sert les fichiers `.mjs` sans `Content-Type`, et le navigateur refuse d'exécuter le worker MapLibre comme module script (`Failed to load module script: ... non-JavaScript MIME type`) même si le fichier existe et répond en 200 — voir le dépannage en §11.

## 3. Racine du document

Le domaine doit pointer vers le dossier `public/` de Laravel, jamais vers la racine du projet. Deux façons de faire sur cPanel :

1. **Recommandé** : envoyer le projet hors `public_html` (ex. `~/fotometro-app/`), puis dans cPanel > « Domaines », éditer le domaine/sous-domaine de la bêta et changer son **dossier racine** pour `fotometro-app/public`.
2. Si l'offre ne permet pas de changer la racine d'un sous-domaine existant : créer le sous-domaine avec sa racine par défaut, la supprimer, puis créer un lien symbolique vers `public/` :
   ```bash
   rm -rf ~/public_html/beta
   ln -s ~/fotometro-app/public ~/public_html/beta
   ```

## 4. Configuration `.env`

Copier `.env.example` vers `.env`, générer la clé, puis ajuster au minimum :

```dotenv
APP_NAME=fotometro
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://votre-sous-domaine.fr

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=moncompte_fotometro
DB_USERNAME=moncompte_fotometro
DB_PASSWORD=mot_de_passe

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

FOTOMETRO_MAP_BASEMAP_DRIVER=raster
FOTOMETRO_MAP_RASTER_URL=https://fournisseur.example/{z}/{x}/{y}.png
FOTOMETRO_MAP_RASTER_TILE_SIZE=256
FOTOMETRO_MAP_STYLE_URL=
FOTOMETRO_MAP_ATTRIBUTION="© fournisseur du fond de carte"
FOTOMETRO_MAP_CACHE_TTL=300
FOTOMETRO_MAP_MAX_ZOOM=19

FOTOMETRO_PHOTO_PROCESS_SYNCHRONOUSLY=true

ADMIN_NAME="Administrateur fotometro"
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=mot-de-passe-fort
```

Ne versionnez jamais le `.env` de production.

`SESSION_DRIVER`, `CACHE_STORE` et `QUEUE_CONNECTION` restent sur `database` (comme dans `.env.example`) : aucun Redis n'est nécessaire, tout est géré par les tables MySQL créées à la migration (`sessions`, `cache`, `jobs`).

`FOTOMETRO_PHOTO_PROCESS_SYNCHRONOUSLY=true` est le réglage recommandé pour ce type d'hébergement : par défaut (`false`), le traitement des photos (redimensionnement, miniatures) passe par la file d'attente et nécessite un worker permanent (`php artisan queue:work`), généralement impossible à maintenir sur un mutualisé. En le passant à `true`, le traitement se fait directement pendant la requête d'import, sans worker (voir §8 pour l'alternative avec cron si le volume grossit).

Le serveur `tile.openstreetmap.org` convient au développement et aux essais, mais ne doit pas être retenu par défaut pour une mise en production publique sans vérifier sa politique d'utilisation. Choisissez un fournisseur de tuiles raster compatible avec le volume et l'usage réel du site.

## 4bis. Configuration email

Par défaut (`.env.example`), `MAIL_MAILER=log` : aucune notification n'est réellement envoyée, elle est seulement écrite dans `storage/logs/laravel.log`. Pour que les emails d'inscription, d'approbation de compte et de publication de photo partent réellement, configurer un mailer SMTP en production, par exemple avec une adresse o2switch :

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=votre-domaine.fr
MAIL_PORT=587
MAIL_USERNAME=contact@votre-domaine.fr
MAIL_PASSWORD=mot-de-passe-boite-mail
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=contact@votre-domaine.fr
MAIL_FROM_NAME="${APP_NAME}"
```

Les identifiants correspondent à un compte email créé via cPanel > « Comptes de messagerie ». Vérifier l'envoi réel après configuration : approuver un compte de test depuis `/admin/users` et confirmer la réception de l'email (pas seulement sa présence dans les logs).

## 5. Installation sur le serveur

Dans cet ordre précis — l'import du réseau doit précéder le seed, sinon `LineStationSeeder` insère des lignes/stations de démonstration factices dans une base encore vide :

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan fotometro:import-network --dry-run
php artisan fotometro:import-network
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- Toujours lancer `--dry-run` avant la synchronisation réelle, vérifier le rapport, puis relancer sans option si les données sont cohérentes.
- `fotometro:import-network` importe les lignes, stations et accès réels à partir de l'instantané IDFM commité dans `resources/idfm-data/` (déployé automatiquement avec le code via `git pull`, pas besoin d'accès réseau à Île-de-France Mobilités ni d'étape supplémentaire) — c'est donc rapide. Ce réseau évolue peu : pour rafraîchir l'instantané depuis les données live d'IDFM, lancer `php artisan fotometro:vendor-idfm-data` **en local**, vérifier le diff, committer, puis redéployer. Seul l'import GTFS (`--only=gtfs`, pour l'ordre des stations sur une ligne) reste téléchargé en direct depuis IDFM (l'archive fait 100+ Mo, elle ne tient pas dans Git).
- `db:seed` ne (re)crée que le compte administrateur une fois des lignes/stations réelles (`external_id` renseigné) présentes — l'ordre ci-dessus le garantit.
- `storage:link` crée le lien symbolique public nécessaire pour servir les photos web/miniatures.
- Si `APP_KEY` n'est pas déjà définie : `php artisan key:generate --force`.

## 6. Permissions

```bash
chmod -R 775 storage bootstrap/cache
```

Sur o2switch, PHP tourne généralement sous votre propre utilisateur cPanel : `775` suffit dans la quasi-totalité des cas (pas besoin de `777`).

## 7. Cron

Aucun worker permanent n'est requis avec `FOTOMETRO_PHOTO_PROCESS_SYNCHRONOUSLY=true`. En revanche, les emails (inscription, approbation/refus de compte, photo publiée/refusée) sont envoyés via des notifications mises en file d'attente (`ShouldQueue`) plutôt que pendant la requête HTTP — sans quoi un SMTP lent ou injoignable bloque la requête jusqu'au timeout du serveur (504 Gateway Time-out), sans rien écrire dans `storage/logs/laravel.log` puisque le blocage a lieu avant qu'une exception ne soit levée. Il faut donc un cron qui vide périodiquement cette file, sans worker permanent :

```cron
* * * * * cd /home/USER/fotometro-app && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/USER/fotometro-app && php artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

`--stop-when-empty` fait sortir la commande dès que la file est vide (ou après `--max-time` secondes) : elle ne laisse donc jamais de processus PHP durable, contrairement à `queue:work` seul — compatible avec un mutualisé.

## 8. Traitement asynchrone des photos (optionnel, plus tard)

Si le volume de photos grossit et que le traitement synchrone ralentit trop les imports, repasser `FOTOMETRO_PHOTO_PROCESS_SYNCHRONOUSLY=false` et ajouter :

```cron
*/5 * * * * cd /home/USER/fotometro-app && php artisan fotometro:process-photos --limit=10 >> /dev/null 2>&1
```

Cette commande traite les photos en attente sans nécessiter de worker permanent, contrairement à `queue:work`.

## 9. Vérifications post-déploiement

- Le site répond en HTTPS avec un cadenas valide.
- Le favicon fotométro s'affiche dans l'onglet du navigateur.
- La popup bêta s'affiche à la première visite de la page d'accueil, et reste fermée après un rechargement.
- La connexion admin (`/login`) fonctionne avec les identifiants `ADMIN_*` définis.
- Un import de photo depuis l'admin aboutit à une photo visible publiquement.
- `php artisan about` (en SSH) confirme l'environnement `production` et les bons drivers.
- `/debug/database` retourne 404 (cette route de diagnostic est réservée à l'environnement local).
- Le worker MapLibre répond en 200 avec le bon type MIME :

  ```bash
  curl -I https://votre-sous-domaine.fr/vendor/maplibre-gl/maplibre-gl-worker.mjs
  ```

  doit contenir `HTTP/... 200` et `Content-Type: application/javascript`. Un 404 signifie que `public/vendor/maplibre-gl/` n'a pas été envoyé (§2) ; un `Content-Type` vide ou `text/html` signifie que la déclaration MIME `.mjs` est absente du `.htaccess` servi (§11).
- La carte affiche bien le fond raster, les tracés de lignes et les points de station (pas seulement le fond).
- `curl https://votre-sous-domaine.fr/sitemap.xml` retourne du XML avec des URLs sur le bon domaine (pas `http://localhost`) — sinon vérifier `APP_URL` dans `.env`.
- `curl https://votre-sous-domaine.fr/robots.txt` référence bien ce même sitemap.

## 10. Mise à jour

```bash
php artisan down
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Si des fichiers front (CSS/JS/Blade) ont changé, recompiler les assets **en local** (`npm run build`) et envoyer `public/build/` et `public/vendor/maplibre-gl/` par SFTP avant ces commandes — voir §2.

### Points de vigilance pour une installation déjà en place

- **`.env` avec d'anciennes URLs IDFM en direct** : si votre `.env` de production contient des `FOTOMETRO_IDFM_*_URL` explicites (hérités d'un `.env.example` copié avant que ces variables ne deviennent optionnelles), ils écrasent le nouveau défaut qui lit l'instantané commité dans `resources/idfm-data/` — l'import continuera donc à interroger l'API IDFM en direct au lieu du fichier local, plus lent et dépendant du réseau. Commentez-les (sauf `FOTOMETRO_IDFM_GTFS_URL`, qui reste en direct volontairement) pour bénéficier de l'instantané. Voir `docs/idfm-import.md`.
- **`public/robots.txt` statique résiduel** : ce fichier a été retiré du dépôt et remplacé par une route dynamique (`/robots.txt`, qui référence `/sitemap.xml`). Un `git pull` propre le supprime automatiquement s'il était suivi par Git ; si un `robots.txt` a été déposé manuellement par SFTP à un moment donné (donc non suivi par Git), il restera sur le serveur et **masquera silencieusement la route dynamique** (Apache sert un fichier existant avant de passer la main à Laravel — voir `public/.htaccess`). Vérifier après mise à jour : `curl https://votre-sous-domaine.fr/robots.txt` doit afficher la ligne `Sitemap: https://votre-sous-domaine.fr/sitemap.xml`.
- **`resources/idfm-data/*.csv`** : ce nouveau dossier est suivi par Git (contrairement à `public/build/`), donc `git pull` le déploie automatiquement sans étape SFTP supplémentaire.
- **Emails réellement envoyés** : si `MAIL_MAILER` n'a jamais été configuré au-delà de la valeur par défaut (`log`), les notifications (inscription en attente, compte approuvé/refusé, photo publiée/refusée) s'écrivent dans les logs Laravel au lieu de partir en email. Voir §4bis.

## 11. Dépannage MapLibre

Si le fond de carte raster s'affiche mais qu'aucune station ni aucun tracé de ligne n'apparaît (aucune erreur visible à l'œil nu, mais la console affiche une erreur), le worker MapLibre est en cause. Diagnostic dans l'ordre :

1. **Le fichier existe-t-il ?** Vérifier que `public/vendor/maplibre-gl/maplibre-gl-worker.mjs` a bien été envoyé sur le serveur (§2) — ce dossier n'est jamais créé par `git pull` seul.
2. **Répond-il en 404 ?** `curl -I https://votre-sous-domaine.fr/vendor/maplibre-gl/maplibre-gl-worker.mjs`. Un 404 confirme le point 1.
3. **Quel est son `Content-Type` ?** Si la réponse est un 200 mais sans `Content-Type` (ou `text/html`), Apache/o2switch ne connaît pas l'extension `.mjs`. Le navigateur refuse alors d'exécuter le fichier comme module script avec une erreur du type :
   ```
   Failed to load module script: The server responded with a non-JavaScript MIME type of "".
   ```
   Ce cas est piégeux : le fichier se charge (200), MapLibre déclenche bien son événement de chargement de carte, mais les sources GeoJSON (stations, tracés) ne sont jamais traitées puisque le worker qui les parse n'a pas pu s'exécuter.
4. **Correctif** : `public/.htaccess` déclare `AddType application/javascript .mjs` (voir §2). Si ce fichier `.htaccess` n'est pas pris en compte par le serveur (`AllowOverride` désactivé, configuration Apache non standard), contacter le support o2switch pour faire ajouter le type MIME au niveau du vhost.
5. Après correction, forcer un rechargement sans cache (Ctrl+F5) avant de re-tester : les navigateurs mettent en cache l'échec de chargement du module.

## Stockage

Les originaux sont stockés hors webroot dans `storage/app/private/photos/originals`. Les versions web et miniatures sont stockées sous `storage/app/public/photos` et servies via le lien public Laravel (`storage:link`, §5).
