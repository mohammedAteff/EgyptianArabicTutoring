<?php

namespace App\Domains\Administration\Models;

use App\Domains\Administration\Notifications\AdminResetPasswordNotification;
use App\Domains\CMS\Models\Blog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $suspended_at
 */
class Administrator extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'administrators';

    protected $fillable = [
        'suspended_at', 'suspended_by', 'suspension_reason',
        'name',
        'email',
        'password',
        'role',
        'time_format',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_pending_secret',
        'two_factor_pending_session', 'two_factor_version', 'two_factor_last_used_step',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'suspended_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_pending_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_pending_at' => 'datetime',
            'two_factor_last_used_step' => 'integer',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'super_admin' => 'Super Admin',
            'admin' => 'Admin',
            'assistant' => 'Assistant',
            default => str($this->role)->replace('_', ' ')->title()->toString(),
        };
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
    }

    public function requiresTwoFactor(): bool
    {
        return $this->isSuperAdmin() && ($this->two_factor_confirmed_at !== null || $this->two_factor_secret !== null);
    }

    public function isAssistant(): bool
    {
        return $this->role === 'assistant';
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new AdminResetPasswordNotification($token));
    }

    public function blogs(): HasMany
    {
        return $this->hasMany(Blog::class, 'author_id');
    }
}
