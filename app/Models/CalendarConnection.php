<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CalendarConnection extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'public_id', 'tenant_id', 'user_id', 'provider', 'account_email', 'calendar_id',
        'access_token', 'refresh_token', 'access_token_expires_at',
        'scopes', 'timezone', 'is_active', 'last_synced_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (CalendarConnection $c) {
            $c->public_id ??= (string) Str::uuid();
        });
    }

    protected $casts = [
        'scopes'                  => 'array',
        'is_active'               => 'bool',
        'access_token'            => 'encrypted',
        'refresh_token'           => 'encrypted',
        'access_token_expires_at' => 'datetime',
        'last_synced_at'          => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->access_token_expires_at
            && $this->access_token_expires_at->copy()->subMinute()->isPast();
    }
}
