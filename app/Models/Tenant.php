<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id', 'name', 'slug', 'contact_email', 'contact_phone',
        'brand_voice', 'brand_profile', 'webhook_url', 'webhook_secret',
        'default_outreach_from', 'default_outreach_signature',
        'timezone', 'require_approval', 'is_active',
    ];

    protected $casts = [
        'brand_profile'    => 'array',
        'require_approval' => 'bool',
        'is_active'        => 'bool',
        'suspended_at'     => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Tenant $t) {
            $t->public_id ??= (string) Str::uuid();
            $t->slug      ??= Str::slug($t->name);
        });
    }

    public function users(): HasMany    { return $this->hasMany(User::class); }
    public function apiKeys(): HasMany  { return $this->hasMany(ApiKey::class); }
    public function jobPostings(): HasMany { return $this->hasMany(JobPosting::class); }
    public function candidates(): HasMany  { return $this->hasMany(Candidate::class); }
    public function webhookEndpoints(): HasMany { return $this->hasMany(WebhookEndpoint::class); }
}
