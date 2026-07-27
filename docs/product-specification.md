# Spécification produit

## Objectif

fotometro catalogue et présente des photographies des stations du métro parisien. L'application doit permettre de structurer les stations, leurs lignes, leur statut de couverture photographique, puis d'ajouter plus tard une carte et une gestion d'images.

## Périmètre de cette étape

- Application Laravel initialisée
- Interface publique provisoire
- Connexion administrateur simple
- Inscription publique absente
- Tableau de bord administrateur provisoire
- Modèles `Line` et `Station`
- Relation plusieurs-à-plusieurs entre lignes et stations
- Seeder de démonstration
- Tests de relations

## Hors périmètre

- Carte MapLibre
- Import de données IDFM
- Upload, traitement ou stockage des photographies
- Rôles avancés
- Files d'attente spécialisées
- Redis, Docker ou services système permanents

## Utilisateurs

### Visiteur

Le visiteur consulte la page publique et voit le nom du projet, la vocation du catalogue, les lignes disponibles et le nombre de stations.

### Administrateur

L'administrateur se connecte avec un compte unique créé par seed ou commande serveur. Il consulte les indicateurs initiaux du catalogue.

## Identité visuelle provisoire

- Fond clair légèrement crème
- Surfaces blanches
- Texte presque noir
- Couleurs des lignes stockées en base
- Interface responsive
- Aucun logo, police ou élément propriétaire de la RATP
