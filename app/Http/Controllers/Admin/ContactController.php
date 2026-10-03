<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Contacts\Services\DirectoryQuery;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactController extends Controller
{
    public function __construct(
        protected ContactService $contactService
    ) {}

    public function index(Request $request, DirectoryQuery $directory): View
    {
        $filters = $directory->filters($request);

        return view('admin.contacts.index', ['title' => 'Students & Contacts Directory', 'contacts' => $directory->query($filters)->paginate(20)->withQueryString(), 'search' => $filters['search'] ?? '', 'filters' => $filters, 'resourceOptions' => Resource::query()->orderBy('title')->limit(500)->pluck('title', 'id')->all(), 'categoryOptions' => ResourceCategory::query()->orderBy('name')->limit(500)->pluck('name', 'id')->all(), 'duplicateCount' => count($this->contactService->findSuspectedDuplicates())]);
    }

    public function export(Request $request, DirectoryQuery $directory, ExportService $exports): StreamedResponse|BinaryFileResponse
    {
        $filters = $directory->filters($request);
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $rows = function () use ($directory, $filters, $timezone): \Generator {
            foreach ($directory->query($filters)->lazy(250) as $person) {
                yield [$person->name ?? '', $person->email ?? '', $person->phone ?? '', $person->person_type, (int) $person->bookings_count, (int) $person->resource_requests_count, (int) $person->resource_downloads_count, $person->last_seen_at ? CarbonImmutable::parse($person->last_seen_at, 'UTC')->setTimezone($timezone)->format('Y-m-d H:i') : '', $timezone];
            }
        };

        return $exports->export('students_contacts', ['Name', 'Email', 'Phone', 'Population', 'Bookings', 'Resource Requests', 'Downloads', 'Last Active', 'Business Timezone'], $rows(), $filters['format'] ?? 'csv', 'Students and Contacts');
    }

    public function leads(Request $request): View
    {
        // Contacts who requested resources or signed up but haven't booked yet
        $query = Contact::query()
            ->whereDoesntHave('bookings')
            ->withCount(['resourceRequests', 'resourceDownloads']);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $leads = $query->orderByDesc('last_seen_at')->paginate(20)->withQueryString();

        return view('admin.contacts.leads', [
            'title' => 'Student Leads (Content Inquiries)',
            'leads' => $leads,
            'search' => $search,
        ]);
    }

    public function show(Contact $contact): View
    {
        $contact->load([
            'bookings' => fn ($q) => $q->with('sessionType')->orderByDesc('start_at_utc'),
            'resourceRequests' => fn ($q) => $q->with('resource')->orderByDesc('created_at'),
            'resourceDownloads' => fn ($q) => $q->with('resource')->orderByDesc('created_at'),
        ]);

        return view('admin.contacts.show', [
            'title' => ($contact->name ?? 'Student').' — Contact Details',
            'contact' => $contact,
        ]);
    }

    public function duplicates(): View
    {
        $suspectedDuplicates = $this->contactService->findSuspectedDuplicates();

        return view('admin.contacts.duplicates', [
            'title' => 'Review Suspected Duplicate Contacts',
            'duplicateGroups' => $suspectedDuplicates,
        ]);
    }

    public function merge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'canonical_id' => ['required', 'exists:contacts,id'],
            'duplicate_id' => ['required', 'exists:contacts,id', 'different:canonical_id'],
        ]);

        $canonical = Contact::findOrFail($validated['canonical_id']);
        $duplicate = Contact::findOrFail($validated['duplicate_id']);

        $this->contactService->merge(
            canonical: $canonical,
            duplicate: $duplicate,
            adminId: Auth::id()
        );

        return redirect()->route('admin.contacts.show', $canonical->id)
            ->with('success', "Duplicate contact {$duplicate->email} successfully merged into {$canonical->email}.");
    }

    public function updateNotes(Request $request, Contact $contact): RedirectResponse
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $prevNotes = $contact->notes;
        $contact->update(['notes' => $validated['notes']]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'contact_notes_updated',
            'entity_type' => Contact::class,
            'entity_id' => $contact->id,
            'previous_data' => ['notes' => $prevNotes],
            'new_data' => ['notes' => $validated['notes']],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Student notes updated successfully.');
    }
}
