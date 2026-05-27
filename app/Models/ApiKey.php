<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'label', 'prefix', 'hash', 'last_four',
        'scopes', 'last_used_at', 'expires_at', 'revoked_at', 'created_by_ip',
    ];

    protected $casts = [
        'scopes'       => 'array',
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
    ];

    /**
     * Mint a new plaintext key + persisted hash row.
     * Plaintext is returned ONCE and never stored.
     *
     * @return array{0: ApiKey, 1: string} [model, plaintext]
     */
    public static function mint(Tenant $tenant, string $label, array $scopes = [], ?\DateTimeInterface $expiresAt = null, ?string $ip = null): array
    {
        $plaintext = 'rcr_' . Str::random(40);
        $prefix    = substr($plaintext, 0, 12);
        $lastFour  = substr($plaintext, -4);
        $hash      = hash('sha256', $plaintext);

        $model = static::create([
            'tenant_id'     => $tenant->id,
            'label'         => $label,
            'prefix'        => $prefix,
            'hash'          => $hash,
            'last_four'     => $lastFour,
            'scopes'        => $scopes,
            'expires_at'    => $expiresAt,
            'created_by_ip' => $ip,
        ]);

        return [$model, $plaintext];
    }

    public function isValid(): bool
    {
        if ($this->revoked_at) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
