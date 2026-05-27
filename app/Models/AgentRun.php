<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class AgentRun extends Model
{
    use HasFactory, BelongsToTenant;

    public const STATUS_RUNNING   = 'running';
    public const STATUS_SUCCEEDED = 'succeeded';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_PARTIAL   = 'partial';

    protected $fillable = [
        'public_id', 'tenant_id', 'agent', 'action',
        'subject_type', 'subject_id', 'status',
        'input_meta', 'output_meta', 'verification_summary',
        'input_tokens', 'output_tokens', 'duration_ms', 'model', 'error',
    ];

    protected $casts = [
        'input_meta'           => 'array',
        'output_meta'          => 'array',
        'verification_summary' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (AgentRun $r) {
            $r->public_id ??= (string) Str::uuid();
        });
    }

    public function subject(): MorphTo { return $this->morphTo(); }
}
