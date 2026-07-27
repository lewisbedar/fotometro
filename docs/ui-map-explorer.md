# Interface cartographique plein ecran

## Structure

La page d'accueil est un explorateur cartographique plein ecran. Le conteneur MapLibre est fixe avec `inset: 0` et `height: 100dvh`. Les controles sont superposes sous forme de panneaux flottants: barre superieure, progression, filtres, lignes, contexte station ou ligne, puis schema de ligne.

## Gestion des panneaux

L'etat Alpine est centralise avec `activePanel`, `selectedLineId`, `selectedStationId`, `isLineDiagramOpen`, `isFiltersOpen` et `isLinesOpen`. Escape ferme la recherche, puis les menus temporaires, puis le panneau station. Le clic sur le logo appelle `resetExplorer()`.

## Synchronisation carte et schema

La selection de ligne cadre la carte sur un trace GeoJSON reel si disponible, sinon sur les stations geolocalisees de la ligne. La selection d'une station depuis la carte ou depuis le schema met a jour le panneau station, recentre la carte, ouvre la popup et fait defiler le schema vers le noeud actif.

## Schema de ligne

Le schema desktop est horizontal et defilable. Le schema mobile est vertical. Les stations sont triees par `station_line.position`; `branch` est conserve pour preparer les futures branches sans afficher de topologie complexe dans cette version.

Les terminus viennent de `station_line.is_terminus`. Les correspondances affichent les autres lignes de la station et excluent la ligne active.

## Statuts

Les statuts ne reposent pas uniquement sur la couleur: cercle vide, cercle pointille, cercle partiellement rempli, cercle plein, coche et double contour pour la selection. Une legende est presente dans le schema.

## Mobile

Sur mobile, les panneaux deviennent des feuilles basses, le schema devient vertical et les boutons secondaires sont compacts. Les espacements tiennent compte de `env(safe-area-inset-top)` et `env(safe-area-inset-bottom)`.

## Logo

La barre superieure affiche `public/images/logo_fotometro.png` si le fichier existe. Sinon, un fallback texte conserve la structure sans image cassee.

## Donnees futures

Les entrees, sorties et photographies ne sont pas inventees. Les panneaux affichent une mention "bientot disponible" tant que ces donnees ne sont pas disponibles. L'import IDFM, les acces reels et les branches complexes restent hors perimetre.
# Donnees importees

L'explorateur consomme `/api/map`. Les lignes exposent leurs stations ordonnees par `station_line.position`, avec `branch`, `is_terminus`, statuts de couverture et correspondances sans repetition de la ligne active.

Le logo horizontal attendu est `public/images/logo_fotometro.png`. Si le fichier est absent, la barre superieure affiche le texte `fotometro` sans image cassee.

Les noms du schema de ligne sont places sous l'axe et sous les noeuds; les correspondances restent sous le nom de station. Les futures branches IDFM sont conservees dans les donnees via `branch`, mais la representation complexe des embranchements reste une evolution separee.
# Schema et GTFS

Le schema desktop lit `lines[].topology.layout` fourni par `/api/map`. Cette structure est mise a jour par l'import GTFS et non deduite du CSV `arrets-lignes`.

Le backend calcule les coordonnees SVG: dimensions, viewBox, segments, noeuds, labels, cartouches de terminus, correspondances et branches. Alpine rend ces coordonnees sans improviser la geometrie. Les lignes branchees disposent de `topology.branches`; les boucles utilisent les types `loop` ou `partial-loop`. Une station commune a plusieurs sequences reste une seule station publique, mais toutes ses occurrences visuelles peuvent etre surlignees.

Sur mobile, une liste verticale separee reste utilisee pour ne pas reduire le SVG desktop complexe. Les choix metier detailles sont documentes dans `docs/line-topology.md`.
