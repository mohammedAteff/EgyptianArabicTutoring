<?php

namespace App\Domains\Audit\Models;

use App\Domains\Administration\Models\Administrator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'administrator_id',
        'action',
        'entity_type',
        'entity_id',
        'previous_data',
        'new_data',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_data' => 'array',
            'new_data' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'administrator_id');
    }
}
