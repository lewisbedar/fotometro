<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_line', function (Blueprint $table) {
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('branch')->nullable();
            $table->boolean('is_terminus')->default(false);
            $table->timestamps();

            $table->primary(['station_id', 'line_id']);
            $table->index(['line_id', 'position']);
            $table->index('branch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('station_line');
    }
};
