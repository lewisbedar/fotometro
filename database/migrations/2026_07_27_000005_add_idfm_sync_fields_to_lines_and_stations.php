<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lines', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('id');
            $table->boolean('is_active')->default(true)->index()->after('sort_order');
            $table->string('source')->nullable()->after('path_geojson');
            $table->json('source_payload')->nullable()->after('source');
            $table->timestamp('source_updated_at')->nullable()->after('source_payload');
        });

        Schema::table('stations', function (Blueprint $table) {
            $table->string('source')->nullable()->after('is_active');
            $table->json('source_payload')->nullable()->after('source');
            $table->timestamp('source_updated_at')->nullable()->after('source_payload');
        });
    }

    public function down(): void
    {
        Schema::table('stations', function (Blueprint $table) {
            $table->dropColumn(['source', 'source_payload', 'source_updated_at']);
        });

        Schema::table('lines', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'is_active', 'source', 'source_payload', 'source_updated_at']);
        });
    }
};
