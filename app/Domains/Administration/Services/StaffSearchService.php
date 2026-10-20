<?php

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffBin;
use App\Domains\Booking\Models\Booking;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Resources\Models\Resource;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;

class StaffSearchService
{
    /** @return list<array<string, mixed>> */
    public function search(Administrator $actor, string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
        $items = [];
        $add = function (string $kind, string $title, string $detail, string $url, array $extra = []) use (&$items): void {
            $items[] = ['kind' => $kind, 'title' => $title, 'detail' => $detail, 'url' => $url, 'actions' => []] + $extra;
        };
        $students = Student::query()->where('identity_status', '!=', 'merged')->where(fn ($query) => $query->where('name_normalized', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))->orderBy('name_normalized')->limit(10)->get(['id', 'first_name', 'last_name', 'email', 'phone']);
        foreach ($students as $student) {
            $actions = [['label' => 'Add Note', 'url' => route('admin.student-bins.index', $student)], ['label' => 'Create Task', 'url' => route('admin.tasks.index', ['student_id' => $student->id])]];
            if ($actor->isAdmin()) {
                $actions[] = ['label' => 'Create Booking', 'url' => route('admin.bookings.create', ['student_id' => $student->id])];
                $actions[] = ['label' => 'Record Payment', 'url' => route('admin.billing.cashier', ['student_id' => $student->id])];
                $actions[] = ['label' => 'Adjust Credits', 'url' => route('admin.students.show', ['student' => $student->id, 'billing_tab' => 'credits'])];
            }
            $items[] = ['kind' => 'student', 'title' => $student->name, 'detail' => $student->email ?? '', 'url' => route('admin.students.show', $student), 'email' => $student->email, 'phone' => $student->phone, 'whatsapp_url' => $student->phone ? 'https://wa.me/'.preg_replace('/[^0-9]/', '', $student->phone) : null, 'actions' => $actions];
        }
        foreach (Contact::query()->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('display_email', 'like', $like)->orWhere('phone', 'like', $like))->orderBy('id')->limit(10)->get(['id', 'name', 'email']) as $contact) {
            $add('contact', $contact->name ?: 'Contact #'.$contact->id, $contact->email ?? '', route('admin.contacts.show', $contact));
        }
        $bookingQuery = Booking::query()->where(function ($query) use ($like, $term): void {
            $query->where('confirmation_token', 'like', $like)->orWhereHas('contact', fn ($contacts) => $contacts->where('name', 'like', $like))->orWhereHas('student', fn ($students) => $students->where('name_normalized', 'like', $like));
            if (preg_match('/^(?:BOOK-)?(\d+)$/i', $term, $match)) {
                $query->orWhere('id', $match[1]);
            }
        });
        foreach ($bookingQuery->orderByDesc('id')->limit(10)->get(['id', 'start_at_utc', 'status']) as $booking) {
            $add('booking', 'Session #'.$booking->id, $booking->start_at_utc->format('j M Y').' · '.$booking->status, route('admin.bookings.show', $booking));
        }
        foreach (StaffBin::query()->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('body', 'like', $like))->orderByDesc('id')->limit(10)->get(['id', 'title']) as $note) {
            $add('staff_note', $note->title, 'Shared staff note', route('admin.staff-bins.index', ['q' => $note->title]));
        }
        foreach (StudentBin::query()->whereHas('student', fn ($query) => $query->whereNull('merged_into_student_id'))->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('body', 'like', $like))->orderByDesc('id')->limit(10)->get(['id', 'student_id', 'title']) as $note) {
            $add('educational_note', $note->title, 'Educational note #'.$note->id, route('admin.student-bins.index', ['student' => $note->student_id, 'q' => $note->title]));
        }
        if ($actor->isAdmin()) {
            foreach (Resource::query()->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('slug', 'like', $like))->orderBy('title')->limit(10)->get(['id', 'title', 'status']) as $resource) {
                $add('resource', $resource->title, $resource->status, route('admin.resources.edit', $resource));
            }
            $payments = PaymentRecord::query()->where('transaction_reference', 'like', $like);
            if (preg_match('/^(?:PAY-)?(\d+)$/i', $term, $match)) {
                $payments->orWhere('id', $match[1]);
            }
            foreach ($payments->orderByDesc('id')->limit(10)->get(['id', 'student_package_id', 'amount_paid', 'currency']) as $payment) {
                $add('payment', 'PAY-'.$payment->id, $payment->currency.' '.$payment->amount_paid, route('admin.packages.lifecycle', $payment->student_package_id));
            }
        }

        return $items;
    }
}
