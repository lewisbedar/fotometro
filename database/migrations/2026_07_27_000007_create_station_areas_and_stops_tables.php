<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->unique();
            $table->string('public_external_id')->nullable()->index();
            $table->string('name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('area_type')->nullable();
            $table->string('source')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('station_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('station_area_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_id')->unique();
            $table->string('zone_external_id')->nullable()->index();
            $table->string('name');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('source')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_stops');
        Schema::dropIfExists('station_areas');
    }
};
