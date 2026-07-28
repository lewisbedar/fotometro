<?php

namespace Database\Seeders;

use App\Models\PhotoCategory;
use Illuminate\Database\Seeder;

class PhotoCategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = [
            ['slug' => 'exterieur', 'name' => 'Extérieur', 'children' => [
                ['slug' => 'exterieur-vue-generale', 'name' => 'Vue générale'],
                ['slug' => 'exterieur-entree', 'name' => 'Entrée'],
                ['slug' => 'exterieur-sortie', 'name' => 'Sortie'],
                ['slug' => 'exterieur-environnement-urbain', 'name' => 'Environnement urbain'],
                ['slug' => 'exterieur-facade-ou-edicule', 'name' => 'Façade ou édicule'],
                ['slug' => 'exterieur-totem-ou-enseigne', 'name' => 'Totem ou enseigne'],
                ['slug' => 'exterieur-acces-pmr', 'name' => 'Accès PMR'],
            ]],
            ['slug' => 'interieur', 'name' => 'Intérieur', 'children' => [
                ['slug' => 'interieur-hall', 'name' => 'Hall'],
                ['slug' => 'interieur-salle-des-billets', 'name' => 'Salle des billets'],
                ['slug' => 'interieur-escalier', 'name' => 'Escalier'],
                ['slug' => 'interieur-escalator', 'name' => 'Escalator'],
                ['slug' => 'interieur-ascenseur', 'name' => 'Ascenseur'],
                ['slug' => 'interieur-couloir', 'name' => 'Couloir'],
                ['slug' => 'interieur-correspondance', 'name' => 'Correspondance'],
                ['slug' => 'interieur-quai', 'name' => 'Quai'],
                ['slug' => 'interieur-voie', 'name' => 'Voie'],
                ['slug' => 'interieur-tunnel', 'name' => 'Tunnel'],
            ]],
            ['slug' => 'signaletique', 'name' => 'Signalétique', 'children' => [
                ['slug' => 'signaletique-nom-de-station', 'name' => 'Nom de station'],
                ['slug' => 'signaletique-plan-de-ligne', 'name' => 'Plan de ligne'],
                ['slug' => 'signaletique-plan-de-quartier', 'name' => 'Plan de quartier'],
                ['slug' => 'signaletique-direction', 'name' => 'Direction'],
                ['slug' => 'signaletique-sortie', 'name' => 'Sortie'],
                ['slug' => 'signaletique-panneau-historique', 'name' => 'Panneau historique'],
                ['slug' => 'signaletique-information-voyageurs', 'name' => 'Information voyageurs'],
            ]],
            ['slug' => 'architecture-et-decoration', 'name' => 'Architecture et décoration', 'children' => [
                ['slug' => 'architecture-et-decoration-carrelage', 'name' => 'Carrelage'],
                ['slug' => 'architecture-et-decoration-mobilier', 'name' => 'Mobilier'],
                ['slug' => 'architecture-et-decoration-eclairage', 'name' => 'Éclairage'],
                ['slug' => 'architecture-et-decoration-oeuvre-d-art', 'name' => 'Œuvre d’art'],
                ['slug' => 'architecture-et-decoration-decoration', 'name' => 'Décoration'],
                ['slug' => 'architecture-et-decoration-structure', 'name' => 'Structure'],
                ['slug' => 'architecture-et-decoration-detail-architectural', 'name' => 'Détail architectural'],
            ]],
            ['slug' => 'vie-et-evolution', 'name' => 'Vie et évolution', 'children' => [
                ['slug' => 'vie-et-evolution-travaux', 'name' => 'Travaux'],
                ['slug' => 'vie-et-evolution-station-fermee', 'name' => 'Station fermée'],
                ['slug' => 'vie-et-evolution-transformation', 'name' => 'Transformation'],
                ['slug' => 'vie-et-evolution-publicite', 'name' => 'Publicité'],
                ['slug' => 'vie-et-evolution-activite', 'name' => 'Activité'],
                ['slug' => 'vie-et-evolution-archive', 'name' => 'Archive'],
            ]],
        ];

        $order = 0;

        foreach ($tree as $parentData) {
            $parent = PhotoCategory::query()->updateOrCreate(
                ['slug' => $parentData['slug']],
                ['name' => $parentData['name'], 'sort_order' => $order++, 'is_active' => true]
            );

            foreach ($parentData['children'] as $childOrder => $childData) {
                PhotoCategory::query()->updateOrCreate(
                    ['slug' => $childData['slug']],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childData['name'],
                        'sort_order' => $childOrder,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
