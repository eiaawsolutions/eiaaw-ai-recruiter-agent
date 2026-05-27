<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Candidate extends Model
{
    use HasFactory, BelongsToTenant;

    public const STAGES = [
        'sourced', 'screened', 'outreach_drafted', 'outreach_pending_approval',
        'outreach_sent', 'replied', 'interview_scheduled', 'shortlisted',
        'hired', 'rejected', 'discarded',
    ];

    protected $fillable = [
        'public_id', 'tenant_id', 'job_posting_id',
        'name', 'title', 'company', 'location', 'country',
        'email', 'phone', 'linkedin_url', 'company_website', 'other_contacts',
        'candidate_type', 'lead_temperature', 'confidence_score',
        'reason_for_fit', 'buying_signal', 'enrichment', 'source',
        'stage', 'stage_changed_at', 'assigned_user_id', 'discard_reason',
        'external_ref',
    ];

    protected $casts = [
        'other_contacts'   => 'array',
        'enrichment'       => 'array',
        'stage_changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Candidate $c) {
            $c->public_id        ??= (string) Str::uuid();
            $c->stage_changed_at ??= now();
        });

        static::updating(function (Candidate $c) {
            if ($c->isDirty('stage')) {
                $c->stage_changed_at = now();
            }
        });
    }

    public function jobPosting(): BelongsTo  { return $this->belongsTo(JobPosting::class); }
    public function sources(): HasMany       { return $this->hasMany(CandidateSource::class); }
    public function screening(): HasOne      { return $this->hasOne(ScreeningResult::class); }
    public function outreachMessages(): HasMany { return $this->hasMany(OutreachMessage::class); }
    public function interviews(): HasMany    { return $this->hasMany(InterviewSlot::class); }

    public function hasVerification(): bool
    {
        return $this->sources()->count() >= (int) config('services.recruiter.min_verification_sources', 1);
    }
}
