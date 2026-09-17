<div x-data="mediaPickerModal()"
     x-show="isOpen"
     x-cloak
     @open-media-picker.window="open($event.detail.targetInputId, $event.detail.targetPreviewId)"
     class="fixed inset-0 z-50 overflow-y-auto"
     style="display: none;">
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" @click="close()"></div>
    <div class="relative min-h-screen flex items-center justify-center p-4">
        <div class="relative bg-white rounded-3xl shadow-2xl max-w-3xl w-full p-6 space-y-6 border border-slate-200">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div>
                    <h3 class="text-lg font-bold font-serif text-slate-900">Media Library Asset Picker</h3>
                    <p class="text-xs text-slate-500">Select an asset from your library to insert into the form.</p>
                </div>
                <button type="button" @click="close()" class="p-2 text-slate-400 hover:text-slate-700 rounded-xl">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Search -->
            <div>
                <input type="text" x-model="search" placeholder="Filter by filename..." class="w-full px-4 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-amber-500">
            </div>

            <!-- Media Grid -->
            <div class="max-h-96 overflow-y-auto grid grid-cols-2 sm:grid-cols-4 gap-4 p-1">
                <template x-for="item in filteredMedia" :key="item.id">
                    <div @click="select(item)" class="group cursor-pointer rounded-2xl border border-slate-200 hover:border-amber-500 hover:ring-2 hover:ring-amber-400/20 p-2 text-center transition-all bg-slate-50 hover:bg-white flex flex-col items-center">
                        <div class="h-24 w-full rounded-xl overflow-hidden bg-slate-200 flex items-center justify-center mb-2">
                            <template x-if="item.mime_type && item.mime_type.startsWith('image/')">
                                <img :src="item.url" :alt="item.filename" class="h-full w-full object-cover">
                            </template>
                            <template x-if="!item.mime_type || !item.mime_type.startsWith('image/')">
                                <span class="text-[10px] font-mono text-slate-500 uppercase" x-text="item.mime_type ? item.mime_type.split('/')[1] : 'FILE'"></span>
                            </template>
                        </div>
                        <span class="text-xs font-semibold text-slate-800 truncate w-full group-hover:text-amber-700" x-text="item.filename"></span>
                        <span class="text-[10px] text-slate-400 font-mono" x-text="item.path"></span>
                    </div>
                </template>
                <template x-if="filteredMedia.length === 0">
                    <div class="col-span-full py-12 text-center text-xs text-slate-400">
                        No matching media assets found.
                    </div>
                </template>
            </div>

            <div class="flex justify-between items-center pt-4 border-t border-slate-100">
                <a href="{{ route('admin.media.index') }}" target="_blank" class="text-xs text-amber-600 hover:underline">Manage & Upload Media &rarr;</a>
                <button type="button" @click="close()" class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
function mediaPickerModal() {
    return {
        isOpen: false,
        targetInputId: null,
        targetPreviewId: null,
        search: '',
        media: [],
        async open(inputId, previewId) {
            this.targetInputId = inputId;
            this.targetPreviewId = previewId;
            this.isOpen = true;
            if (this.media.length === 0) {
                try {
                    const res = await fetch('{{ route('admin.media.picker') }}');
                    this.media = await res.json();
                } catch (e) {
                    console.error('Failed to load media assets', e);
                }
            }
        },
        close() {
            this.isOpen = false;
        },
        get filteredMedia() {
            if (!this.search.trim()) return this.media;
            const q = this.search.toLowerCase();
            return this.media.filter(m => m.filename.toLowerCase().includes(q) || m.path.toLowerCase().includes(q));
        },
        select(item) {
            if (this.targetInputId) {
                const el = document.getElementById(this.targetInputId);
                if (el) {
                    el.value = item.path;
                    el.dispatchEvent(new Event('input', { bubbles: true }));
                    el.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
            if (this.targetPreviewId) {
                const preview = document.getElementById(this.targetPreviewId);
                if (preview) {
                    preview.src = item.url;
                    preview.classList.remove('hidden');
                }
            }
            this.close();
        }
    };
}

window.openMediaPicker = function(inputId, previewId) {
    window.dispatchEvent(new CustomEvent('open-media-picker', {
        detail: { targetInputId: inputId, targetPreviewId: previewId }
    }));
};
</script>
