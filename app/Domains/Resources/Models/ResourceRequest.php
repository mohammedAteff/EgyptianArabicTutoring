<?php

namespace App\Domains\Resources\Models;

use App\Domains\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResourceRequest extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'resource_requests';

    protected $fillable = [
        'student_id', 'submitted_email',
        'contact_id',
        'resource_id',
        'visitor_token',
        'session_token',
        'consumed_challenge_hash',
        'source',
        'medium',
        'campaign',
        'content',
        'term',
        'landing_page',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'submitted_email' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->created_at ??= now();
        });
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /** @return BelongsTo<\App\Domains\Resources\Models\Resource, $this> */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class, 'request_id');
    }
}
