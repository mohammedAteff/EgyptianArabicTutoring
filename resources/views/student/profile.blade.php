@extends('layouts.student', ['title' => 'Profile'])
@section('content')
<p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Your Account</p>
<h1 class="mt-2 text-3xl font-semibold text-nile-900">Profile</h1>
<p class="mt-3 text-stone-600">Manage the contact information connected to your student profile.</p>
@if(session('success'))<p role="status" class="mt-4 rounded-xl bg-green-50 p-4 text-green-800">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert" class="mt-4 rounded-xl bg-red-50 p-4 text-red-800">{{ $errors->first() }}</div>@endif
<section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6" aria-labelledby="student-information">
    <h2 id="student-information" class="text-xl font-semibold">Student Information</h2>
    <dl class="mt-4 grid gap-4 sm:grid-cols-3">
        <div><dt class="text-sm text-stone-500">Full name</dt><dd class="mt-1 break-words"><bdi>{{ $student->name }}</bdi></dd></div>
        <div><dt class="text-sm text-stone-500">Phone number</dt><dd class="mt-1"><bdi>{{ $student->phone ?: 'Not provided' }}</bdi></dd></div>
        <div><dt class="text-sm text-stone-500">Date of birth</dt><dd class="mt-1">{{ $student->date_of_birth?->format('j F Y') ?? 'Not provided' }}</dd></div>
    </dl>
    <p class="mt-4 text-sm text-stone-500">Your birthday is visible to you and staff only. Other students cannot see it. Contact Abdallah if your recorded student details need updating.</p>
</section>
<section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6" aria-labelledby="email-addresses">
    <h2 id="email-addresses" class="text-xl font-semibold">Email Addresses</h2>
    <p class="mt-2 text-sm text-stone-600">Email addresses connected to your student profile appear here.</p>
    <p class="mt-4 break-all"><bdi>{{ $student->email }}</bdi> <span class="text-sm text-stone-500">· Primary</span></p>
    @foreach($emails as $email)<p class="mt-2 break-all"><bdi>{{ $email->email_normalized }}</bdi> <span class="text-sm text-stone-500">· {{ $email->verified_at ? 'Existing sign-in address' : 'Additional contact address' }}</span></p>@endforeach
    <h3 class="mt-6 font-semibold">Add Another Email Address</h3>
    <p class="mt-2 text-sm text-stone-600">Add another email address to your student profile. Adding an address doesn't change your sign-in details.</p>
    <form method="POST" action="{{ route('student.profile.email.request') }}" class="mt-4 flex flex-wrap items-end gap-3">
        @csrf
        <label class="min-w-0 flex-1 text-sm" for="additional-email">Email address<input id="additional-email" type="email" name="email" required maxlength="255" autocomplete="email" placeholder="name@example.com" value="{{ old('email') }}" class="mt-1 block min-h-11 w-full rounded-xl border border-stone-300 p-3"></label>
        <button class="min-h-11 rounded-xl bg-nile-800 px-5 py-3 font-semibold text-white">Add Email Address</button>
    </form>
</section>
<section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
<a href="{{ route('student.security.show') }}" class="font-semibold underline">Account Security: optional authenticator protection</a>
<form method="POST" action="{{ route('student.profile.timezone') }}" class="mt-4">@csrf @method('PATCH')
<label for="preferred-timezone">Preferred timezone</label><input id="preferred-timezone" name="timezone" value="{{ $student->preferred_timezone }}" required class="m-2 rounded-lg border p-3">
<button class="rounded-lg bg-nile-800 p-3 text-white">Save timezone</button></form></section>
@endsection
