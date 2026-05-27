<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'url', 'secret', 'events',
        'is_active', 'last_success_at', 'last_failure_at', 'consecutive_failures',
    ];

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
