# Import reseau IDFM

fotometro peut importer le reseau metro depuis des jeux de donnees publics d'Ile-de-France Mobilites, sans service permanent ni worker. L'import s'execute par commande Artisan et reste compatible avec un hebergement mutualise o2switch.

## Sources publiques

- Arrets et lignes: `https://data.iledefrance-mobilites.fr/explore/dataset/arrets-lignes/`
- Traces des lignes: `https://data.iledefrance-mobilites.fr/explore/dataset/traces-des-lignes-de-transport-en-commun-idfm/`
- Offre GTFS IDFM: `https://prim.iledefrance-mobilites.fr/fr/jeux-de-donnees/offre-horaires-tc-gtfs-idfm`
- Referentiel des arrets: acces: `https://www.data.gouv.fr/fr/datasets/referentiel-des-arrets-acces/`
- Referentiel arret TC IDF: `https://data.iledefrance-mobilites.fr/explore/dataset/referentiel-arret-tc-idf/custom/`

Les URLs configurables peuvent pointer vers l'API publique IDFM, un fichier local `file://...` ou un fichier de stockage `storage://...` pour les tests et imports controles.

Pour `arrets-lignes`, fotometro transforme automatiquement l'URL `/records` configuree en export complet:

```text
/api/explore/v2.1/catalog/datasets/arrets-lignes/exports/csv?limit=-1
```

Le CSV a ete retenu pour lire le fichier progressivement depuis `storage/app/idfm`, detecter le separateur et eviter la limite `offset + limit < 10000` de l'endpoint `/records`. La pagination `/records` reste disponible pour les petits jeux filtres, avec une garde explicite avant la limite des 10 000 enregistrements.

## Configuration

```dotenv
FOTOMETRO_IDFM_ARRETS_LIGNES_URL=https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/arrets-lignes/records?limit=100
FOTOMETRO_IDFM_TRACES_URL=https://data.iledefrance-mobilites.fr/api/explore/v2.1/catalog/datasets/traces-des-lignes-de-transport-en-commun-idfm/records?limit=100
FOTOMETRO_IDFM_ACCESSES_URL=
FOTOMETRO_IDFM_ACCESS_RELATIONS_URL=
FOTOMETRO_IDFM_TIMEOUT=30
FOTOMETRO_IDFM_TEMP_DIR=storage/app/idfm
FOTOMETRO_IDFM_IMPORT_ACCESSES=true
FOTOMETRO_IDFM_DEACTIVATE_ABSENT=true
```

## Commande

```bash
php artisan fotometro:import-network
php artisan fotometro:import-network --dry-run
php artisan fotometro:import-network --only=lines
php artisan fotometro:import-network --only=stations
php artisan fotometro:import-network --only=accesses
php artisan fotometro:import-network --skip-traces
php artisan fotometro:import-network --skip-accesses
php artisan fotometro:import-network --force
```

`--dry-run` execute les operations dans une transaction puis annule les changements. `--force` transforme les erreurs en rapport lisible au lieu d'interrompre brutalement l'exploitation.

## Regles de securite des donnees

- Les lignes, stations et acces importes utilisent `external_id`.
- Les enregistrements absents d'une source ne sont pas supprimes: ils sont marques `is_active=false`.
- Les champs editoriaux ne sont pas ecrases par l'import: descriptions, statut de couverture, photos et enrichissements humains restent sous controle de l'application.
- `source_payload` est conserve en base pour audit technique mais n'est jamais expose par `/api/map`.
- Le cache public `fotometro.public-map.v1` est invalide apres un import reussi.

## Schema

`lines` ajoute `external_id`, `is_active`, `source`, `source_payload` et `source_updated_at`.

`stations` conserve ses champs editoriaux et ajoute `source`, `source_payload` et `source_updated_at`.

`station_accesses` stocke les acces et sorties: identifiant externe, nom, reference, coordonnees, type, rue, description, accessibilite PMR, statut actif et metadonnees source.

`access_station` relie un acces a une ou plusieurs stations.

## Traces

L'import accepte uniquement les geometries GeoJSON `LineString` et `MultiLineString`. Une trace invalide ou vide est ignoree et ne remplace jamais une trace valide deja presente.

## Filtrage metro

Le premier filtrage applicatif ne repose pas sur le nom commercial. Les importeurs retiennent les lignes dont les champs source de mode ou type (`mode`, `route_type`, `transportmode`) indiquent le metro, puis ignorent bus, tramway, RER et Transilien comme lignes principales. Les correspondances non-metro pourront etre traitees dans une evolution separee.

## Identifiants canoniques

Les identifiants de ligne IDFM sont normalises avant comparaison et stockage. La forme canonique supprime le prefixe `IDFM:`, retire espaces et guillemets, met la valeur en majuscules, et transforme une valeur numerique a cinq chiffres en code `Cxxxxx`. Ainsi `IDFM:C01371`, `C01371` et `"01371"` deviennent tous `C01371`.

## Diagnostic local

La route temporaire `/debug/database` est disponible uniquement en environnement local. Elle retourne le driver, le nom de base, le nombre de lignes, le nombre de stations et le nombre de relations `station_line`. Elle sert a verifier que le serveur web lit bien la meme base MySQL que la CLI et doit etre supprimee apres diagnostic.

## Donnees de demonstration

Le seeder de demonstration reste utile en developpement et dans les tests. Sur une base non-test contenant deja des donnees importees avec `external_id`, il ne reinsere pas les donnees fictives afin de ne pas polluer le reseau reel.

## o2switch

L'import peut etre lance manuellement en SSH ou par tache cron ponctuelle:

```bash
/usr/local/bin/php /home/USER/fotometro/artisan fotometro:import-network --dry-run
```

Commencer par `--dry-run`, verifier le rapport, puis lancer l'import reel. Aucun Redis, Docker, supervisor ou processus resident n'est requis.
