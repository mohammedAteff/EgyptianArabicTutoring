<?php

namespace App\Domains\Contacts\Services;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DirectoryQuery
{
    public function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'population' => ['nullable', Rule::in(['students', 'resources', 'leads', 'students_resources', 'all'])],
            'resource_id' => ['nullable', 'integer', 'exists:resources,id'],
            'category_id' => ['nullable', 'integer', 'exists:resource_categories,id'],
            'booking_status' => ['nullable', Rule::in(['confirmed', 'completed', 'cancelled', 'no_show'])],
            'activity_from' => ['nullable', 'date_format:Y-m-d'], 'activity_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:activity_from'],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ]);
    }

    public function query(array $filters): Builder
    {
        $owner = Student::tutoringRoster()->where(function ($students): void {
            $students->whereColumn('students.email_normalized', 'contacts.email')
                ->orWhereHas('verifiedEmails', fn ($emails) => $emails->whereNotNull('verified_at')->whereColumn('email_normalized', 'contacts.email'))
                ->orWhereHas('bookings', fn ($bookings) => $bookings->whereColumn('contact_id', 'contacts.id'))
                ->orWhereExists(fn ($requests) => $requests->selectRaw('1')->from('resource_requests')->whereColumn('resource_requests.student_id', 'students.id')->whereColumn('resource_requests.contact_id', 'contacts.id'));
        })->havingRaw('count(*) = 1')->selectRaw('min(students.id)');
        $contacts = Contact::active()->select('contacts.*')->selectSub($owner, 'canonical_student_id')->toBase();
        $mapped = DB::query()->fromSub($contacts, 'people')->select('people.*')
            ->selectSub(DB::table('resource_requests')->selectRaw('count(*)')->whereColumn('contact_id', 'people.id'), 'resource_requests_count')
            ->selectSub(DB::table('resource_downloads')->selectRaw('count(*)')->whereColumn('contact_id', 'people.id'), 'resource_downloads_count');
        if (! empty($filters['resource_id']) || ! empty($filters['category_id'])) {
            $mapped->whereExists(function (Builder $requests) use ($filters): void {
                $requests->selectRaw('1')->from('resource_requests')->join('resources', 'resources.id', '=', 'resource_requests.resource_id')->whereColumn('resource_requests.contact_id', 'people.id');
                if (! empty($filters['resource_id'])) {
                    $requests->where('resources.id', $filters['resource_id']);
                }
                if (! empty($filters['category_id'])) {
                    $requests->where('resources.category_id', $filters['category_id']);
                }
            });
        }
        $stats = DB::query()->fromSub($mapped, 'mapped')->whereNotNull('canonical_student_id')->groupBy('canonical_student_id')
            ->selectRaw('canonical_student_id, min(id) as contact_id, sum(resource_requests_count) as resource_requests_count, sum(resource_downloads_count) as resource_downloads_count, max(last_seen_at) as last_seen_at');
        $students = Student::tutoringRoster()->toBase()->leftJoinSub($stats, 'activity', 'activity.canonical_student_id', '=', 'students.id')
            ->selectRaw("students.id as student_id, activity.contact_id as id, concat(students.first_name, ' ', students.last_name) as name, students.email, students.phone, coalesce(activity.resource_requests_count,0) as resource_requests_count, coalesce(activity.resource_downloads_count,0) as resource_downloads_count, coalesce(activity.last_seen_at,students.updated_at) as last_seen_at, 'Student' as person_type")
            ->selectSub(DB::table('bookings')->selectRaw('count(*)')->whereColumn('student_id', 'students.id'), 'bookings_count');
        if (! empty($filters['resource_id']) || ! empty($filters['category_id'])) {
            $students->whereNotNull('activity.contact_id');
        }
        $leads = DB::query()->fromSub($mapped, 'people')->whereNull('canonical_student_id')
            ->selectRaw("null as student_id, id, name, email, phone, resource_requests_count, resource_downloads_count, last_seen_at, case when resource_requests_count > 0 then 'Resource-only' else 'Lead / contact' end as person_type")
            ->selectSub(DB::table('bookings')->selectRaw('count(*)')->whereColumn('contact_id', 'people.id'), 'bookings_count');
        $query = DB::query()->fromSub($students->unionAll($leads), 'directory');
        $population = $filters['population'] ?? 'all';
        if ($population === 'students') {
            $query->whereNotNull('student_id');
        } elseif ($population === 'resources') {
            $query->whereNull('student_id')->where('resource_requests_count', '>', 0);
        } elseif ($population === 'leads') {
            $query->whereNull('student_id')->where('resource_requests_count', 0);
        } elseif ($population === 'students_resources') {
            $query->where(fn (Builder $people) => $people->whereNotNull('student_id')->orWhere('resource_requests_count', '>', 0));
        }
        if (! empty($filters['search'])) {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']).'%';
            $query->where(fn (Builder $people) => $people->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like)->orWhereExists(fn (Builder $aliases) => $aliases->selectRaw('1')->from('student_emails')->whereColumn('student_emails.student_id', 'directory.student_id')->whereNotNull('verified_at')->where('email_normalized', 'like', $like)));
        }
        if (! empty($filters['booking_status'])) {
            $query->whereExists(fn (Builder $bookings) => $bookings->selectRaw('1')->from('bookings')->where('status', $filters['booking_status'])->where(fn (Builder $owners) => $owners->whereColumn('bookings.student_id', 'directory.student_id')->orWhereColumn('bookings.contact_id', 'directory.id')));
        }
        foreach (['activity_from' => '>=', 'activity_to' => '<'] as $filter => $operator) {
            if (! empty($filters[$filter])) {
                $boundary = CarbonImmutable::parse($filters[$filter], app(TimezoneService::class)->getBusinessTimezone())->startOfDay();
                $query->where('last_seen_at', $operator, ($filter === 'activity_to' ? $boundary->addDay() : $boundary)->utc());
            }
        }

        return $query->orderByDesc('last_seen_at')->orderBy('person_type')->orderBy('id')->orderBy('student_id');
    }
}
