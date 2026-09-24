<?php

namespace App\Domains\Resources\Models;

use App\Domains\Contacts\Models\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResourceDownload extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'resource_downloads';

    protected $fillable = [
        'resource_id',
        'contact_id',
        'request_id',
        'visitor_token',
        'session_token',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->created_at ??= now();
        });
    }

    public function resource(): BelongsTo
    {
        return $this->belongsTo(Resource::class, 'resource_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ResourceRequest::class, 'request_id');
    }
}
