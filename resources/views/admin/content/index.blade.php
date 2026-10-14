@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-6xl space-y-6"><h1 class="font-serif text-3xl font-bold">Social Channels</h1><p class="text-sm text-slate-600">Manage official social links, visibility and footer ordering.</p>
    <!-- Social Channels & WhatsApp Support (Spec 50 & 51) -->
        <aside class="min-w-0 bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        <div>
            <h2 class="text-lg font-bold font-serif text-slate-900">Official Social Media & WhatsApp Channels</h2>
            <p class="text-xs text-slate-500">Displayed in footer and contact sections. WhatsApp includes pre-filled conversational intent messages.</p>
        </div>

        <form action="{{ route('admin.content.social.update') }}" method="POST" class="space-y-6">
            @csrf

            <div class="space-y-4">
                @foreach($socials as $soc)
                    <div data-social-channel-card="{{ $soc->id }}" class="min-w-0 bg-slate-50 p-4 sm:p-5 rounded-2xl border border-slate-200 space-y-4">
                        <div class="flex items-center justify-between gap-3">
                            <label class="flex items-center gap-2 cursor-pointer text-sm font-bold text-slate-900 capitalize">
                                <input type="checkbox" name="socials[{{ $soc->id }}][enabled]" value="1" {{ $soc->enabled ? 'checked' : '' }}
                                       data-social-toggle data-url="{{ route('admin.content.social.toggle', $soc) }}"
                                       class="w-4 h-4 rounded-sm border-slate-300 text-amber-600 focus:ring-amber-500">
                                <span>{{ $soc->platform }}</span>
                            </label>
                            <span data-toggle-status="{{ $soc->id }}" class="text-[10px] text-slate-500" aria-live="polite"></span>
                        </div>

                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">
                                    {{ $soc->platform === 'whatsapp' ? 'Phone Number (Intl)' : 'Profile URL' }}
                                </label>
                                <input type="text" name="socials[{{ $soc->id }}][url_or_phone]" value="{{ $soc->url_or_phone }}" required
                                       class="w-full min-w-0 px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                            </div>

                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Display Label</label>
                                <input type="text" name="socials[{{ $soc->id }}][label]" value="{{ $soc->label }}" required maxlength="100"
                                       class="w-full min-w-0 px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            </div>

                            @if($soc->platform === 'whatsapp')
                                <div>
                                    <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Default Pre-filled Message</label>
                                    <textarea name="socials[{{ $soc->id }}][default_message]" rows="3" maxlength="1000"
                                              class="w-full min-w-0 px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ $soc->default_message }}</textarea>
                                </div>
                            @endif
                            <div>
                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-slate-400 mb-1">Display order</label>
                                <input type="number" min="0" max="10000" name="socials[{{ $soc->id }}][sort_order]" value="{{ $soc->sort_order }}" required class="w-full min-w-0 px-3 py-2 text-sm bg-white border border-slate-300 rounded-lg">
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end">
                <button type="submit" class="w-full sm:w-auto px-5 py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-colors font-serif shadow-xs">
                    Save Social Channels
                </button>
            </div>
        </form>
        <form x-data="{ platform: 'youtube' }" action="{{ route('admin.content.social.store') }}" method="POST" class="space-y-4 border-t border-slate-100 pt-5">
            @csrf
            <div class="grid grid-cols-1 gap-4">
                <label class="block text-xs font-semibold text-slate-700">Platform
                    <select name="platform" x-model="platform" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach(\App\Domains\CMS\Models\SocialLink::PLATFORMS as $platform)
                            <option value="{{ $platform }}">{{ ucfirst($platform) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-semibold text-slate-700">Display label
                    <input name="label" required maxlength="100" class="mt-1 w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label class="block text-xs font-semibold text-slate-700"><span x-text="platform === 'whatsapp' ? 'International phone number' : 'Profile URL'"></span>
                    <input name="url_or_phone" required maxlength="2048" placeholder="https://…" class="mt-1 w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </label>
                <label x-show="platform === 'whatsapp'" x-cloak class="block text-xs font-semibold text-slate-700">Default pre-filled WhatsApp message
                    <textarea name="default_message" rows="3" maxlength="1000" class="mt-1 w-full min-w-0 rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                </label>
            </div>
            <button type="submit" class="w-full sm:w-auto rounded-lg border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Add channel</button>
        </form>
        </aside>
    </div>

    <script>
        document.addEventListener('change', async (event) => {
            const toggle = event.target.closest('[data-social-toggle]');
            if (!toggle) return;
            const desired = toggle.checked;
            const status = document.querySelector(`[data-toggle-status="${toggle.name.match(/socials\[(\d+)\]/)?.[1]}"]`);
            toggle.disabled = true;
            if (status) status.textContent = 'Saving…';
            try {
                const response = await fetch(toggle.dataset.url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: new URLSearchParams({ enabled: desired ? '1' : '0' }),
                });
                if (!response.ok) throw new Error('Toggle failed');
                const result = await response.json();
                toggle.checked = Boolean(result.enabled);
                if (status) status.textContent = 'Saved';
            } catch (error) {
                toggle.checked = !desired;
                if (status) status.textContent = 'Could not save';
            } finally {
                toggle.disabled = false;
            }
        });
    </script>


</div>
@endsection
