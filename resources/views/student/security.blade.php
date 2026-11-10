@extends('layouts.student', ['title' => 'Account Security'])
@section('content')
<div data-student-auth-screen>
<h1 class="text-3xl font-semibold text-nile-900">{{ __('Account Security') }}</h1>
<p class="mt-3 text-stone-600">{{ __('Authenticator protection is optional and is off unless you enable it. Once enabled, your personal details alone cannot sign you in.') }}</p>
@if($errors->any())<div id="security-errors" role="alert" tabindex="-1" class="student-auth-error" data-student-error-summary><ul>@foreach($errors->all() as $error)<li>{{ __($error) }}</li>@endforeach</ul></div>@endif
@if(session('success'))<p role="status" class="mt-4 text-green-800">{{ __(session('success')) }}</p>@endif
<section class="student-surface mt-6 space-y-4">
<h2 class="text-xl font-semibold">{{ __('Authenticator:') }} {{ __($student->requiresTwoFactor() ? 'Enabled' : 'Off') }}</h2>
@if(isset($setup))
<p>{{ __('Scan this code using your authenticator app. Setup expires after ten minutes.') }}</p>
<div role="img" aria-label="{{ __('Authenticator setup QR code') }}">{!! $setup['qr'] !!}</div>
<p>{{ __('Manual key:') }} <code>{{ $setup['secret'] }}</code></p>
<form method="POST" action="{{ route('student.security.confirm') }}" class="student-auth-form student-auth-focused">@csrf
<label for="setup-code">{{ __('Authenticator code') }}</label><input id="setup-code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required @if($errors->has('code')) aria-invalid="true" aria-describedby="security-errors" @endif>
<button class="student-button student-button-primary">{{ __('Confirm setup') }}</button></form>
@endif
@if(isset($recoveryCodes) && count($recoveryCodes))
<p>{{ __('Save these recovery codes privately. They are shown once and each works once.') }}</p>
<ul class="space-y-2 font-mono">@foreach($recoveryCodes as $recoveryCode)<li>{{ $recoveryCode }}</li>@endforeach</ul>
@endif
@if(! $student->requiresTwoFactor())
<p>{{ __('Begin setup, then follow the confirmation sent to your primary email in this browser.') }}</p>
<form method="POST" action="{{ route('student.security.enroll') }}">@csrf<button class="student-button student-button-primary">{{ __('Set up authenticator') }}</button></form>
@else
<p>{{ __('If you lose your authenticator and recovery codes, contact your tutor for an identity-checked reset.') }}</p>
<form method="POST" action="{{ route('student.security.change') }}" class="student-auth-form student-auth-focused" aria-describedby="security-factor-instructions">@csrf
<p id="security-factor-instructions">{{ __('Enter an authenticator code or one unused recovery code. Use one alternative only.') }}</p>
<label for="code">{{ __('Authenticator code') }}</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" @if($errors->has('code')) aria-invalid="true" aria-describedby="security-errors" @endif>
<label for="recovery-code">{{ __('Or one unused recovery code') }}</label><input id="recovery-code" name="recovery_code" autocomplete="off" maxlength="100" @if($errors->has('recovery_code')) aria-invalid="true" aria-describedby="security-errors" @endif>
<button name="action" value="regenerate" class="student-button student-button-primary">{{ __('Replace recovery codes') }}</button>
<button name="action" value="disable" class="student-button border-red-800 text-red-800">{{ __('Disable authenticator') }}</button></form>
@endif
</section>
</div>
@endsection
