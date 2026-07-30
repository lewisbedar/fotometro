<?php

use App\Enums\PhotoModerationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            // nullOnDelete, not cascade: a photo that's already public must
            // outlive the account that submitted it if that account is
            // later deleted.
            $table->foreignId('user_id')->nullable()->after('station_access_id')->constrained()->nullOnDelete();
            $table->string('moderation_status', 20)->default(PhotoModerationStatus::Pending->value)->after('user_id')->index();
            $table->timestamp('moderated_at')->nullable()->after('moderation_status');
            $table->foreignId('moderated_by')->nullable()->after('moderated_at')->constrained('users')->nullOnDelete();
            $table->foreignId('photo_rejection_reason_id')->nullable()->after('moderated_by')->constrained()->nullOnDelete();
            $table->text('rejection_note')->nullable()->after('photo_rejection_reason_id');
        });

        // Every photo that existed before this migration was imported by the
        // trusted admin wizard, with no moderation queue to have passed
        // through — treat it as already approved rather than leaving it
        // stuck as "pending" and invisible to scopePubliclyVisible().
        DB::table('photos')->update([
            'moderation_status' => PhotoModerationStatus::Approved->value,
            'moderated_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropConstrainedForeignId('photo_rejection_reason_id');
            $table->dropColumn(['moderation_status', 'moderated_at', 'rejection_note']);
        });
    }
};
