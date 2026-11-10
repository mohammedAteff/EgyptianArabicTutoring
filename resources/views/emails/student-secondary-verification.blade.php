<html lang="{{ app()->getLocale() }}">
<body>
<p>{{ __('You requested a secondary email for your Arabic with Abdallah student profile.') }}</p>
<p>{{ __('While signed in to the same student account, use the link below. It expires in 15 minutes and can be used once.') }}</p>
<p><a href="{{ $verificationUrl }}">{{ __('Verify this email') }}</a></p>
<p>{{ __('If you did not request this change, ignore this message.') }}</p>
</body>
</html>
