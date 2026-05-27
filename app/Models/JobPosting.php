<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JobPosting extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'public_id', 'tenant_id', 'title', 'department', 'seniority',
        'work_mode', 'location', 'country',
        'comp_currency', 'comp_min', 'comp_max', 'comp_period',
        'scope', 'must_haves', 'nice_to_haves', 'ideal_candidate_archetypes',
        'disqualifiers', 'status', 'owner_user_id', 'opened_at', 'closed_at',
    ];

    protected $casts = [
        'must_haves'                  => 'array',
        'nice_to_haves'               => 'array',
        'ideal_candidate_archetypes'  => 'array',
        'disqualifiers'               => 'array',
        'opened_at'                   => 'datetime',
        'closed_at'                   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (JobPosting $j) {
            $j->public_id ??= (string) Str::uuid();
        });
    }

    public function owner(): BelongsTo  { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function candidates(): HasMany { return $this->hasMany(Candidate::class); }
}
