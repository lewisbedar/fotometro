<?php

use App\Enums\CoverageStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('city')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('district')->nullable();
            $table->date('opening_date')->nullable();
            $table->text('description')->nullable();
            $table->string('coverage_status', 32)->default(CoverageStatus::NotStarted->value)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stations');
    }
};
