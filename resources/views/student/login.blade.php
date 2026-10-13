@extends('layouts.student', ['title' => 'Sign In'])

@section('content')
<div class="mx-auto max-w-xl rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-10">
    <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">Student Portal</p>
    <h1 class="mt-2 text-3xl font-semibold tracking-tight text-nile-900">Welcome Back</h1>
    <p class="mt-3 text-stone-600">Access your sessions, learning materials, progress, notes, and everything connected to your Arabic learning.</p>
    <p class="mt-3 text-sm text-stone-600">Enter your date of birth and at least two of the details below as they are recorded on your student profile.</p>
    @if($errors->any())
        <div role="alert" class="mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800"><p class="font-semibold">We Couldn't Match Those Details</p><p class="mt-1">Check the information you entered and try again.</p><p class="mt-1">If you're still unable to access your account, contact Abdallah for help.</p></div>
    @endif
    <form method="POST" action="{{ route('student.login.submit') }}" class="mt-7 space-y-5" x-data="{ insufficient: false }" x-on:submit="insufficient = [$refs.name.value, $refs.email.value, $refs.phone.value].filter(value => value.trim()).length &lt; 2; if (insufficient) $event.preventDefault()">
        @csrf
        <div><label for="date_of_birth" class="block text-sm font-medium">Date of birth</label><input id="date_of_birth" name="date_of_birth" type="date" required class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <div><label for="name" class="block text-sm font-medium">Full name</label><input id="name" name="name" type="text" maxlength="255" placeholder="Your name as registered" autocomplete="name" x-ref="name" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <div><label for="email" class="block text-sm font-medium">Email address</label><input id="email" name="email" type="email" maxlength="255" placeholder="Your email address" autocomplete="email" x-ref="email" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <div><label for="phone" class="block text-sm font-medium">Phone number</label><input id="phone" name="phone" type="tel" maxlength="40" placeholder="Your phone number" autocomplete="tel" x-ref="phone" class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2"></div>
        <div x-show="insufficient" x-cloak role="alert" class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900"><p class="font-semibold">A Little More Information Is Needed</p><p>Enter your date of birth and at least two of your name, email address, or phone number.</p></div>
        <button type="submit" class="w-full rounded-lg bg-nile-900 px-5 py-3 font-semibold text-white hover:bg-nile-800">Sign In</button>
    </form>
</div>
@endsection
