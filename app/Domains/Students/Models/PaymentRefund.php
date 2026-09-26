<?php

namespace App\Domains\Students\Models;

use Database\Factories\PaymentRefundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class PaymentRefund extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['payment_record_id', 'student_package_id', 'student_id', 'idempotency_key', 'amount_refunded', 'currency', 'reason', 'refunded_at', 'recorded_by', 'created_at'];

    protected function casts(): array
    {
        return ['amount_refunded' => 'decimal:2', 'refunded_at' => 'datetime', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new RuntimeException('Refund records are append-only.'));
        static::deleting(fn () => throw new RuntimeException('Refund records are append-only.'));
    }

    protected static function newFactory(): PaymentRefundFactory
    {
        return PaymentRefundFactory::new();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(PaymentRecord::class, 'payment_record_id');
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
