@extends('layouts.student', ['title' => 'Verify Sign In'])
@section('content')
<section class="student-auth-focused student-surface" data-student-auth-screen>
    <h1>{{ __('Verify your sign-in') }}</h1>
    <p id="verification-instructions" class="mt-3">{{ __('Enter your authenticator code or one unused recovery code. Use one alternative only.') }}</p>
    @if($errors->any())<div id="verification-errors" role="alert" tabindex="-1" class="student-auth-error" data-student-error-summary><ul>@foreach($errors->all() as $error)<li>{{ __($error) }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('student.two-factor.verify') }}" class="student-auth-form" aria-describedby="verification-instructions">@csrf
        <div><label for="code">{{ __('Authenticator code') }}</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" @if($errors->has('code')) aria-invalid="true" aria-describedby="verification-errors" @endif></div>
        <div><label for="recovery-code">{{ __('Or recovery code') }}</label><input id="recovery-code" name="recovery_code" autocomplete="off" maxlength="100" @if($errors->has('recovery_code')) aria-invalid="true" aria-describedby="verification-errors" @endif></div>
        <button class="student-button student-button-primary">{{ __('Verify sign-in') }}</button>
    </form>
</section>
@endsection
