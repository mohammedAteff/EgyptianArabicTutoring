<?php

namespace App\Domains\Analytics\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsReconciliationAudit extends Model
{
    use HasFactory;

    protected $table = 'analytics_reconciliation_audits';

    protected $fillable = [
        'audit_type',
        'cutover_at',
        'rebuilt_visitors_count',
        'preserved_historical_count',
        'authoritative_bookings_count',
        'non_comparable_before',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cutover_at' => 'datetime',
            'non_comparable_before' => 'datetime',
            'rebuilt_visitors_count' => 'integer',
            'preserved_historical_count' => 'integer',
            'authoritative_bookings_count' => 'integer',
        ];
    }
}
