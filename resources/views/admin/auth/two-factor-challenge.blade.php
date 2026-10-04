<!DOCTYPE html>
<html lang="en" class="min-h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify your sign-in | {{ config('business.site_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-950 p-4 font-sans text-slate-100 sm:p-6">
    <main class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl sm:p-8">
        <h1 class="text-2xl font-bold">Verify your sign-in</h1>
        <p class="mt-3 text-sm text-slate-300">Enter a new six-digit code from your authenticator. This sign-in challenge expires in ten minutes.</p>
        @if($errors->any())<div role="alert" class="mt-5 rounded-xl border border-red-700 bg-red-950 p-4 text-sm text-red-200">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
        <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="mt-6 space-y-4">
            @csrf
            <label for="code" class="block text-sm font-medium">Authenticator code</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus class="min-h-11 w-full rounded-xl border border-slate-600 bg-slate-950 px-4 py-3 text-lg tracking-widest focus:outline-none focus:ring-2 focus:ring-amber-500">
            <button class="min-h-11 w-full rounded-xl bg-amber-600 px-5 py-3 font-semibold text-white">Verify authenticator code</button>
        </form>
        <details class="mt-6 border-t border-slate-700 pt-4">
            <summary class="min-h-11 cursor-pointer py-3 text-sm font-medium text-amber-300">Use a recovery code instead</summary>
            <form method="POST" action="{{ route('admin.two-factor.verify') }}" class="mt-4 space-y-4">
                @csrf
                <label for="recovery-code" class="block text-sm font-medium">One unused recovery code</label>
                <input id="recovery-code" name="recovery_code" type="text" required maxlength="100" autocomplete="off" class="min-h-11 w-full rounded-xl border border-slate-600 bg-slate-950 px-4 py-3 focus:outline-none focus:ring-2 focus:ring-amber-500">
                <button class="min-h-11 w-full rounded-xl bg-slate-700 px-5 py-3 font-semibold">Verify recovery code</button>
            </form>
        </details>
        <form method="POST" action="{{ route('admin.logout') }}" class="mt-6">@csrf<button class="min-h-11 w-full rounded-xl border border-slate-600 px-4 py-3 text-sm">Cancel and return to sign-in</button></form>
    </main>
</body>
</html>
