<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->default(UserRole::User->value)->after('password');
            $table->string('status', 20)->default(UserStatus::Pending->value)->after('role');
            $table->string('username', 60)->unique()->nullable()->after('status');
            $table->text('bio')->nullable()->after('username');
            $table->foreignId('favorite_station_id')->nullable()->after('bio')->constrained('stations')->nullOnDelete();
            $table->foreignId('favorite_line_id')->nullable()->after('favorite_station_id')->constrained('lines')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('favorite_line_id');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable()->after('approved_by');

            $table->index('role');
            $table->index('status');
        });

        // The column defaults above ('user'/'pending') would otherwise silently
        // demote every account that already existed before this migration —
        // on this app that's the single seeded admin. Promote pre-existing
        // rows explicitly so nobody gets locked out on deploy.
        DB::table('users')->update([
            'role' => UserRole::Admin->value,
            'status' => UserStatus::Approved->value,
            'approved_at' => now(),
        ]);

        DB::table('users')->whereNull('username')->select('id', 'name')->orderBy('id')->each(function (object $user): void {
            $slug = Str::slug($user->name) ?: 'utilisateur';
            $candidate = $slug;
            $index = 2;

            while (DB::table('users')->where('username', $candidate)->exists()) {
                $candidate = "{$slug}-{$index}";
                $index++;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('favorite_station_id');
            $table->dropConstrainedForeignId('favorite_line_id');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['role', 'status', 'username', 'bio', 'approved_at', 'rejection_reason']);
        });
    }
};
