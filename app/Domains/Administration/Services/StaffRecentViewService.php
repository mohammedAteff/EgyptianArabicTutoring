<?php

namespace App\Domains\Administration\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Models\StaffRecentView;
use App\Domains\Booking\Models\Booking;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Model;

class StaffRecentViewService
{
    public function record(Administrator $administrator, string $type, int $id): void
    {
        StaffRecentView::upsert(['administrator_id' => $administrator->id, 'entity_type' => $type, 'entity_id' => $id, 'viewed_at' => now('UTC')], ['administrator_id', 'entity_type', 'entity_id'], ['viewed_at']);
        $keep = StaffRecentView::query()->where('administrator_id', $administrator->id)->orderByDesc('viewed_at')->orderByDesc('id')->limit(30)->pluck('id');
        StaffRecentView::query()->where('administrator_id', $administrator->id)->whereNotIn('id', $keep)->delete();
    }

    /** @return list<array{label: string, url: string}> */
    public function forAdministrator(Administrator $administrator): array
    {
        $recent = StaffRecentView::query()->where('administrator_id', $administrator->id)->orderByDesc('viewed_at')->orderByDesc('id')->limit(12)->get();
        $students = Student::query()->where('identity_status', '!=', 'merged')->where('operational_status', 'active')->whereIn('id', $recent->where('entity_type', 'student')->pluck('entity_id'))->get()->keyBy('id');
        $bookings = Booking::query()->whereIn('id', $recent->where('entity_type', 'booking')->pluck('entity_id'))->get(['id'])->keyBy('id');
        $contacts = Contact::query()->whereIn('id', $recent->where('entity_type', 'contact')->pluck('entity_id'))->get(['id', 'name'])->keyBy('id');
        $items = [];
        foreach ($recent as $view) {
            $record = match ($view->entity_type) {
                'student' => $students->get($view->entity_id), 'booking' => $bookings->get($view->entity_id), 'contact' => $contacts->get($view->entity_id), default => null,
            };
            if (! $record instanceof Model) {
                continue;
            }
            $items[] = [
                'label' => match ($view->entity_type) {
                    'student' => $record->name, 'contact' => $record->name ?? 'Contact #'.$record->id, default => 'Booking #'.$record->id,
                },
                'url' => route(match ($view->entity_type) {
                    'student' => 'admin.students.show', 'contact' => 'admin.contacts.show', default => 'admin.bookings.show'
                }, $record->id),
            ];
        }

        return $items;
    }
}
