@extends('layouts.student', ['title' => 'Account Security'])
@section('content')
<h1 class="text-3xl font-semibold text-nile-900">Account Security</h1>
<p class="mt-3 text-stone-600">Authenticator protection is optional and is off unless you enable it. Once enabled, your personal details alone cannot sign you in.</p>
@if($errors->any())<p role="alert" class="mt-4 text-red-800">{{ $errors->first() }}</p>@endif
@if(session('success'))<p role="status" class="mt-4 text-green-800">{{ session('success') }}</p>@endif
<section class="mt-6 space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
<h2 class="text-xl font-semibold">Authenticator: {{ $student->requiresTwoFactor() ? 'Enabled' : 'Off' }}</h2>
@if(isset($setup))
<p>Scan this code using your authenticator app. Setup expires after ten minutes.</p>
<div role="img" aria-label="Authenticator setup QR code">{!! $setup['qr'] !!}</div>
<p>Manual key: <code>{{ $setup['secret'] }}</code></p>
<form method="POST" action="{{ route('student.security.confirm') }}">@csrf
<label for="setup-code">Authenticator code</label><input id="setup-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required class="m-2 rounded-lg border p-3">
<button class="rounded-lg bg-nile-800 p-3 text-white">Confirm setup</button></form>
@endif
@if(isset($recoveryCodes) && count($recoveryCodes))
<p>Save these recovery codes privately. They are shown once and each works once.</p>
<ul class="space-y-2 font-mono">@foreach($recoveryCodes as $recoveryCode)<li>{{ $recoveryCode }}</li>@endforeach</ul>
@endif
@if(! $student->requiresTwoFactor())
<p>Begin setup, then follow the confirmation sent to your primary email in this browser.</p>
<form method="POST" action="{{ route('student.security.enroll') }}">@csrf<button class="rounded-lg bg-nile-800 p-3 text-white">Set up authenticator</button></form>
@else
<p>If you lose your authenticator and recovery codes, contact your tutor for an identity-checked reset.</p>
<form method="POST" action="{{ route('student.security.change') }}" class="space-y-3">@csrf
<label for="code">Authenticator code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" class="block rounded-lg border p-3">
<label for="recovery-code">Or one unused recovery code</label><input id="recovery-code" name="recovery_code" autocomplete="off" maxlength="100" class="block rounded-lg border p-3">
<button name="action" value="regenerate" class="rounded-lg bg-nile-800 p-3 text-white">Replace recovery codes</button>
<button name="action" value="disable" class="rounded-lg bg-red-800 p-3 text-white">Disable authenticator</button></form>
@endif
</section>
@endsection
