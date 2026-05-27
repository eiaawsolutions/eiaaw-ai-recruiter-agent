<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OutreachMessage extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'public_id', 'tenant_id', 'candidate_id', 'job_posting_id',
        'channel', 'direction', 'subject', 'body', 'variables',
        'from_address', 'to_address', 'reply_to',
        'status', 'approved_at', 'approved_by_user_id', 'sent_at', 'replied_at',
        'provider', 'provider_message_id', 'provider_events',
        'model_used', 'idempotency_key',
    ];

    protected $casts = [
        'variables'       => 'array',
        'provider_events' => 'array',
        'approved_at'     => 'datetime',
        'sent_at'         => 'datetime',
        'replied_at'      => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (OutreachMessage $m) {
            $m->public_id ??= (string) Str::uuid();
        });
    }

    public function candidate(): BelongsTo   { return $this->belongsTo(Candidate::class); }
    public function jobPosting(): BelongsTo  { return $this->belongsTo(JobPosting::class); }
    public function approvedBy(): BelongsTo  { return $this->belongsTo(User::class, 'approved_by_user_id'); }
}
