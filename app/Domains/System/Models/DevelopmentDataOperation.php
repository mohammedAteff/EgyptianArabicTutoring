<?php

namespace App\Domains\System\Models;

use Database\Factories\Domains\System\Models\DevelopmentDataOperationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DevelopmentDataOperation extends Model
{
    /** @use HasFactory<DevelopmentDataOperationFactory> */
    use HasFactory;

    protected $fillable = ['administrator_id', 'token_hash', 'session_binding', 'security_fingerprint', 'type', 'domain', 'scope', 'preview', 'status', 'expires_at', 'completed_at', 'archive_path', 'summary'];

    protected $hidden = ['token_hash', 'session_binding', 'security_fingerprint', 'archive_path'];

    protected function casts(): array
    {
        return ['scope' => 'array', 'preview' => 'array', 'summary' => 'array', 'expires_at' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    protected static function newFactory(): DevelopmentDataOperationFactory
    {
        return DevelopmentDataOperationFactory::new();
    }
}
