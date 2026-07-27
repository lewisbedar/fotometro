<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_accesses', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('name')->nullable();
            $table->string('reference')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('access_type')->nullable();
            $table->string('street')->nullable();
            $table->text('description')->nullable();
            $table->boolean('wheelchair_accessible')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->string('source')->nullable();
            $table->json('source_payload')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
        });

        Schema::create('access_station', function (Blueprint $table) {
            $table->foreignId('station_access_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->primary(['station_access_id', 'station_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('access_station');
        Schema::dropIfExists('station_accesses');
    }
};
