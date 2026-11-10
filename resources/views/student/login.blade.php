@extends('layouts.student', ['title' => 'Sign In', 'studentPresentation' => 'sign-in'])

@section('content')
<div class="student-sign-in" data-student-auth-screen>
    <div class="student-sign-in-grid">
        <header class="student-sign-in-editorial">
            <a href="{{ route('home') }}" class="student-sign-in-brand">
                <img class="student-sign-in-logo-horizontal" src="{{ asset('assets/brand/wordmark-ink-520.webp') }}" width="520" height="82" alt="Arabic with Abdallah">
                <img class="student-sign-in-logo-stacked" src="{{ asset('assets/brand/wordmark-stacked-teal-1200.webp') }}" width="1200" height="537" alt="Arabic with Abdallah">
            </a>
            <p class="student-sign-in-context">{{ __('Student Portal') }}</p>
            <h1>{{ __('Welcome Back') }}</h1>
            <p class="student-sign-in-intro">{{ __('Access your sessions, learning materials, progress, notes, and everything connected to your Arabic learning.') }}</p>
            <div class="student-sign-in-greeting"><p lang="ar" dir="rtl">أَهْلًا وَسَهْلًا</p><p>{{ __('Welcome') }}</p></div>
        </header>
        <section class="student-sign-in-form-region" aria-labelledby="sign-in-title">
            <h2 id="sign-in-title">{{ __('Sign In') }}</h2>
            <p id="sign-in-instructions">{{ __('Enter your date of birth and at least two of the details below as they are recorded on your student profile.') }}</p>
            @if($errors->any())
                <div id="sign-in-errors" class="student-auth-error" role="alert" tabindex="-1" data-student-error-summary>
                    <p class="font-semibold">{{ __('Please review your sign-in') }}</p>
                    <ul>@foreach($errors->all() as $error)<li>{{ __($error) }}</li>@endforeach</ul>
                </div>
            @endif
            <form method="POST" action="{{ route('student.login.submit') }}" class="student-auth-form" data-student-sign-in aria-describedby="sign-in-instructions" aria-busy="false">
                @csrf
                <div><label for="date_of_birth">{{ __('Date of birth') }}</label><input id="date_of_birth" name="date_of_birth" type="date" required @if($errors->has('date_of_birth')) aria-invalid="true" aria-describedby="sign-in-errors" @endif></div>
                <fieldset data-student-identity-group aria-describedby="identity-instructions">
                    <legend>{{ __('Your identifying details') }}</legend>
                    <p id="identity-instructions" class="student-auth-hint">{{ __('Provide at least two: name, email address, or phone number.') }}</p>
                    <div><label for="name">{{ __('Full name') }}</label><input id="name" name="name" type="text" maxlength="255" autocomplete="name" @if($errors->has('name')) aria-invalid="true" aria-describedby="sign-in-errors" @endif></div>
                    <div><label for="email">{{ __('Email address') }}</label><input id="email" name="email" type="email" maxlength="255" autocomplete="email" @if($errors->has('email')) aria-invalid="true" aria-describedby="sign-in-errors" @endif></div>
                    <div><label for="phone">{{ __('Phone number') }}</label><input id="phone" name="phone" type="tel" maxlength="40" autocomplete="tel" @if($errors->has('phone')) aria-invalid="true" aria-describedby="sign-in-errors" @endif></div>
                </fieldset>
                <div id="sign-in-insufficient" class="student-auth-error" role="alert" tabindex="-1" data-student-insufficient hidden><p class="font-semibold">{{ __('A Little More Information Is Needed') }}</p><p>{{ __('Enter your date of birth and at least two of your name, email address, or phone number.') }}</p></div>
                <button type="submit" class="student-button student-button-primary" data-student-sign-in-submit>{{ __('Sign In') }}</button>
            </form>
            <p class="student-sign-in-help">{{ __('If you\'re unable to access your account, contact Abdallah for help.') }}</p>
        </section>
    </div>
</div>
@endsection
