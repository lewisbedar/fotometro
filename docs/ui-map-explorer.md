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
