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
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'suspended_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin'], true);
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
