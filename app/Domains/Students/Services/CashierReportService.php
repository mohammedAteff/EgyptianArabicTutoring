<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashierReportService
{
    public function __construct(private TimezoneService $timezones) {}

    public function filters(Request $request): array
    {
        return $request->validate([
            'student_id' => ['nullable', 'integer', 'min:1', 'exists:students,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'transaction_type' => ['nullable', Rule::in(['payment', 'refund', 'credit'])],
            'package_id' => ['nullable', 'integer', 'exists:student_packages,id'],
            'payment_method' => ['nullable', 'string', 'max:80'],
            'package_status' => ['nullable', Rule::in(['active', 'expired', 'completed', 'cancelled'])],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ]);
    }

    public function headers(): array
    {
        return ['Date', 'Student Name', 'Student Email', 'Transaction Type', 'Package Name', 'Amount', 'Currency', 'Credit Change', 'Payment Method', 'Internal Reference', 'Original Payment Reference', 'External Reference', 'Business Timezone'];
    }

    public function rows(array $filters): \Generator
    {
        $types = [
            'payment' => [PaymentRecord::class, 'paid_at'],
            'refund' => [PaymentRefund::class, 'refunded_at'],
            'credit' => [SessionLedgerEntry::class, 'created_at'],
        ];
        foreach ($types as $type => [$model, $dateColumn]) {
            if (! empty($filters['transaction_type']) && $filters['transaction_type'] !== $type) {
                continue;
            }
            $query = $model::query()->with(['student', 'package']);
            if ($type === 'refund') {
                $query->with('payment');
            }
            if ($type === 'credit') {
                $query->where('entry_type', 'courtesy_adjustment');
            }
            $this->apply($query, $dateColumn, $type, $filters);
            foreach ($query->orderByDesc($dateColumn)->orderByDesc('id')->lazy(250) as $record) {
                $student = $record->getRelation('student');
                $amount = match ($type) {
                    'payment' => (float) $record->amount_paid,
                    'refund' => -(float) $record->amount_refunded,
                    default => '',
                };
                yield [
                    $record->{$dateColumn}->copy()->setTimezone($this->timezones->getBusinessTimezone())->format('Y-m-d H:i'),
                    $student instanceof Student ? $student->name : 'Anonymized student', $student instanceof Student ? $student->email : '',
                    match ($type) {
                        'payment' => 'Payment', 'refund' => 'Refund', default => 'Courtesy Credit'
                    },
                    $record->package->package_name, $amount, $type === 'credit' ? '' : $record->currency,
                    $type === 'credit' ? (int) $record->credit_change : '',
                    $type === 'payment' ? $record->payment_method : ($type === 'refund' ? $record->payment->payment_method : ''),
                    strtoupper(match ($type) {
                        'payment' => 'PAY', 'refund' => 'REF', default => 'CRD'
                    }).'-'.$record->id,
                    $type === 'refund' ? 'PAY-'.$record->payment_record_id : '',
                    $type === 'payment' ? $record->transaction_reference ?? '' : ($type === 'refund' ? $record->payment->transaction_reference ?? '' : ''),
                    $this->timezones->getBusinessTimezone(),
                ];
            }
        }
    }

    private function apply(Builder $query, string $dateColumn, string $type, array $filters): void
    {
        foreach (['student_id' => 'student_id', 'package_id' => 'student_package_id'] as $filter => $column) {
            if (! empty($filters[$filter])) {
                $query->where($column, $filters[$filter]);
            }
        }
        foreach (['date_from' => '>=', 'date_to' => '<'] as $filter => $operator) {
            if (! empty($filters[$filter])) {
                $boundary = CarbonImmutable::parse($filters[$filter], $this->timezones->getBusinessTimezone())->startOfDay();
                $query->where($dateColumn, $operator, ($filter === 'date_to' ? $boundary->addDay() : $boundary)->setTimezone('UTC'));
            }
        }
        if (! empty($filters['package_status'])) {
            $query->whereHas('package', fn (Builder $packages) => $packages->where('status', $filters['package_status']));
        }
        if (! empty($filters['payment_method'])) {
            if ($type === 'refund') {
                $query->whereHas('payment', fn (Builder $payments) => $payments->where('payment_method', $filters['payment_method']));
            } elseif ($type === 'payment') {
                $query->where('payment_method', $filters['payment_method']);
            } else {
                $query->whereRaw('1 = 0');
            }
        }
    }
}
