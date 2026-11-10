<html lang="{{ app()->getLocale() }}">
<body>
<p>{{ __('You requested optional authenticator protection for your student account.') }}</p>
<p><a href="{{ $verificationUrl }}">{{ __('Continue authenticator setup in the browser where you requested it') }}</a>. {{ __('This link expires in ten minutes.') }}</p>
<p>{{ __('If you did not request this, do not approve it. Contact your tutor.') }}</p>
</body>
</html>
