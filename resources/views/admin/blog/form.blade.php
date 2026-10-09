@extends('layouts.admin')
@section('content')
@php
    $editing = $blog !== null;
    $translationSource = $translationSource ?? null;
    $action = $editing ? route('admin.blog.update', $blog) : route('admin.blog.store');
    $currentStatus = old('status', $blog?->status ?? 'draft');
    $language = old('locale', $blog?->locale ?? ($initialLocale ?? 'en'));
@endphp
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="font-serif text-2xl font-bold text-slate-900">{{ $editing ? 'Edit Article' : 'Add Article' }}</h1><p class="mt-1 text-sm text-slate-500">Write, preview and publish educational articles.</p></div>
        <a href="{{ route('admin.blog.index') }}" class="text-sm font-semibold text-slate-600 underline">Return to Articles</a>
    </div>
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @if(session('success'))<p role="status" class="rounded-xl bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</p>@endif
    @if($translationSource ?? false)<p class="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">Translation of “{{ $translationSource->title }}”. Enter the actual approved translation; no content is generated or published automatically.</p>@endif
    <form data-blog-form data-public-base="{{ url($language === 'en' ? '/blog' : '/'.$language.'/blog') }}" data-original-slug="{{ $blog?->slug }}"
          data-was-published="{{ $blog?->published_at ? '1' : '0' }}" data-upload-url="{{ route('admin.media.store') }}"
          method="POST" action="{{ $action }}" class="space-y-8 rounded-2xl border border-slate-200 bg-white p-5 sm:p-8">
        @csrf @if($editing)@method('PUT')@endif
        <input type="hidden" name="lock_version" value="{{ old('lock_version', $blog?->lock_version ?? 1) }}">
        <input type="hidden" name="status" value="{{ $currentStatus }}">
        @if($translationSource)<input type="hidden" name="translation_of" value="{{ $translationSource->id }}">@endif
        <input type="hidden" name="translation_group_id" value="{{ old('translation_group_id', $blog?->translation_group_id ?? ($translationSource?->translation_group_id ?? '')) }}">
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="sm:col-span-2"><label for="title" class="block text-sm font-semibold">Article Title</label><input id="title" name="title" maxlength="255" placeholder="Enter a clear, descriptive title..." value="{{ old('title', $blog?->title) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2"><p class="mt-1 text-xs text-slate-500">Choose a title that accurately reflects what readers will find in the article.</p></div>
            <div><label for="slug" class="block text-sm font-semibold">Article Slug</label><input id="slug" name="slug" maxlength="255" placeholder="your-article-title" value="{{ old('slug', $blog?->slug) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2"><p class="mt-1 text-xs text-slate-500">Keep the web address short and readable. Intentional changes to published URLs preserve a redirect.</p></div>
            <div><label for="category_id" class="block text-sm font-semibold">Category</label><select id="category_id" name="category_id" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2"><option value="">Choose a category...</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected((string)old('category_id', $blog?->category_id) === (string)$category->id)>{{ $category->name }}</option>@endforeach</select></div>
            <div><label for="locale" class="block text-sm font-semibold">Article Language</label>
                @if($editing)<input type="hidden" name="locale" value="{{ $blog->locale }}"><p class="mt-2 rounded-lg bg-slate-50 px-3 py-2">{{ ['en'=>'English','fr'=>'Français','de'=>'Deutsch'][$blog->locale] }}</p>
                @else<select id="locale" name="locale" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">@foreach(['en'=>'English','fr'=>'Français','de'=>'Deutsch'] as $code=>$label)<option value="{{ $code }}" @selected($language === $code)>{{ $label }}</option>@endforeach</select>@endif
            </div>
            <div><span class="block text-sm font-semibold">Publication Status</span><p class="mt-2 rounded-lg bg-slate-50 px-3 py-2 capitalize">{{ $currentStatus }}</p>@if($blog?->published_at)<p class="mt-1 text-xs text-slate-500">First published {{ $blog->published_at->format('M j, Y, H:i') }} UTC.</p>@endif</div>
        </div>
        <div><label for="excerpt" class="block text-sm font-semibold">Article Excerpt</label><textarea id="excerpt" name="excerpt" rows="3" maxlength="2000" placeholder="Briefly explain what readers will learn..." class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('excerpt', $blog?->excerpt) }}</textarea><p class="mt-1 text-xs text-slate-500">This summary may appear in the article listing and featured previews.</p></div>
        <section data-blog-editor aria-labelledby="content-heading">
            <h2 id="content-heading" class="text-lg font-semibold">Article Content</h2>
            <div data-editor-controls hidden class="mt-3">
                <div class="mb-3 flex flex-wrap gap-3"><button type="button" data-editor-mode="visual" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Visual Editor</button><button type="button" data-editor-mode="source" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">HTML Source</button></div>
                <p data-preservation-warning hidden role="status" class="mb-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-900">Your HTML is preserved. Some markup cannot pass through the visual editor unchanged. Continue in HTML Source, or explicitly convert a copy to visual format.</p>
                <button type="button" data-convert-visual hidden class="mb-3 rounded-lg border border-amber-400 px-3 py-2 text-sm font-semibold text-amber-800">Convert to Visual Format</button>
                <div data-editor-toolbar role="toolbar" aria-label="Article formatting" class="flex flex-wrap gap-2 rounded-t-xl border border-slate-300 bg-slate-50 p-3">
                    @foreach(['paragraph'=>'Paragraph','heading2'=>'H2','heading3'=>'H3','bold'=>'Bold','italic'=>'Italic','bulletList'=>'Bullet List','orderedList'=>'Numbered List','blockquote'=>'Quote','link'=>'Link','image'=>'Image','ltr'=>'Left to Right','rtl'=>'Right to Left','undo'=>'Undo','redo'=>'Redo'] as $command=>$label)<button type="button" data-editor-command="{{ $command }}" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold hover:bg-amber-50">{{ $label }}</button>@endforeach
                    <button type="button" data-upload-kind="inline" class="rounded-md border border-slate-200 bg-white px-3 py-2 text-xs font-semibold">Upload Inline Image</button>
                </div>
            </div>
            <div data-editor-visual hidden class="article-copy min-h-96 rounded-b-xl border border-t-0 border-slate-300 p-5"></div>
            <label for="body" data-source-label class="mt-3 block text-sm font-semibold">HTML Source</label>
            <textarea id="body" name="body" rows="20" data-editor-source class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm" dir="auto">{{ old('body', $blog?->body) }}</textarea>
            <div data-source-image-actions hidden class="mt-3 flex flex-wrap gap-3">
                <button type="button" data-source-inline-image class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Choose Inline Image</button>
                <button type="button" data-upload-kind="inline" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Upload Inline Image</button>
            </div>
            <p class="mt-2 text-xs leading-6 text-slate-500">Arabic and Latin text are supported. Opening or saving unchanged content preserves its original HTML. Edited HTML is cleaned for safety; unsupported formatting may be removed. Tables and other unsupported visual markup can be maintained in HTML Source.</p>
        </section>
        <section aria-labelledby="image-heading" class="border-t border-slate-100 pt-6">
            <h2 id="image-heading" class="text-lg font-semibold">Featured Image</h2>
            <input type="hidden" id="featured_image_path" name="featured_image_path" value="{{ old('featured_image_path', $blog?->featured_image_path) }}">
            <img id="featured_image_preview" @if($featuredImage)src="{{ $featuredImage }}"@endif alt="" class="mt-4 aspect-video max-h-56 rounded-xl object-cover {{ $featuredImage ? '' : 'hidden' }}">
            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" onclick="openMediaPicker('featured_image_path', 'featured_image_preview', true)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Choose Image</button>
                <button type="button" data-upload-kind="featured" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Upload Image</button>
                <button type="button" data-remove-featured class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold">Remove Image</button>
            </div>
            <input type="file" data-image-upload accept="image/jpeg,image/png,image/webp,image/gif" hidden>
            <p data-upload-status role="status" class="mt-2 text-sm text-slate-600"></p>
            <label for="featured_image_alt" class="mt-4 block text-sm font-semibold">Image Description (Alt Text)</label>
            <input id="featured_image_alt" name="featured_image_alt" maxlength="300" value="{{ old('featured_image_alt', $blog?->featured_image_alt) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">
            <p class="mt-1 text-xs text-slate-500">Describe images that convey information. Leave blank only for decorative images.</p>
            <input type="hidden" id="blog_inline_image">
        </section>
        <section aria-labelledby="search-heading" class="space-y-4 border-t border-slate-100 pt-6">
            <h2 id="search-heading" class="text-lg font-semibold">Search Appearance</h2>
            <div class="grid gap-5 sm:grid-cols-2">
                <div><label for="seo_title" class="block text-sm font-semibold">SEO Title</label><input id="seo_title" name="seo_title" maxlength="255" value="{{ old('seo_title', $blog?->seo_title) }}" placeholder="How this article should appear in search results..." class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2"></div>
                <div><label for="seo_description" class="block text-sm font-semibold">SEO Description</label><textarea id="seo_description" name="seo_description" rows="2" maxlength="500" placeholder="Summarize the article for search results..." class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">{{ old('seo_description', $blog?->seo_description) }}</textarea></div>
            </div>
            <div><label for="canonical_url" class="block text-sm font-semibold">Canonical URL</label><input id="canonical_url" name="canonical_url" type="url" maxlength="2048" value="{{ old('canonical_url', $blog?->canonical_url) }}" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2"><p class="mt-1 text-xs text-slate-500">Leave blank to use the article's normal public URL, unless another canonical address is intentionally required.</p></div>
            <div aria-label="Search preview" class="rounded-xl bg-slate-50 p-4"><p data-search-url class="break-all text-xs text-slate-600"></p><p data-search-title class="mt-2 text-xl text-blue-800"></p><p data-search-description class="mt-2 text-sm text-slate-600"></p><p class="mt-3 text-xs text-slate-500">Search engines may choose to display different titles or descriptions.</p></div>
            <ul data-editor-feedback aria-live="polite" class="list-inside list-disc space-y-1 text-sm text-amber-800"></ul>
        </section>
        <section aria-labelledby="relationships-heading" class="border-t border-slate-100 pt-6">
            <h2 id="relationships-heading" class="text-lg font-semibold">Related Content</h2><p class="mt-2 text-sm text-slate-500">Choose content that is relevant to this article. Public pages only show genuinely available items.</p>
            <div class="mt-5 grid gap-5 md:grid-cols-3">
                @foreach(['article'=>['Related Articles',$relatedArticleChoices,$blog?->relatedArticles], 'resource'=>['Related Learning Resources',$relatedResourceChoices,$blog?->relatedResources], 'game'=>['Related Games',$relatedGameChoices,$blog?->relatedGames]] as $kind=>$group)
                    @php($selected = old('related_'.$kind.'_ids', $group[2]?->pluck('id')->all() ?? []))
                    <fieldset class="rounded-xl border border-slate-200 p-4"><legend class="px-1 text-sm font-semibold">{{ $group[0] }}</legend><div class="max-h-56 space-y-3 overflow-y-auto">@forelse($group[1] as $item)<label class="flex items-start gap-2 text-sm"><input type="checkbox" name="related_{{ $kind }}_ids[]" value="{{ $item->id }}" @checked(in_array($item->id, $selected)) class="mt-1"><span>{{ $item->title }}@if($kind === 'article') <span class="text-xs text-slate-500">({{ strtoupper($item->locale) }})</span>@endif</span></label>@empty<p class="text-sm text-slate-500">No available items.</p>@endforelse</div></fieldset>
                @endforeach
            </div>
        </section>
        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-6">
            @if($editing)<a href="{{ route('admin.blog.preview', $blog) }}" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700">Preview Article</a>@endif
            @if($currentStatus === 'published')
                <button type="submit" name="intent" value="draft" data-confirm="Unpublish this article? It will leave the public listing and sitemap." class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Unpublish to Draft</button>
                <button type="submit" name="intent" value="publish" class="rounded-lg bg-amber-600 px-5 py-2.5 font-semibold text-white">Update Article</button>
            @else
                <button type="submit" name="intent" value="draft" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold">Save Draft</button>
                <button type="submit" name="intent" value="publish" class="rounded-lg bg-amber-600 px-5 py-2.5 font-semibold text-white">Publish Article</button>
            @endif
        </div>
        <p data-save-status role="status" class="text-right text-sm text-slate-600"></p>
    </form>
    @if($editing)
        <section class="rounded-2xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold">Related Translations</h2><p class="mt-2 text-sm text-slate-500">Only real published versions are offered to public readers.</p>
            <div class="mt-4 flex flex-wrap gap-4">@foreach(['en'=>'English','fr'=>'Français','de'=>'Deutsch'] as $code=>$label)@php($version=$versions->firstWhere('locale',$code))@if($version)<a href="{{ route('admin.blog.edit',$version) }}" class="text-sm font-semibold text-amber-700 underline">Edit {{ $label }} ({{ $version->status }})</a>@else<a href="{{ route('admin.blog.create',['translation_of'=>$blog->id,'locale'=>$code]) }}" class="text-sm font-semibold text-amber-700 underline">Add {{ $label }} Translation</a>@endif@endforeach</div>
        </section>
        <details class="rounded-2xl border border-slate-200 bg-white p-6"><summary class="cursor-pointer font-semibold">Recent saved revisions ({{ $blog->revisions->count() }})</summary><p class="mt-3 text-sm text-slate-500">Previous published content is preserved here. Copy from a revision into HTML Source to restore it intentionally.</p>@foreach($blog->revisions as $revision)<details class="mt-4 border-t border-slate-100 pt-3"><summary class="cursor-pointer text-sm">{{ $revision->created_at->format('M j, Y, H:i') }} UTC · {{ $revision->snapshot['title'] ?? 'Article' }}</summary><textarea readonly aria-label="Preserved revision HTML" rows="10" class="mt-3 w-full rounded-lg border border-slate-300 p-3 font-mono text-xs">{{ $revision->snapshot['body'] ?? '' }}</textarea></details>@endforeach</details>
    @endif
</div>
<dialog data-editor-dialog class="w-full max-w-lg rounded-2xl border border-slate-200 p-6 shadow-xl">
    <form method="dialog" class="space-y-4">
        <h2 data-dialog-title class="text-lg font-semibold"></h2>
        <label data-dialog-label for="editor-dialog-value" class="block text-sm font-semibold"></label>
        <input id="editor-dialog-value" data-dialog-value class="w-full rounded-lg border border-slate-300 px-3 py-2">
        <p data-dialog-error role="alert" class="text-sm text-red-700"></p>
        <div class="flex justify-end gap-3"><button value="cancel" class="rounded-lg border border-slate-300 px-4 py-2">Cancel</button><button value="confirm" class="rounded-lg bg-amber-600 px-4 py-2 font-semibold text-white">Apply</button></div>
    </form>
</dialog>
@endsection
