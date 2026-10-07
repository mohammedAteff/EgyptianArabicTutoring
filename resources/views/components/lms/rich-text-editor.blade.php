@once
<script>
window.courseRichTextEditor = function () {
    return {
        range: null,
        init() {
            this.$refs.editor.innerHTML = this.$refs.source.value;
            this.$refs.source.closest('form')?.addEventListener('submit', () => this.sync());
        },
        sync() { this.$refs.source.value = this.$refs.editor.innerHTML; this.remember(); },
        remember() {
            const selection = window.getSelection();
            if (selection?.rangeCount && this.$refs.editor.contains(selection.getRangeAt(0).commonAncestorContainer)) {
                this.range = selection.getRangeAt(0).cloneRange();
            }
        },
        currentRange() {
            const selection = window.getSelection();
            if (selection?.rangeCount && this.$refs.editor.contains(selection.getRangeAt(0).commonAncestorContainer)) {
                this.range = selection.getRangeAt(0).cloneRange();
                return this.range;
            }
            if (this.range && this.$refs.editor.contains(this.range.commonAncestorContainer)) return this.range;
            const range = document.createRange();
            range.selectNodeContents(this.$refs.editor);
            range.collapse(false);
            return range;
        },
        insert(node) {
            const range = this.currentRange();
            range.deleteContents();
            range.insertNode(node);
            range.setStartAfter(node);
            range.collapse(true);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            this.range = range.cloneRange();
            this.$refs.editor.focus();
            this.sync();
        },
        paste(text) { this.insert(document.createTextNode(text)); },
        wrap(tag) {
            if (!['strong', 'em', 'h2', 'p', 'ul'].includes(tag)) return;
            const range = this.currentRange();
            const node = document.createElement(tag);
            const fragment = range.extractContents();
            if (tag === 'ul') {
                const item = document.createElement('li');
                item.appendChild(fragment);
                node.appendChild(item);
            } else node.appendChild(fragment);
            if (!node.textContent) node.appendChild(document.createTextNode('Text'));
            range.insertNode(node);
            range.selectNodeContents(node);
            const selection = window.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            this.range = range.cloneRange();
            this.$refs.editor.focus();
            this.sync();
        },
        link() {
            const value = window.prompt('Link address (https://...)');
            if (!value) return;
            let url;
            try { url = new URL(value); } catch { return; }
            if (!['https:', 'http:', 'mailto:'].includes(url.protocol) || url.username || url.password) return;
            const range = this.currentRange();
            const node = document.createElement('a');
            node.href = url.href;
            node.appendChild(range.extractContents());
            if (!node.textContent) node.appendChild(document.createTextNode(url.href));
            range.insertNode(node);
            range.selectNodeContents(node);
            this.range = range.cloneRange();
            this.$refs.editor.focus();
            this.sync();
        }
    };
};
</script>
@endonce
<div x-data="courseRichTextEditor()" class="space-y-2">
    <label id="{{ $id }}-label" for="{{ $id }}" class="block text-sm font-semibold text-slate-700">Lesson text</label>
    <div role="toolbar" aria-label="Text formatting" class="flex flex-wrap gap-1 rounded-t-xl border border-slate-300 bg-slate-50 p-2">
        @foreach(['strong'=>'Bold','em'=>'Italic','h2'=>'Heading','p'=>'Paragraph','ul'=>'List'] as $tag=>$label)
            <button type="button" @mousedown.prevent @click="wrap('{{ $tag }}')" class="min-h-11 rounded-lg px-3 text-xs font-semibold text-slate-700 hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-amber-600">{{ $label }}</button>
        @endforeach
        <button type="button" @mousedown.prevent @click="link()" class="min-h-11 rounded-lg px-3 text-xs font-semibold text-slate-700 hover:bg-amber-100 focus-visible:outline-2 focus-visible:outline-amber-600">Link</button>
    </div>
    <div id="{{ $id }}" x-ref="editor" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="{{ $id }}-label"
        aria-describedby="{{ $id }}-help" dir="auto" @input="sync()" @keyup="remember()" @mouseup="remember()" @blur="remember()"
        @paste.prevent="paste($event.clipboardData.getData('text/plain'))" @drop.prevent
        class="min-h-40 rounded-b-xl border border-slate-300 bg-white p-4 text-sm leading-7 focus:outline-2 focus:outline-amber-600 [&_h2]:text-xl [&_h2]:font-bold [&_ul]:list-disc [&_ul]:ps-6 [&_a]:text-amber-700 [&_a]:underline [&_p]:my-2"></div>
    <textarea name="html" x-ref="source" class="hidden" aria-hidden="true" tabindex="-1">{{ $html }}</textarea>
    <p id="{{ $id }}-help" class="text-xs text-slate-500">Arabic and English can be mixed. Paste keeps plain text. Add images as separate Image blocks.</p>
</div>
