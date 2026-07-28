<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('station_accesses', function (Blueprint $table) {
            $table->string('number', 16)->nullable()->after('reference');
        });

        // Backfill from the IDFM payload already stored on each row: 'accshortname'
        // is the official exit number, previously captured but discarded because
        // it shared a value() candidate list with the 'name' column.
        DB::table('station_accesses')
            ->where('source', 'idfm')
            ->whereNotNull('source_payload')
            ->orderBy('id')
            ->select(['id', 'source_payload'])
            ->each(function (object $row): void {
                $payload = json_decode($row->source_payload, true);
                $number = $payload['accshortname'] ?? null;

                if ($number !== null && $number !== '') {
                    DB::table('station_accesses')->where('id', $row->id)->update(['number' => $number]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('station_accesses', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
