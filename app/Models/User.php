<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

#[Fillable(['name', 'email', 'password', 'username', 'bio', 'copyright_display_name'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function favoriteStation(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function favoriteLine(): BelongsTo
    {
        return $this->belongsTo(Line::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'approved_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isModerator(): bool
    {
        return $this->role === UserRole::Moderator;
    }

    public function canModerate(): bool
    {
        return $this->isAdmin() || $this->isModerator();
    }

    public function isApproved(): bool
    {
        return $this->status === UserStatus::Approved;
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function displayCopyrightName(): string
    {
        return $this->copyright_display_name ?: $this->name;
    }

    public function getAvatarUrlAttribute(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        // Same fixed filename is reused on every re-upload, so a version
        // query string is the only way to bust the browser cache.
        return Storage::disk('public')->url($this->avatar_path).'?v='.($this->updated_at?->timestamp ?? time());
    }
}
