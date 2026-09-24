@extends('layouts.student', ['title' => 'Student sign in'])

@section('content')
<div class="mx-auto max-w-xl rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-10">
    <h1 class="text-3xl font-semibold tracking-tight text-nile-900">Student sign in</h1>
    <p class="mt-3 text-sm text-stone-600">Enter your date of birth and at least two of your name, email, and phone number exactly as recorded with your tutor.</p>
    @if($errors->any())
        <p role="alert" class="mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">The provided student details could not be verified.</p>
    @endif
    <form method="POST" action="{{ route('student.login.submit') }}" class="mt-7 space-y-5">
        @csrf
        <div><label for="date_of_birth" class="block text-sm font-medium">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <div><label for="name" class="block text-sm font-medium">Full name</label><input id="name" name="name" type="text" autocomplete="name" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <div><label for="email" class="block text-sm font-medium">Email</label><input id="email" name="email" type="email" autocomplete="email" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <div><label for="phone_country" class="block text-sm font-medium">Phone country code (for national numbers)</label><input id="phone_country" name="phone_country" type="text" maxlength="2" placeholder="EG" class="mt-1 w-28 rounded-lg border border-stone-300 px-3 py-2"><p class="mt-1 text-xs text-stone-500">Or enter an international number beginning with +.</p></div>
        <div><label for="phone" class="block text-sm font-medium">Phone</label><input id="phone" name="phone" type="tel" autocomplete="tel" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <button type="submit" class="w-full rounded-lg bg-nile-900 px-5 py-3 font-semibold text-white hover:bg-nile-800">Sign in</button>
    </form>
</div>
@endsection
