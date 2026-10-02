@extends('layouts.student', ['title' => 'Your profile'])
@section('content')
<h1 class="text-3xl font-semibold text-nile-900">Your profile</h1>
@if(session('success'))<p role="status" class="mt-4 rounded-xl bg-green-50 p-4 text-green-800">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert" class="mt-4 rounded-xl bg-red-50 p-4 text-red-800">{{ $errors->first() }}</div>@endif
<section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6"><h2 class="text-xl font-semibold">Verified email addresses</h2><p class="mt-3">{{ $student->email }} · Primary</p>@foreach($emails as $email)<p class="mt-2">{{ $email->email_normalized }} · Verified secondary</p>@endforeach
<form method="POST" action="{{ route('student.profile.email.request') }}" class="mt-6 flex flex-wrap items-end gap-3">@csrf<label class="flex-1 text-sm">Add a secondary email<input type="email" name="email" required maxlength="255" value="{{ old('email') }}" class="mt-1 block w-full rounded-xl border border-stone-300 p-3"></label><button class="rounded-xl bg-nile-800 px-5 py-3 font-semibold text-white">Send verification</button></form><p class="mt-3 text-sm text-stone-500">A resource request with another email does not verify it or change your login details.</p></section>
@endsection
