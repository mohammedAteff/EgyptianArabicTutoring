<?php

namespace App\Domains\Students\Models;

use App\Domains\Booking\Models\Booking;
use Database\Factories\SessionLedgerEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/** @property-read Student|null $student */
class SessionLedgerEntry extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = ['student_package_entitlement_id', 'entitlement_type_id', 'student_id', 'student_package_id', 'booking_id', 'idempotency_key', 'entry_type', 'credit_change', 'description', 'created_by', 'created_at'];

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

    /** @return BelongsTo<EntitlementType, $this> */
    public function type(): BelongsTo
    {
        return $this->belongsTo(EntitlementType::class, 'entitlement_type_id');
    }

    /** @return BelongsTo<StudentPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(StudentPackage::class, 'student_package_id');
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class)->withTrashed();
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}
