<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateSource extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'candidate_id', 'kind', 'url',
        'excerpt', 'snapshot_meta', 'verified_at',
    ];

    protected $casts = [
        'snapshot_meta' => 'array',
        'verified_at'   => 'datetime',
    ];

    public function candidate(): BelongsTo { return $this->belongsTo(Candidate::class); }
}
