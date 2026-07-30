<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * "Entrée"/"Sortie" were buried under "Extérieur", and the beta notice
     * promises a dedicated "Entrées et sorties" category — split them into
     * their own root. Also adds "Détails techniques", the other category
     * mentioned in that notice which never actually existed.
     */
    public function up(): void
    {
        $maxRootOrder = (int) DB::table('photo_categories')->whereNull('parent_id')->max('sort_order');

        $entreesSortiesId = DB::table('photo_categories')->where('slug', 'entrees-et-sorties')->value('id');

        if (! $entreesSortiesId) {
            $entreesSortiesId = DB::table('photo_categories')->insertGetId([
                'name' => 'Entrées et sorties',
                'slug' => 'entrees-et-sorties',
                'parent_id' => null,
                'sort_order' => $maxRootOrder + 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('photo_categories')
            ->where('slug', 'exterieur-entree')
            ->update(['parent_id' => $entreesSortiesId, 'slug' => 'entrees-et-sorties-entree', 'sort_order' => 0, 'updated_at' => now()]);

        DB::table('photo_categories')
            ->where('slug', 'exterieur-sortie')
            ->update(['parent_id' => $entreesSortiesId, 'slug' => 'entrees-et-sorties-sortie', 'sort_order' => 1, 'updated_at' => now()]);

        $detailsTechniquesId = DB::table('photo_categories')->where('slug', 'details-techniques')->value('id');

        if (! $detailsTechniquesId) {
            $detailsTechniquesId = DB::table('photo_categories')->insertGetId([
                'name' => 'Détails techniques',
                'slug' => 'details-techniques',
                'parent_id' => null,
                'sort_order' => $maxRootOrder + 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $children = [
            ['slug' => 'details-techniques-cablage-electricite', 'name' => 'Câblage et électricité'],
            ['slug' => 'details-techniques-ventilation', 'name' => 'Ventilation'],
            ['slug' => 'details-techniques-securite', 'name' => 'Équipement de sécurité'],
            ['slug' => 'details-techniques-sonorisation', 'name' => 'Sonorisation et annonces'],
            ['slug' => 'details-techniques-horloge', 'name' => 'Horloge et affichage horaire'],
        ];

        foreach ($children as $order => $child) {
            DB::table('photo_categories')->updateOrInsert(
                ['slug' => $child['slug']],
                [
                    'name' => $child['name'],
                    'parent_id' => $detailsTechniquesId,
                    'sort_order' => $order,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        $exterieurId = DB::table('photo_categories')->where('slug', 'exterieur')->value('id');

        DB::table('photo_categories')
            ->where('slug', 'entrees-et-sorties-entree')
            ->update(['parent_id' => $exterieurId, 'slug' => 'exterieur-entree', 'updated_at' => now()]);

        DB::table('photo_categories')
            ->where('slug', 'entrees-et-sorties-sortie')
            ->update(['parent_id' => $exterieurId, 'slug' => 'exterieur-sortie', 'updated_at' => now()]);

        DB::table('photo_categories')->where('slug', 'entrees-et-sorties')->delete();
        DB::table('photo_categories')->where('slug', 'like', 'details-techniques%')->delete();
    }
};
