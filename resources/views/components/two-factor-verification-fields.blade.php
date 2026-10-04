@props(['prefix'])
<div class="space-y-4">
    <label for="{{ $prefix }}-password" class="block text-sm font-medium">Current password
        <input id="{{ $prefix }}-password" name="password" type="password" required maxlength="1024" autocomplete="current-password" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 focus:ring-2 focus:ring-amber-500">
    </label>
    <label for="{{ $prefix }}-code" class="block text-sm font-medium">Authenticator code
        <input id="{{ $prefix }}-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" aria-describedby="{{ $prefix }}-help" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 focus:ring-2 focus:ring-amber-500">
    </label>
    <p id="{{ $prefix }}-help" class="text-sm text-slate-600">Enter a new six-digit authenticator code, or one unused recovery code below. Use only one factor.</p>
    <label for="{{ $prefix }}-recovery" class="block text-sm font-medium">Recovery code (alternative)
        <input id="{{ $prefix }}-recovery" name="recovery_code" type="text" maxlength="100" autocomplete="off" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-slate-900 focus:ring-2 focus:ring-amber-500">
    </label>
</div>
