<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password | {{ config('business.site_name') }} Admin</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full flex items-center justify-center p-4 sm:p-6 bg-radial from-slate-900 to-slate-950 font-sans text-slate-100">
    <div class="w-full max-w-md">
        
        <div class="text-center mb-8">
            <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-br from-amber-500 to-amber-700 items-center justify-center text-slate-950 text-2xl font-bold font-serif shadow-lg shadow-amber-500/20 mb-4">
                <span aria-hidden="true">A</span>
            </div>
            <h1 class="text-2xl font-bold font-serif tracking-tight text-white">Reset Password</h1>
            <p class="text-sm text-slate-400 mt-1">Enter your administrator email to receive a recovery link</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8">
            
            @if(session('status'))
                <div class="mb-5 p-3 rounded-lg bg-emerald-950/50 border border-emerald-800 text-emerald-300 text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    <span>{{ session('status') }}</span>
                </div>
            @endif

            <form action="{{ route('admin.password.email') }}" method="POST" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Administrator Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                           class="w-full px-4 py-2.5 bg-slate-950 border @error('email') border-red-500 @else border-slate-700 @enderror rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-colors text-sm"
                           placeholder="admin@example.com">
                    @error('email')
                        <p class="text-xs text-red-400 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" 
                        class="w-full py-3 px-4 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-bold rounded-xl shadow-lg shadow-amber-500/20 hover:shadow-amber-500/30 transition-all text-sm font-serif">
                    Send Password Recovery Link
                </button>
            </form>

            <div class="mt-6 text-center">
                <a href="{{ route('admin.login') }}" class="text-xs text-amber-400 hover:text-amber-300 font-semibold transition-colors">
                    &larr; Return to Sign In
                </a>
            </div>
        </div>

    </div>
</body>
</html>
