# Topologie des lignes

Cette branche ajoute une representation topologique dediee aux schemas de ligne. `station_line` reste la relation generale Station-Ligne, tandis que `line_station_sequences` porte les occurrences ordonnees issues du GTFS.

## Orientation canonique

Les sequences sont affichees selon une convention stable:

- ouest vers est pour un axe principalement horizontal;
- nord vers sud pour un axe principalement vertical;
- diagonale dans le sens le plus naturel d'apres la geometrie;
- correction manuelle lorsque l'usage metro est plus fiable que la geometrie.

La configuration est centralisee dans `config/fotometro.php`, section `line_orientation`. Les vues Blade et le JavaScript ne contiennent pas les terminus metier.

Orientations principales retenues:

- Ligne 1: La Defense -> Chateau de Vincennes
- Ligne 2: Porte Dauphine -> Nation
- Ligne 3: Pont de Levallois - Becon -> Gallieni
- Ligne 4: Porte de Clignancourt -> Bagneux - Lucie Aubrac
- Ligne 5: Bobigny - Pablo Picasso -> Place d'Italie
- Ligne 6: Charles de Gaulle - Etoile -> Nation
- Ligne 7: La Courneuve - 8 Mai 1945 -> Mairie d'Ivry / Villejuif - Louis Aragon
- Ligne 8: Balard -> Pointe du Lac
- Ligne 9: Pont de Sevres -> Mairie de Montreuil
- Ligne 11: Chatelet -> Rosny-Bois-Perrier
- Ligne 12: Mairie d'Aubervilliers -> Mairie d'Issy
- Ligne 13: branches nord -> Chatillon - Montrouge
- Ligne 14: Saint-Denis - Pleyel -> Aeroport d'Orly

## Import GTFS

`php artisan fotometro:import-network --only=gtfs` lit les patterns GTFS selectionnes, applique l'orientation, puis remplit `line_station_sequences`.

La commande accepte aussi:

```bash
php artisan fotometro:import-network --only=topology
```

Cet alias execute le meme import GTFS. Le rapport affiche les sequences topologiques creees, troncs communs detectes, branches detectees, boucles detectees, orientations inversees, orientations manuelles utilisees et regles non resolues.

## Table line_station_sequences

Champs principaux:

- `line_id`, `station_id`;
- `sequence_key`, `branch_key`, `direction_key`;
- `position`;
- `is_terminus`;
- `is_branch_start`, `is_branch_end`;
- `is_loop_entry`, `is_loop_exit`;
- `is_shared_trunk`;
- `source`, `gtfs_pattern`.

Une meme station peut apparaitre dans plusieurs sequences et a plusieurs positions. C'est necessaire pour les branches, boucles partielles et stations communes.

## API

`/api/map` expose maintenant `lines[].topology`:

```json
{
  "type": "branched",
  "orientation": {
    "start": {},
    "ends": []
  },
  "trunk": [],
  "branches": [
    {
      "key": "branch-a",
      "stations": []
    }
  ],
  "main": [],
  "loop": []
}
```

Le frontend consomme cette structure. Il ne recalcule pas les branches depuis les patterns GTFS bruts.

`lines[].topology.layout` contient le placement SVG explicite:

- dimensions `width` et `height`;
- `view_box` calcule depuis le bounding box des segments, stations, labels et correspondances;
- `segments` avec coordonnees de debut et de fin;
- `stations` avec `x`, `y`, position du label, ancrage, rotation, terminus et pastilles de correspondance;
- `branches` et `terminus` pour le diagnostic.

Alpine ne determine pas la geometrie desktop. Il rend uniquement les coordonnees calculees par `App\Services\Map\LineDiagramLayout`.

Les terminus suivent la meme inclinaison et la meme logique de label que les autres stations. Ils sont distingues par un cartouche bleu fotometro, configure via `config/line_diagrams.php` et expose dans le SVG comme variable CSS `--fotometro-terminus-blue`.

## Types

- `simple`: sequence unique.
- `branched`: plusieurs sequences longues avec stations communes.
- `loop`: boucle metier explicite, actuellement utilisee pour la ligne 7bis.
- `partial-loop`: boucle ou variante partielle, actuellement utilisee pour la ligne 10.

## Lignes particulieres

Ligne 7: layout dedie avec tronc horizontal depuis La Courneuve - 8 Mai 1945, point de bifurcation unique, deux diagonales a 45 degres et deux branches sud horizontales vers Mairie d'Ivry et Villejuif - Louis Aragon. Le viewBox est elargi par bounding box pour ne pas couper les terminus sud.

Ligne 13: layout dedie avec deux branches nord distinctes, diagonales de convergence, puis tronc commun horizontal vers Chatillon - Montrouge.

Ligne 7bis: type `loop`, avec segment principal horizontal et boucle fermee de reference. Les stations de boucle sont placees par table de coordonnees avec resolution `external_id`, puis fallback nom.

Ligne 10: type `partial-loop`, avec axe principal vers Gare d'Austerlitz et boucle ouest dediee. Les stations Boulogne Pont de Saint-Cloud, Boulogne Jean Jaures, Porte d'Auteuil, Michel-Ange - Auteuil et Michel-Ange - Molitor sont placees par table de reference. Les trois patterns GTFS ne sont pas affiches comme trois branches paralleles.

## Diagnostic local

La route `/debug/line-diagrams` affiche les 16 lignes les unes sous les autres avec code, type, largeur, terminus, branches et SVG desktop. Elle retourne 404 hors environnement local.

## Limites connues

- Le rendu SVG/CSS suit une grammaire rigide proche d'un plan de ligne: axes horizontaux, branches separees, angles 45/90 degres. Il ne cherche pas encore une fidelite exhaustive a toutes les subtilites d'exploitation.
- Les stations partagees peuvent apparaitre dans plusieurs sequences; la selection met en evidence toutes les occurrences visuelles d'une meme station publique.
- Les branches courtes d'exploitation restent filtrees par inclusion dans les patterns plus longs.
- Les corrections manuelles utilisent les noms comme fallback stable; l'usage futur d'identifiants de station officiels est preferable lorsque toute la chaine IDFM les fournit sans ambiguite.
