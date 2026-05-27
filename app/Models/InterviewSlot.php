<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class InterviewSlot extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'public_id', 'tenant_id', 'candidate_id', 'job_posting_id',
        'stage', 'starts_at', 'ends_at', 'location_kind',
        'meeting_url', 'meeting_address', 'status', 'attendees', 'notes',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at'   => 'datetime',
        'attendees' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (InterviewSlot $s) {
            $s->public_id ??= (string) Str::uuid();
        });
    }

    public function candidate(): BelongsTo  { return $this->belongsTo(Candidate::class); }
    public function jobPosting(): BelongsTo { return $this->belongsTo(JobPosting::class); }
}
