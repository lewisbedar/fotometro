<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_photo_category', function (Blueprint $table): void {
            $table->foreignId('photo_id')->constrained()->cascadeOnDelete();
            $table->foreignId('photo_category_id')->constrained()->cascadeOnDelete();
            $table->primary(['photo_id', 'photo_category_id']);
        });

        DB::table('photos')
            ->whereNotNull('photo_category_id')
            ->select('id', 'photo_category_id')
            ->orderBy('id')
            ->each(function (object $photo): void {
                DB::table('photo_photo_category')->insert([
                    'photo_id' => $photo->id,
                    'photo_category_id' => $photo->photo_category_id,
                ]);
            });

        Schema::table('photos', function (Blueprint $table): void {
            $table->dropForeign(['photo_category_id']);
            $table->dropIndex(['photo_category_id']);
            $table->dropColumn('photo_category_id');
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->foreignId('photo_category_id')->nullable()->after('station_access_id')->constrained()->nullOnDelete();
            $table->index('photo_category_id');
        });

        DB::table('photos')->select('id')->orderBy('id')->each(function (object $photo): void {
            $firstCategoryId = DB::table('photo_photo_category')
                ->where('photo_id', $photo->id)
                ->orderBy('photo_category_id')
                ->value('photo_category_id');

            if ($firstCategoryId !== null) {
                DB::table('photos')->where('id', $photo->id)->update(['photo_category_id' => $firstCategoryId]);
            }
        });

        Schema::dropIfExists('photo_photo_category');
    }
};
