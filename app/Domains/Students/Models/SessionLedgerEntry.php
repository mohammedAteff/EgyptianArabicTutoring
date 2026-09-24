<?php

namespace App\Domains\Students\Models;

use Database\Factories\SessionLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class SessionLedgerEntry extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['student_id', 'student_package_id', 'booking_id', 'idempotency_key', 'entry_type', 'credit_change', 'description', 'created_by', 'created_at'];

    protected function casts(): array
    {
        return ['credit_change' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Ledger entries are append-only.'));
    }

    protected static function newFactory(): SessionLedgerEntryFactory
    {
        return SessionLedgerEntryFactory::new();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(StudentPackage::class, 'student_package_id');
    }
}
