<?php

namespace App\Domains\Contacts\Models;

use App\Domains\Booking\Models\Booking;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Models\ResourceRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'contacts';

    protected $fillable = [
        'name',
        'email',
        'display_email',
        'phone',
        'notes',
        'first_seen_at',
        'last_seen_at',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'merged_into_contact_id',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Contact $contact) {
            if (empty($contact->display_email) && ! empty($contact->email)) {
                $contact->display_email = trim($contact->email);
            }
            if (! empty($contact->email)) {
                $contact->email = static::normalizeEmail($contact->email);
            }
        });
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'contact_id');
    }

    public function resourceRequests(): HasMany
    {
        return $this->hasMany(ResourceRequest::class, 'contact_id');
    }

    public function resourceDownloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class, 'contact_id');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(self::class, 'merged_into_contact_id');
    }

    public function mergedContacts(): HasMany
    {
        return $this->hasMany(self::class, 'merged_into_contact_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('merged_into_contact_id');
    }

    public function isCustomer(): bool
    {
        return $this->bookings()
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereNull('cancelled_at')
            ->exists();
    }

    public function isLead(): bool
    {
        return $this->resourceRequests()->exists() && ! $this->isCustomer();
    }
}
