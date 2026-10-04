@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-slate-900">Account Security</h1>
        <p class="mt-2 text-slate-600">Optional two-factor authentication for your Super Admin account.</p>
    </div>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold">Two-Factor Authentication</h2>
            <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $enabled ? 'Enabled' : 'Disabled' }}</span>
        </div>
        <p class="mt-3 text-sm text-slate-600">Use a standards-compatible authenticator such as Aegis, 2FAS or FreeOTP. When enabled, every new sign-in requires a second factor. Keep me signed in does not bypass this check.</p>
        @if(isset($recoveryCodes))
            <div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-5">
                <h3 class="font-semibold text-amber-950">Save your recovery codes now</h3>
                <p class="mt-2 text-sm text-amber-900">These codes are shown only once. Store them somewhere private. Each code works once; a new set replaces all previous codes.</p>
                <ul class="mt-4 grid gap-2 font-mono text-sm text-slate-900 sm:grid-cols-2">@foreach($recoveryCodes as $recoveryCode)<li class="break-all rounded-lg bg-white px-3 py-2">{{ $recoveryCode }}</li>@endforeach</ul>
                <a href="{{ route('admin.security.show') }}" class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 py-3 font-semibold text-white">I have saved my codes</a>
            </div>
        @endif
        @if(isset($setup))
            <div class="mt-6 space-y-4">
                <h3 class="font-semibold">Set up your authenticator</h3>
                <p class="text-sm text-slate-600">Scan this QR code or enter the manual key. This setup expires in ten minutes. {{ $enabled ? 'Your current authenticator stays active until you confirm the replacement.' : 'Two-factor authentication stays disabled until you confirm a code.' }}</p>
                <div role="img" aria-label="Authenticator setup QR code" class="w-fit max-w-full rounded-xl border border-slate-200 bg-white p-2">{!! $setup['qr'] !!}</div>
                <div><span class="text-sm font-medium">Manual setup key</span><code class="mt-2 block break-all rounded-xl bg-slate-100 p-4 text-sm">{{ $setup['secret'] }}</code></div>
            </div>
        @endif
        @if($pending)
            <form method="POST" action="{{ route('admin.security.confirm') }}" class="mt-6 space-y-4">
                @csrf
                <label for="confirmation-code" class="block text-sm font-medium">Confirm authenticator code
                    <input id="confirmation-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-amber-500">
                </label>
                <button class="min-h-11 rounded-xl bg-amber-600 px-5 py-3 font-semibold text-white">Confirm setup</button>
            </form>
        @endif
        @if(! $enabled)
            <form method="POST" action="{{ route('admin.security.enroll') }}" class="mt-6 space-y-4">
                @csrf
                <label for="enroll-password" class="block text-sm font-medium">Confirm your current password
                    <input id="enroll-password" name="password" type="password" required maxlength="1024" autocomplete="current-password" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-4 py-3 focus:ring-2 focus:ring-amber-500">
                </label>
                <button class="min-h-11 rounded-xl bg-slate-900 px-5 py-3 font-semibold text-white">{{ $pending ? 'Restart setup with a new key' : 'Set up two-factor authentication' }}</button>
            </form>
        @else
            <div class="mt-6 space-y-4">
                @foreach(['regenerate' => 'Regenerate Recovery Codes', 'reset' => 'Replace Authenticator', 'disable' => 'Disable Two-Factor Authentication'] as $action => $label)
                    <details class="rounded-xl border border-slate-200 p-4">
                        <summary class="min-h-11 cursor-pointer py-3 font-semibold">{{ $label }}</summary>
                        <form method="POST" action="{{ route('admin.security.'.$action) }}" class="mt-4 space-y-4">
                            @csrf
                            @if($action === 'disable') @method('DELETE') @endif
                            <x-two-factor-verification-fields :prefix="$action" />
                            <button class="min-h-11 rounded-xl px-5 py-3 font-semibold text-white {{ $action === 'disable' ? 'bg-red-700' : 'bg-slate-900' }}">{{ $label }}</button>
                        </form>
                    </details>
                @endforeach
            </div>
        @endif
    </section>
</div>
@endsection
