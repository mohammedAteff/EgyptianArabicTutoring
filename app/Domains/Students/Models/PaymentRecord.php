<?php

namespace App\Domains\Students\Models;

use Database\Factories\PaymentRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PaymentRecord extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['student_package_id', 'student_id', 'idempotency_key', 'amount_paid', 'currency', 'payment_method', 'transaction_reference', 'paid_at', 'recorded_by', 'notes', 'created_at'];

    protected function casts(): array
    {
        return ['amount_paid' => 'decimal:2', 'paid_at' => 'datetime', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Payment records are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Payment records are append-only.'));
    }

    protected static function newFactory(): PaymentRecordFactory
    {
        return PaymentRecordFactory::new();
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(StudentPackage::class, 'student_package_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
