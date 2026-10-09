import { Editor, Extension, Mark, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';

const Direction = Extension.create({
    name: 'articleDirection',
    addGlobalAttributes() {
        return [{
            types: ['paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'listItem', 'codeBlock'],
            attributes: {
                dir: { default: null, parseHTML: el => ['rtl', 'ltr', 'auto'].includes(el.getAttribute('dir')) ? el.getAttribute('dir') : null },
                lang: { default: null, parseHTML: el => /^[a-zA-Z0-9-]{1,32}$/.test(el.getAttribute('lang') || '') ? el.getAttribute('lang') : null },
            },
        }];
    },
});
const LanguageSpan = Mark.create({
    name: 'languageSpan',
    addAttributes() { return { dir: { default: null }, lang: { default: null } }; },
    parseHTML() { return [{ tag: 'span[dir]' }, { tag: 'span[lang]' }, { tag: 'bdi', getAttrs: el => ({ dir: el.getAttribute('dir') || 'auto', lang: el.getAttribute('lang') }) }]; },
    renderHTML({ HTMLAttributes }) { return ['span', mergeAttributes(HTMLAttributes), 0]; },
});

export function htmlFingerprint(html) {
    if (!html.trim() || /^<p(?:\s[^>]*)?>\s*<\/p>$/.test(html.trim())) return '';
    const doc = new DOMParser().parseFromString(html, 'text/html');
    const blockTags = new Set(['P', 'DIV', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'PRE', 'TABLE', 'HR']);
    function fingerprint(node) {
        if (node.nodeType === Node.TEXT_NODE) {
            if (!node.textContent.trim() && (blockTags.has(node.parentNode?.tagName) || node.parentNode === doc.body) && (!node.previousSibling || blockTags.has(node.previousSibling.tagName)) && (!node.nextSibling || blockTags.has(node.nextSibling.tagName))) return '';
            return ['text', node.textContent];
        }
        if (node.nodeType !== Node.ELEMENT_NODE) return ['other', node.nodeType, node.textContent];
        const name = { B: 'STRONG', I: 'EM' }[node.tagName] || node.tagName;
        const attrs = Array.from(node.attributes, attr => [attr.name, attr.value]).sort((a, b) => a[0].localeCompare(b[0]));
        return [name, attrs, Array.from(node.childNodes, fingerprint).filter(item => item !== '')];
    }
    return JSON.stringify(Array.from(doc.body.childNodes, fingerprint).filter(item => item !== ''));
}

export function isAllowedArticleLink(value) {
    if (!value || /[\u0000-\u001f\u007f]/.test(value) || value.startsWith('//')) return false;
    try {
        const parsed = new URL(value, location.origin);
        return ['http:', 'https:', 'mailto:'].includes(parsed.protocol);
    } catch { return false; }
}

export function initializeBlogEditor(form) {
    if (form.dataset.editorReady) return;
    form.dataset.editorReady = '1';
    const panel = form.querySelector('[data-blog-editor]');
    const source = panel.querySelector('[data-editor-source]');
    const visual = panel.querySelector('[data-editor-visual]');
    const toolbar = panel.querySelector('[data-editor-toolbar]');
    const controls = panel.querySelector('[data-editor-controls]');
    const warning = panel.querySelector('[data-preservation-warning]');
    const convert = panel.querySelector('[data-convert-visual]');
    const sourceLabel = panel.querySelector('[data-source-label]');
    const sourceImageActions = panel.querySelector('[data-source-image-actions]');
    const feedback = form.querySelector('[data-editor-feedback]');
    const saveStatus = form.querySelector('[data-save-status]');
    let visualSource = source.value;
    let mode = 'source';
    let submitting = false;
    const field = name => form.elements.namedItem(name);
    const snapshot = () => JSON.stringify(Array.from(new FormData(form).entries()));
    const initialSnapshot = snapshot();
    let originalVisual = '';
    const editor = new Editor({
        element: visual,
        extensions: [
            StarterKit.configure({ link: { openOnClick: false, autolink: false, linkOnPaste: false } }),
            Image.configure({ allowBase64: false }), Direction, LanguageSpan,
        ],
        content: source.value,
        editorProps: { attributes: { role: 'textbox', 'aria-label': 'Article Content', 'aria-multiline': 'true', dir: 'auto', class: 'min-h-80 focus:outline-none' } },
        onUpdate: () => {
            if (mode !== 'visual') return;
            const html = editor.getHTML();
            source.value = htmlFingerprint(html) === originalVisual ? visualSource : html;
            refresh();
        },
        onSelectionUpdate: () => {
            toolbar.querySelectorAll('[data-editor-command]').forEach(button => {
                const command = button.dataset.editorCommand;
                const active = command === 'heading2' ? editor.isActive('heading', { level: 2 }) : command === 'heading3' ? editor.isActive('heading', { level: 3 }) : editor.isActive(command);
                button.setAttribute('aria-pressed', String(active));
            });
        },
    });
    originalVisual = htmlFingerprint(editor.getHTML());

    function changeMode(next, allowConversion = false) {
        if (next === 'visual') {
            editor.commands.setContent(source.value, { emitUpdate: false });
            visualSource = source.value;
            originalVisual = htmlFingerprint(editor.getHTML());
            const lossy = htmlFingerprint(source.value) !== htmlFingerprint(editor.getHTML());
            warning.hidden = !lossy;
            convert.hidden = !lossy;
            if (lossy && !allowConversion) next = 'source';
            if (allowConversion) {
                source.value = editor.getHTML();
                visualSource = source.value;
                warning.hidden = true;
                convert.hidden = true;
            }
        }
        mode = next;
        visual.hidden = mode !== 'visual';
        toolbar.hidden = mode !== 'visual';
        source.hidden = mode === 'visual';
        sourceLabel.hidden = mode === 'visual';
        if (sourceImageActions) sourceImageActions.hidden = mode !== 'source';
        controls.querySelectorAll('[data-editor-mode]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.editorMode === mode)));
        refresh();
    }

    function refresh() {
        saveStatus.textContent = submitting ? 'Saving article…' : snapshot() !== initialSnapshot ? 'Unsaved changes' : 'All changes saved';
        const title = field('title').value.trim();
        const slug = field('slug').value.trim();
        const canonical = field('canonical_url').value.trim();
        form.querySelector('[data-search-title]').textContent = field('seo_title').value.trim() || title || 'Article title';
        form.querySelector('[data-search-description]').textContent = field('seo_description').value.trim() || field('excerpt').value.trim() || 'Article summary';
        form.querySelector('[data-search-url]').textContent = canonical || form.dataset.publicBase + '/' + (slug || 'your-article-title');
        const messages = [];
        if (!title) messages.push('Add a clear article title before publishing.');
        if (!field('seo_description').value.trim() && !field('excerpt').value.trim()) messages.push('Add an article summary or search description.');
        if (slug && !/^[a-zA-Z0-9-]+$/.test(slug)) messages.push('The URL will be normalized when saved. Use letters, numbers and hyphens.');
        if (canonical && (!isAllowedArticleLink(canonical) || !/^https?:\/\//i.test(canonical))) messages.push('Use a complete HTTP or HTTPS canonical address.');
        if (field('featured_image_path').value && !field('featured_image_alt').value.trim()) messages.push('Add an image description if the featured image conveys information.');
        const doc = new DOMParser().parseFromString(source.value, 'text/html');
        if (doc.querySelector('h1')) messages.push('The article title already supplies H1. Prefer H2 and H3 in the body.');
        let previousLevel = 1;
        if (Array.from(doc.querySelectorAll('h2,h3,h4,h5,h6')).some(heading => {
            const level = Number(heading.tagName.slice(1)); const skipped = level > previousLevel + 1; previousLevel = level; return skipped;
        })) messages.push('A heading skips a level. Check the article structure.');
        if (Array.from(doc.querySelectorAll('img')).some(img => !img.hasAttribute('alt'))) messages.push('Some inline images have no alternative text.');
        if (Array.from(doc.querySelectorAll('a[href]')).some(link => !isAllowedArticleLink(link.getAttribute('href')))) messages.push('Some links have invalid or unsafe destinations.');
        if (field('status').value !== 'published') messages.push('This saved article is excluded from public listings and indexing until published.');
        feedback.replaceChildren(...messages.map(message => { const li = document.createElement('li'); li.textContent = message; return li; }));
    }

    async function ask(title, label, initial, validate = () => '') {
        const dialog = document.querySelector('[data-editor-dialog]');
        dialog.querySelector('[data-dialog-title]').textContent = title;
        dialog.querySelector('[data-dialog-label]').textContent = label;
        const input = dialog.querySelector('[data-dialog-value]');
        const error = dialog.querySelector('[data-dialog-error]');
        input.value = initial || ''; error.textContent = '';
        dialog.returnValue = '';
        return new Promise(resolve => {
            const validateInput = event => {
                if (event.submitter?.value !== 'confirm') return;
                const message = validate(input.value);
                if (message) { event.preventDefault(); error.textContent = message; }
            };
            dialog.querySelector('form').addEventListener('submit', validateInput);
            dialog.addEventListener('close', () => {
                dialog.querySelector('form').removeEventListener('submit', validateInput);
                resolve(dialog.returnValue === 'confirm' ? input.value : null);
            }, { once: true });
            dialog.showModal(); input.focus();
        });
    }

    async function insertImage(item) {
        const alt = await ask('Image Description', 'Describe the image (leave blank only for a decorative image)', item.alt_text || '');
        if (alt === null) return;
        if (mode === 'visual') {
            editor.chain().focus().setImage({ src: item.url, alt }).run();
        } else {
            const img = document.createElement('img'); img.src = item.url; img.alt = alt;
            source.setRangeText(img.outerHTML, source.selectionStart, source.selectionEnd, 'end'); refresh();
        }
    }

    controls.hidden = false;
    controls.querySelectorAll('[data-editor-mode]').forEach(button => button.addEventListener('click', () => changeMode(button.dataset.editorMode)));
    convert.addEventListener('click', () => {
        if (window.confirm('Convert this content to the visual editor format? Unsupported markup may be changed or removed. Your existing saved content is preserved until you save.')) changeMode('visual', true);
    });
    toolbar.addEventListener('click', async event => {
        const command = event.target.closest('[data-editor-command]')?.dataset.editorCommand;
        if (!command) return;
        if (command === 'image') { window.openMediaPicker('blog_inline_image', null, true); return; }
        if (command === 'link') {
            const value = await ask('Article Link', 'Destination (HTTP, HTTPS, email or internal path; blank removes link)', editor.getAttributes('link').href, value => !value || isAllowedArticleLink(value) ? '' : 'Enter a valid link.');
            if (value === null) return;
            if (!value) editor.chain().focus().extendMarkRange('link').unsetLink().run();
            else editor.chain().focus().extendMarkRange('link').setLink({ href: value }).run();
            return;
        }
        const chain = editor.chain().focus();
        const actions = {
            paragraph: () => chain.setParagraph(), heading2: () => chain.toggleHeading({ level: 2 }), heading3: () => chain.toggleHeading({ level: 3 }),
            bold: () => chain.toggleBold(), italic: () => chain.toggleItalic(), bulletList: () => chain.toggleBulletList(),
            orderedList: () => chain.toggleOrderedList(), blockquote: () => chain.toggleBlockquote(),
            undo: () => chain.undo(), redo: () => chain.redo(),
            rtl: () => chain.updateAttributes(editor.isActive('heading') ? 'heading' : 'paragraph', { dir: 'rtl' }),
            ltr: () => chain.updateAttributes(editor.isActive('heading') ? 'heading' : 'paragraph', { dir: 'ltr' }),
        };
        actions[command]?.().run();
    });
    panel.querySelector('[data-source-inline-image]')?.addEventListener('click', () => window.openMediaPicker('blog_inline_image', null, true));
    window.addEventListener('media-selected', event => {
        if (event.detail.targetInputId === 'blog_inline_image') insertImage(event.detail.item);
        if (event.detail.targetInputId === 'featured_image_path') {
            if (!field('featured_image_alt').value.trim()) field('featured_image_alt').value = event.detail.item.alt_text || '';
            refresh();
        }
    });

    const fileInput = form.querySelector('[data-image-upload]');
    const uploadStatus = form.querySelector('[data-upload-status]');
    let uploadKind = 'featured';
    form.querySelectorAll('[data-upload-kind]').forEach(button => button.addEventListener('click', () => {
        uploadKind = button.dataset.uploadKind; fileInput.click();
    }));
    fileInput.addEventListener('change', async () => {
        if (!fileInput.files[0]) return;
        uploadStatus.textContent = 'Uploading image…';
        const data = new FormData(); data.append('file', fileInput.files[0]); data.append('images_only', '1');
        try {
            const response = await fetch(form.dataset.uploadUrl, { method: 'POST', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: data });
            const item = await response.json();
            if (!response.ok) throw new Error(item.errors?.file?.[0] || 'We couldn’t upload this image. Please try again or choose another file.');
            if (uploadKind === 'inline') await insertImage(item);
            else {
                field('featured_image_path').value = item.path;
                const image = form.querySelector('#featured_image_preview'); image.src = item.url; image.hidden = false; image.classList.remove('hidden');
                refresh();
            }
            uploadStatus.textContent = 'Image uploaded.';
        } catch (error) { uploadStatus.textContent = error.message; }
        fileInput.value = '';
    });
    form.querySelector('[data-remove-featured]').addEventListener('click', () => {
        field('featured_image_path').value = ''; field('featured_image_alt').value = '';
        const image = form.querySelector('#featured_image_preview'); image.removeAttribute('src'); image.classList.add('hidden'); refresh();
    });
    let automaticSlug = !field('slug').value && form.dataset.wasPublished !== '1';
    field('slug').addEventListener('input', () => { automaticSlug = false; });
    field('title').addEventListener('input', () => {
        if (automaticSlug) field('slug').value = field('title').value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 255);
    });
    field('locale')?.addEventListener('change', () => {
        const locale = field('locale').value;
        form.dataset.publicBase = location.origin + (locale === 'en' ? '/blog' : '/' + locale + '/blog'); refresh();
    });
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    form.addEventListener('submit', event => {
        if (submitting) { event.preventDefault(); return; }
        const confirmation = event.submitter?.dataset.confirm;
        if (confirmation && !window.confirm(confirmation)) { event.preventDefault(); return; }
        if (form.dataset.wasPublished === '1' && field('slug').value !== form.dataset.originalSlug && !window.confirm('Change this published article URL? The previous URL will redirect to the new address.')) { event.preventDefault(); return; }
        submitting = true; refresh();
    });
    window.addEventListener('beforeunload', event => {
        if (!submitting && snapshot() !== initialSnapshot) { event.preventDefault(); event.returnValue = ''; }
    });
    changeMode('visual');
}
