<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set New Password | {{ config('business.site_name') }} Admin</title>

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
            <h1 class="text-2xl font-bold font-serif tracking-tight text-white">Create New Password</h1>
            <p class="text-sm text-slate-400 mt-1">Please enter your new administrator security credential</p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8">
            <form action="{{ route('admin.password.update') }}" method="POST" class="space-y-5">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $email) }}" required
                           class="w-full px-4 py-2.5 bg-slate-950 border @error('email') border-red-500 @else border-slate-700 @enderror rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-colors text-sm font-mono">
                    @error('email')
                        <p class="text-xs text-red-400 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">New Password</label>
                    <input type="password" id="password" name="password" required autofocus
                           class="w-full px-4 py-2.5 bg-slate-950 border @error('password') border-red-500 @else border-slate-700 @enderror rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition-colors text-sm"
                           placeholder="At least 8 characters">
                    @error('password')
                        <p class="text-xs text-red-400 mt-1.5 font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required
                           class="w-full px-4 py-2.5 bg-slate-950 border rounded-xl text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500 border-slate-700 transition-colors text-sm"
                           placeholder="Repeat new password">
                </div>

                <button type="submit" 
                        class="w-full py-3 px-4 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-bold rounded-xl shadow-lg shadow-amber-500/20 hover:shadow-amber-500/30 transition-all text-sm font-serif">
                    Reset Password
                </button>
            </form>
        </div>

    </div>
</body>
</html>
