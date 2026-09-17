<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Booking\Models\Booking;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Resources\Models\Resource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SearchController extends Controller
{
    public function search(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $contacts = collect();
        $bookings = collect();
        $resources = collect();

        if (strlen($q) >= 2) {
            $contacts = Contact::query()
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('display_email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->take(10)
                ->get();

            $bookings = Booking::query()
                ->with('contact')
                ->where('confirmation_token', 'like', "%{$q}%")
                ->orWhere('notes', 'like', "%{$q}%")
                ->orWhereHas('contact', function ($cq) use ($q) {
                    $cq->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                })
                ->take(10)
                ->get();

            $resources = Resource::query()
                ->where('title', 'like', "%{$q}%")
                ->orWhere('slug', 'like', "%{$q}%")
                ->take(10)
                ->get();
        }

        return view('admin.search.index', [
            'title' => "Search Results for '{$q}'",
            'query' => $q,
            'contacts' => $contacts,
            'bookings' => $bookings,
            'resources' => $resources,
        ]);
    }
}
