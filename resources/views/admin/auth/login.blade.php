<!DOCTYPE html>
<html lang="en" data-admin-appearance="light" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | Arabic with Abdallah Operations Console</title>
    @include('components.admin.appearance-init')
    @vite(['resources/css/app.css', 'resources/css/admin-quiet-operations.css'])
</head>
<body class="aq-sign-in">
    <main class="aq-auth" aria-labelledby="sign-in-heading">
        <header class="aq-auth-brand">
            <img class="aq-logo-light" src="{{ asset('assets/brand/wordmark-teal-780.webp') }}" width="780" height="123" alt="Arabic with Abdallah">
            <img class="aq-logo-dark" src="{{ asset('assets/brand/wordmark-cream-780.webp') }}" width="780" height="123" alt="Arabic with Abdallah">
            <p>Operations Console</p>
        </header>
        <section class="aq-auth-card">
            <h1 id="sign-in-heading">Sign In</h1>
            @if(session('error'))
                <div class="aq-auth-feedback aq-auth-error" role="alert">{{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="aq-auth-feedback aq-auth-success" role="status">{{ session('success') }}</div>
            @endif
            <form action="{{ route('admin.login.submit') }}" method="POST" class="aq-auth-form">
                @csrf
                <div>
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                           aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @error('email') aria-describedby="email-error" @enderror>
                    @error('email')
                        <p id="email-error" class="aq-auth-field-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password"
                           aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" @error('password') aria-describedby="password-error" @enderror>
                    @error('password')
                        <p id="password-error" class="aq-auth-field-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <label for="remember" class="aq-auth-remember">
                    <input type="checkbox" id="remember" name="remember" value="1" @checked(old('remember'))>
                    <span>Keep me signed in</span>
                </label>
                <button type="submit" class="aq-action-primary">Sign In to Console</button>
            </form>
            <a class="aq-auth-recovery" href="{{ route('admin.password.request') }}">Forgot password?</a>
        </section>
        <p class="aq-auth-copyright">&copy; {{ date('Y') }} Arabic with Abdallah</p>
    </main>
</body>
</html>
