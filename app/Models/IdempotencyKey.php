<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'key', 'request_hash', 'response',
        'status_code', 'locked_until', 'expires_at',
    ];

    protected $casts = [
        'response'     => 'array',
        'locked_until' => 'datetime',
        'expires_at'   => 'datetime',
    ];
}
