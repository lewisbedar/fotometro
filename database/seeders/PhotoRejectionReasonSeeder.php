<?php

namespace Database\Seeders;

use App\Models\PhotoRejectionReason;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PhotoRejectionReasonSeeder extends Seeder
{
    public function run(): void
    {
        $reasons = [
            'Photo floue ou de mauvaise qualité',
            'Station ou accès incorrect',
            'Doublon d\'une photo existante',
            'Contenu inapproprié',
            'Atteinte à la vie privée (personnes reconnaissables)',
            'Droits d\'auteur non respectés',
        ];

        foreach ($reasons as $order => $label) {
            PhotoRejectionReason::query()->updateOrCreate(
                ['slug' => Str::slug($label)],
                ['label' => $label, 'sort_order' => $order, 'is_active' => true],
            );
        }
    }
}
