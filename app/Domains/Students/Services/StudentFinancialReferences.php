<?php

namespace App\Domains\Students\Services;

use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\StudentPackage;

class StudentFinancialReferences
{
    /** @return array{purchases: array<int, int>, payments: array<int, int>, refunds: array<int, int>} */
    public function forStudent(int $studentId): array
    {
        return [
            'purchases' => $this->number(StudentPackage::query()->where('student_id', $studentId)->orderBy('id')->pluck('id')->all()),
            'payments' => $this->number(PaymentRecord::query()->where('student_id', $studentId)->orderBy('id')->pluck('id')->all()),
            'refunds' => $this->number(PaymentRefund::query()->where('student_id', $studentId)->orderBy('id')->pluck('id')->all()),
        ];
    }

    /** @param list<int> $ids
     * @return array<int, int>
     */
    private function number(array $ids): array
    {
        $numbers = [];
        foreach ($ids as $offset => $id) {
            $numbers[$id] = $offset + 1;
        }

        return $numbers;
    }
}
