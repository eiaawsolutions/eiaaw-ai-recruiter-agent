<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InboundWebhookEvent extends Model
{
    // Provider events are not tenant-scoped at ingest — resolution happens
    // during processing. Keep this outside BelongsToTenant.
    protected $fillable = [
        'provider', 'event_id', 'event_type', 'payload',
        'signature_valid', 'processed_at', 'processing_error',
    ];

    protected $casts = [
        'payload'         => 'array',
        'signature_valid' => 'bool',
        'processed_at'    => 'datetime',
    ];
}
