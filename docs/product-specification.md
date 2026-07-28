# Spécification produit

## Objectif

fotometro catalogue et présente des photographies des stations du métro parisien. L'application structure les lignes, les stations, leur statut de couverture photographique et une première exploration cartographique publique.

## Périmètre actuel

- Accueil transformé en explorateur MapLibre
- Affichage des stations actives avec coordonnées
- Affichage des tracés disponibles pour les lignes
- Recherche de station accent-insensible
- Sélection de ligne et de station
- Fiche publique de station
- Fiche publique de ligne
- Résumé de progression photographique
- Authentification administrateur simple
- Données de démonstration

## Hors périmètre

- Import automatique Île-de-France Mobilités
- Gestion des photographies
- Galeries réelles
- Filigranes
- Administration complète des lignes et stations
- Déploiement automatisé o2switch

## Utilisateurs

### Visiteur

Le visiteur explore la carte, filtre par ligne ou statut, recherche une station, ouvre une fiche de station ou consulte la progression d'une ligne.

### Administrateur

L'administrateur se connecte avec un compte unique et consulte le tableau de bord provisoire.

## Identité visuelle

- Fond clair légèrement crème
- Surfaces blanches
- Texte presque noir
- Couleurs des lignes stockées en base
- Formes circulaires pour les stations et pastilles
- Aucune reproduction du logo, du plan officiel, des pictogrammes ou typographies propriétaires de la RATP

## Interface carte plein ecran

L'accueil est une carte MapLibre plein ecran avec des panneaux flottants. La barre superieure regroupe le logo ou son fallback, la recherche et les actions. Les panneaux de progression, filtres, lignes, station et ligne apparaissent au-dessus de la carte sans recreer MapLibre.

La selection de ligne ouvre un schema dynamique construit depuis les donnees Laravel. Le schema est horizontal sur desktop et vertical sur mobile. Les stations sont triees par position de pivot, les terminus sont signales, les correspondances ne repetent pas la ligne active et les statuts utilisent des formes en plus des couleurs.
# Donnees reseau reelles

Le produit s'appuie desormais sur un import IDFM pour remplacer les donnees de demonstration lorsque les sources sont configurees. L'import couvre lignes, stations, correspondances, ordre, terminus, branches simples, traces GeoJSON valides et acces si les datasets d'acces sont fournis.

Les donnees editoriales de couverture photographique restent propres a fotometro et ne sont pas ecrasees par l'import.

# Catalogue photographique

Les administrateurs peuvent importer des photographies, les rattacher a une station et optionnellement a un acces, choisir une categorie, renseigner le copyright et publier la photo apres traitement.

La premiere version n'inclut pas la reconnaissance automatique par GPS, les suggestions intelligentes, les albums complexes ni les commentaires publics.
