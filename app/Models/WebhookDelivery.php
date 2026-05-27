<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WebhookDelivery extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'public_id', 'tenant_id', 'webhook_endpoint_id', 'event_type', 'payload',
        'signature', 'status', 'http_status', 'attempts', 'last_error',
        'next_retry_at', 'delivered_at',
    ];

    protected $casts = [
        'payload'       => 'array',
        'next_retry_at' => 'datetime',
        'delivered_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebhookDelivery $d) {
            $d->public_id ??= (string) Str::uuid();
        });
    }

    public function endpoint(): BelongsTo { return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id'); }
}
