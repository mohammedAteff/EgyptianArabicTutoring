@extends('layouts.admin')

@section('content')
<x-student-quick-actions :student="$student" />
<x-student-operational-alerts :student="$student" :alerts="$operationalAlerts" />
@if(auth('web')->user()?->isAdmin())
<form method="POST" action="{{ route('admin.students.operational-status', $student) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
    @csrf @method('PATCH')
    <label class="flex-1 text-sm font-semibold">Operational status<select name="status" class="mt-1 block w-full rounded-lg border border-slate-300 bg-white p-3">@foreach(['active', 'inactive', 'archived'] as $status)<option @selected($student->operational_status === $status)>{{ $status }}</option>@endforeach</select></label>
    <x-operational-reason-select /><button class="min-h-11 rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Save operational status</button>
    <p class="w-full text-xs text-slate-500">Inactive / archived students leave default roster and follow-ups. Existing lessons stay visible; financial history and account suspension are separate.</p>
</form>
<form method="POST" action="{{ route('admin.students.meeting-preference', $student->id) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
    @csrf
    <label class="flex-1 text-sm">Preferred meeting provider<select name="preferred_meeting_provider_id" class="mt-1 block w-full rounded-lg border border-slate-300 p-3"><option value="">Use default provider</option>@foreach($meetingProviders as $provider)<option value="{{ $provider->id }}" @selected($student->preferred_meeting_provider_id === $provider->id)>{{ $provider->name }}</option>@endforeach</select></label>
    <button class="rounded-lg bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Save meeting preference</button>
</form>
@endif
@if(auth('web')->user()?->isSuperAdmin())
<form method="POST" action="{{ route('admin.accounts.suspension', ['type' => 'student', 'account' => $student->id]) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4">
    @csrf
    <input type="hidden" name="suspend" value="{{ $student->suspended_at ? '0' : '1' }}">
    <label class="flex-1 text-sm">Reason<input name="reason" required maxlength="1000" class="mt-1 block w-full rounded-lg border border-slate-300 p-2"></label>
    <span class="pb-2 text-sm font-semibold {{ $student->suspended_at ? 'text-rose-700' : 'text-emerald-700' }}">{{ $student->suspended_at ? 'Suspended' : 'Active' }}</span>
    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white">{{ $student->suspended_at ? 'Restore account' : 'Suspend account' }}</button>
</form>
@endif

