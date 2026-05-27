<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'role', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'bool',
        ];
    }

    public function isOwner(): bool     { return $this->role === 'owner'; }
    public function isRecruiter(): bool { return in_array($this->role, ['owner', 'recruiter'], true); }
    public function canApprove(): bool  { return $this->isRecruiter(); }
}
