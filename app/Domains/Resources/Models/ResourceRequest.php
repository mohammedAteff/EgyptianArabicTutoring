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
        'contact_id',
        'resource_id',
        'visitor_token',
        'session_token',
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
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class, 'request_id');
    }
}