<div class="mx-auto max-w-7xl space-y-6">
    @if($learningProfile) @include('admin.students._learning') @endif
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <a href="{{ route('admin.students.index') }}" class="text-sm font-semibold text-amber-800 hover:text-amber-950">← Student Records</a>
            <p class="mt-4 text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Student #{{ $student->id }}</p>
            <h1 class="mt-2 font-serif text-3xl font-bold tracking-tight text-slate-950">{{ $student->first_name }} {{ $student->last_name }}</h1>
            <p class="mt-2 text-sm text-slate-600">{{ $student->email ?: 'No email on file' }} <span class="px-1 text-slate-300">·</span> {{ $student->phone ?: 'No phone on file' }}</p>
        </div>
        @if(! $isAssistant)
            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold uppercase tracking-wide text-slate-700">{{ str_replace('_', ' ', $student->identity_status) }}</span>
        @endif
    </div>

    @if(session('success'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900">
            <ul class="list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @if(! $isAssistant)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5 flex items-start justify-between gap-3" data-copy-section>
                <div><h2 class="font-serif text-xl font-bold text-slate-900">Identity details</h2>
                <p class="mt-1 text-sm text-slate-500">Only authorized administrators can change identity and verification information.</p></div>
                <x-copy-button :all="true" label="Copy Student Details" />
            </div>
            <form method="POST" action="{{ route('admin.students.update', $student->id) }}" data-student-identity class="grid gap-4 sm:grid-cols-2">
                @csrf
                @method('PATCH')
                <div>
                    <div class="flex items-start justify-between gap-2" data-copy-section><label for="first_name" class="py-3 text-sm font-semibold text-slate-700">First name</label><x-copy-button field="first_name" label="Copy First name" /></div>
                    <input id="first_name" name="first_name" required value="{{ old('first_name', $student->first_name) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200">
                </div>
                <div>
                    <div class="flex items-start justify-between gap-2" data-copy-section><label for="last_name" class="py-3 text-sm font-semibold text-slate-700">Last name</label><x-copy-button field="last_name" label="Copy Last name" /></div>
                    <input id="last_name" name="last_name" required value="{{ old('last_name', $student->last_name) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200">
                </div>
                <div>
                    <div class="flex items-start justify-between gap-2" data-copy-section><label for="email" class="py-3 text-sm font-semibold text-slate-700">Email</label><x-copy-button field="email" label="Copy Email" /></div>
                    <input id="email" name="email" type="email" value="{{ old('email', $student->email) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200">
                </div>
                <div>
                    <div class="flex items-start justify-between gap-2" data-copy-section><label for="phone" class="py-3 text-sm font-semibold text-slate-700">Phone (E.164 after save)</label><x-copy-button field="phone" label="Copy Phone (E.164 after save)" /></div>
                    <div class="grid grid-cols-[5rem_1fr] gap-2">
                        <input name="phone_country" aria-label="Phone country code" value="{{ old('phone_country') }}" maxlength="2" placeholder="EG" class="rounded-xl border border-slate-300 px-3 py-2.5 text-sm uppercase focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200">
                        <input id="phone" name="phone" type="tel" value="{{ old('phone', $student->phone) }}" class="min-w-0 rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Enter a country code only for national-format numbers; international numbers should start with +.</p>
                </div>
                <div>
                    <div class="flex items-start justify-between gap-2" data-copy-section><label for="date_of_birth" class="py-3 text-sm font-semibold text-slate-700">Date of birth</label><x-copy-button field="date_of_birth" label="Copy Date of birth" /></div>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $student->date_of_birth?->format('Y-m-d')) }}" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200">
                </div>
                <div>
                    <div class="flex items-start justify-between gap-2" data-copy-section><label for="identity_status" class="py-3 text-sm font-semibold text-slate-700">Identity status</label><x-copy-button field="identity_status" label="Copy Identity status" /></div>
                    <select id="identity_status" name="identity_status" required class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200">
                        <option value="legacy_unverified" @selected(old('identity_status', $student->identity_status) === 'legacy_unverified')>Legacy — unverified</option>
                        <option value="verified" @selected(old('identity_status', $student->identity_status) === 'verified')>Verified</option>
                    </select>
                </div>
                <div>
                    <div class="flex items-start justify-between gap-2" data-copy-section><label for="preferred_timezone" class="py-3 text-sm font-semibold text-slate-700">Preferred timezone (IANA)</label><x-copy-button field="preferred_timezone" label="Copy Preferred timezone (IANA)" /></div>
                    <input id="preferred_timezone" name="preferred_timezone" value="{{ old('preferred_timezone', $student->preferred_timezone) }}" placeholder="Africa/Cairo" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200">
                </div>
                <div class="sm:col-span-2">
                    <div class="flex items-start justify-between gap-2" data-copy-section><label for="internal_notes" class="py-3 text-sm font-semibold text-slate-700">Internal staff notes</label><x-copy-button field="internal_notes" label="Copy Internal staff notes" /></div>
                    <textarea id="internal_notes" name="internal_notes" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200">{{ old('internal_notes', $student->internal_notes) }}</textarea>
                </div>
                <div class="sm:col-span-2 flex justify-end">
                    <button class="rounded-xl bg-slate-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-400">Save identity details</button>
                </div>
            </form>
        </section>
    @endif


    <a href="{{ route('admin.student-bins.index', $student->id) }}" class="inline-flex rounded-xl bg-white px-4 py-3 text-sm font-semibold text-amber-800">Educational Notes →</a>
    @can('manageTeaching', $student)<a href="{{ route('admin.students.teaching', $student) }}" class="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Teaching workspace →</a>@endcan
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4 flex items-end justify-between gap-4">
            <div>
                <h2 class="font-serif text-xl font-bold text-slate-900">Sessions</h2>
                <p class="mt-1 text-sm text-slate-500">Bookings remain linked to their original schedule and history.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">{{ $bookings->count() }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500"><tr><th class="py-3 pr-4">Session</th><th class="py-3 pr-4">Business time</th><th class="py-3 pr-4">Status</th><th class="py-3">Admin follow-up</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bookings as $booking)
                        <tr><td class="py-3 pr-4 font-medium text-slate-900">{{ $booking->sessionType?->name ?? 'Tutoring session' }} @can('manageLessonWorkspace', $booking)<a href="{{ route('admin.lessons.show', $booking) }}" class="mt-1 inline-flex min-h-11 items-center text-sm font-semibold text-amber-700 underline">Lesson Workspace</a>@endcan</td><td class="py-3 pr-4 text-slate-600">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($booking->business_start) }} <span class="text-xs text-slate-400">{{ $businessTz }}</span></td><td class="py-3 pr-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ str_replace('_', ' ', $booking->status) }}</span></td><td class="py-3 text-slate-600">{{ $booking->admin_reconfirmation_needed ? 'Reconfirmation needed' : '—' }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-8 text-center text-slate-500">No bookings are linked to this student.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <div class="mb-4">
            <h2 class="font-serif text-xl font-bold text-slate-900">Questionnaires</h2>
            <p class="mt-1 text-sm text-slate-500">Assistant accounts see only answers explicitly marked assistant-visible.</p>
        </div>
        <div class="space-y-4">
            @forelse($formSubmissions as $submission)
                <article class="rounded-xl border border-slate-200 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-semibold text-slate-900">{{ $submission->version?->form?->title ?? 'Questionnaire' }}</h3>
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $submission->status === 'submitted' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800' }}">{{ ucfirst($submission->status) }} · revision {{ $submission->submission_revision }}</span>
                    </div>
                    @if($submission->answers->isNotEmpty())
                        <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                            @foreach($submission->answers as $answer)
                                <div class="rounded-lg bg-slate-50 p-3"><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $answer->question?->label }}</dt><dd class="mt-1 break-words whitespace-pre-wrap text-sm text-slate-800">{{ $answer->value_text }}</dd></div>
                            @endforeach
                        </dl>
                    @else
                        <p class="mt-3 text-sm text-slate-500">No visible answers.</p>
                    @endif
                </article>
            @empty
                <p class="rounded-xl bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">No questionnaire responses yet.</p>
            @endforelse
        </div>
    </section>

    @if(! $isAssistant)
        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="mb-5">
                <h2 class="font-serif text-xl font-bold text-slate-900">Session packages & payments</h2>
                <p class="mt-1 text-sm text-slate-500">Balances are derived from the append-only payment and credit ledgers. Refunds do not restore credits.</p>
            </div>

            @include('admin.students.package-management')
            <details class="mt-5 rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Create a package</summary>
            <form method="POST" action="{{ route('admin.students.packages.store', $student->id) }}" class="grid gap-3 rounded-xl bg-slate-50 p-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <h3 class="font-semibold text-slate-900 sm:col-span-2 lg:col-span-4">Create a package</h3>
                <input name="package_name" required maxlength="160" placeholder="Package name" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <label class="text-sm">Entitlement type<select name="entitlement_code" required class="mt-1 block w-full rounded-lg border border-slate-300 p-3"><option value="">Choose a type</option>@foreach($entitlementTypes as $type)<option value="{{ $type->code }}">{{ $type->label }}</option>@endforeach</select></label>
                <input name="total_sessions_allocated" required type="number" min="1" max="500" placeholder="Sessions" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <input name="original_price" required inputmode="decimal" placeholder="Original price" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <input name="discount_amount" required inputmode="decimal" value="0.00" placeholder="Discount" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <input name="currency" required maxlength="3" value="USD" placeholder="Currency" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm uppercase">
                <input name="expiration_date" type="date" min="{{ now()->toDateString() }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                <input type="hidden" name="package_idempotency_key" value="{{ old('package_idempotency_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <button class="rounded-lg bg-amber-700 px-4 py-2 text-sm font-bold text-white hover:bg-amber-800">Create package</button>
            </form>

            </details>
        </section>

        @if(auth()->user()->role === 'super_admin')
            <section class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 sm:p-6">
                    <h2 class="font-serif text-xl font-bold text-slate-900">Merge student records</h2>
                    <p class="mt-1 text-sm text-slate-700">Owner-only. This moves ownership links while preserving bookings, form history, payment facts, and ledger entries.</p>
                    <form method="POST" action="{{ route('admin.students.merge', $student->id) }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
                        @csrf
                        <label class="sr-only" for="secondary_student_id">Student ID to merge into this record</label>
                        <input id="secondary_student_id" name="secondary_student_id" required type="number" min="1" placeholder="Secondary student ID" class="min-w-0 flex-1 rounded-xl border border-amber-300 bg-white px-3 py-2.5 text-sm">
                        <button class="rounded-xl bg-amber-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-amber-900">Merge into this student</button>
                    </form>
                </div>
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 sm:p-6">
                    <h2 class="font-serif text-xl font-bold text-rose-950">Anonymize personal data</h2>
                    <p class="mt-1 text-sm text-rose-900">Owner-only and irreversible through the interface. Financial and operational history is retained; personal fields and form answers are redacted.</p>
                    <form method="POST" action="{{ route('admin.students.anonymize', $student->id) }}" class="mt-4 flex flex-col gap-3 sm:flex-row" onsubmit="return confirm('Anonymize this student’s personal data? This cannot be undone.');">
                        @csrf
                        <label class="sr-only" for="anonymize-confirmation">Type ANONYMIZE to confirm</label>
                        <input id="anonymize-confirmation" name="confirmation" required autocomplete="off" placeholder="Type ANONYMIZE" class="min-w-0 flex-1 rounded-xl border border-rose-300 bg-white px-3 py-2.5 text-sm">
                        <button class="rounded-xl bg-rose-800 px-4 py-2.5 text-sm font-bold text-white hover:bg-rose-900">Anonymize student</button>
                    </form>
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
