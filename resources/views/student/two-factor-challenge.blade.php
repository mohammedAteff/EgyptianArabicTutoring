@extends('layouts.student', ['title' => 'Verify Sign In'])
@section('content')
<h1 class="text-3xl font-semibold text-nile-900">Verify your sign-in</h1>
<p class="mt-3">Enter your authenticator code or one unused recovery code.</p>
@if($errors->any())<p role="alert" class="mt-4 text-red-800">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ route('student.two-factor.verify') }}" class="mt-6 space-y-4">@csrf
<label for="code">Authenticator code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="block rounded-lg border p-3">
<label for="recovery-code">Or recovery code</label><input id="recovery-code" name="recovery_code" autocomplete="off" maxlength="100" class="block rounded-lg border p-3">
<button class="rounded-lg bg-nile-800 p-3 text-white">Verify sign-in</button></form>
@endsection
