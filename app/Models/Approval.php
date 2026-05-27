<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class Approval extends Model
{
    use HasFactory, BelongsToTenant;

    public const STATUS_PENDING  = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED  = 'expired';

    protected $fillable = [
        'public_id', 'tenant_id', 'approvable_type', 'approvable_id',
        'action', 'status', 'rationale', 'payload',
        'requested_by_user_id', 'decided_by_user_id',
        'decided_at', 'expires_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'decided_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Approval $a) {
            $a->public_id  ??= (string) Str::uuid();
            $a->expires_at ??= now()->addDays(7);
        });
    }

    public function approvable(): MorphTo  { return $this->morphTo(); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class, 'requested_by_user_id'); }
    public function decider(): BelongsTo   { return $this->belongsTo(User::class, 'decided_by_user_id'); }
}
