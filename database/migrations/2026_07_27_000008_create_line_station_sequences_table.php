<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('line_station_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('line_id')->constrained()->cascadeOnDelete();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('sequence_key');
            $table->string('branch_key')->nullable();
            $table->string('direction_key')->nullable();
            $table->unsignedSmallInteger('position');
            $table->boolean('is_terminus')->default(false);
            $table->boolean('is_branch_start')->default(false);
            $table->boolean('is_branch_end')->default(false);
            $table->boolean('is_loop_entry')->default(false);
            $table->boolean('is_loop_exit')->default(false);
            $table->boolean('is_shared_trunk')->default(false);
            $table->string('source')->default('gtfs');
            $table->string('gtfs_pattern')->nullable();
            $table->timestamps();

            $table->unique(['line_id', 'sequence_key', 'station_id', 'position'], 'line_sequence_station_position_unique');
            $table->index(['line_id', 'sequence_key', 'position'], 'line_sequence_position_index');
            $table->index(['line_id', 'branch_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('line_station_sequences');
    }
};
