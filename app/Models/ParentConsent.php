<?php

namespace App\Models;

use App\Enums\ConsentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ParentConsent extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'parent_name',
        'parent_email',
        'token',
        'status',
        'granted_at',
        'revoked_at',
        'granted_ip',
    ];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'status' => ConsentStatus::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected $attributes = [
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (ParentConsent $consent) {
            $consent->token ??= Str::random(64);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grant(?string $ip = null): bool
    {
        return $this->forceFill([
            'status' => ConsentStatus::Granted,
            'granted_at' => now(),
            'granted_ip' => $ip,
            'revoked_at' => null,
        ])->save();
    }

    /**
     * A parent may withdraw consent at any time. This stops the coach; it does
     * not delete anything the student built. The brain freezes, never
     * destroys.
     */
    public function revoke(): bool
    {
        return $this->forceFill([
            'status' => ConsentStatus::Revoked,
            'revoked_at' => now(),
        ])->save();
    }

    public function isGranted(): bool
    {
        return $this->status === ConsentStatus::Granted;
    }

    public function scopeGranted($query)
    {
        return $query->where('status', ConsentStatus::Granted->value);
    }
}
