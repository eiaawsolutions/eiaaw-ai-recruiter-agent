<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WebhookEndpoint extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'public_id', 'tenant_id', 'url', 'secret', 'events',
        'is_active', 'last_success_at', 'last_failure_at', 'consecutive_failures',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebhookEndpoint $w) {
            $w->public_id ??= (string) Str::uuid();
        });
    }

    protected $casts = [
        'events'              => 'array',
        'is_active'           => 'bool',
        'last_success_at'     => 'datetime',
        'last_failure_at'     => 'datetime',
        'consecutive_failures' => 'int',
    ];

    public function deliveries(): HasMany { return $this->hasMany(WebhookDelivery::class); }

    public function subscribesTo(string $event): bool
    {
        $events = $this->events ?? [];
        return in_array('*', $events, true) || in_array($event, $events, true);
    }
}
