<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScreeningResult extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'candidate_id', 'job_posting_id',
        'overall_score', 'must_have_matches', 'nice_to_have_matches',
        'disqualifier_hits', 'risk_flags', 'summary',
        'model_used', 'model_meta',
    ];

    protected $casts = [
        'must_have_matches'    => 'array',
        'nice_to_have_matches' => 'array',
        'disqualifier_hits'    => 'array',
        'risk_flags'           => 'array',
        'model_meta'           => 'array',
    ];

    public function candidate(): BelongsTo  { return $this->belongsTo(Candidate::class); }
    public function jobPosting(): BelongsTo { return $this->belongsTo(JobPosting::class); }
}
