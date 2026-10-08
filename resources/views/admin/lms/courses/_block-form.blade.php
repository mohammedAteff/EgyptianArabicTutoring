@php
    $blockKey=$block['key']??'new-'.$lesson['key'];
    $formId='block-'.str_replace(':','-',$blockKey);
    $kind=$block['kind']??'rich_text';
    $payload=$block['payload']??[];
    $isOld=old('operation')===($block?'edit_block':'add_block') && (old('key')===$blockKey || old('parent_key')===$lesson['key']);
    $kind=$isOld?old('kind',$kind):$kind;
    $source=$isOld?old('source',!empty($block['resource_id'])?'resource':'asset'):(!empty($block['resource_id'])?'resource':'asset');
@endphp
<form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" class="space-y-4"
    x-data="{kind:@js($kind), source:@js($source)}">
    @csrf
    <input type="hidden" name="version" value="{{ $course->lock_version }}">
    <input type="hidden" name="operation" value="{{ $block?'edit_block':'add_block' }}">
    <input type="hidden" name="{{ $block?'key':'parent_key' }}" value="{{ $block?$blockKey:$lesson['key'] }}">
    <div><label for="{{ $formId }}-kind" class="mb-1.5 block text-sm font-semibold text-slate-700">Content type</label>
        <select id="{{ $formId }}-kind" name="kind" x-model="kind" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 text-sm">
            @foreach(['rich_text'=>'Rich text','image'=>'Image','file'=>'PDF / file','resource'=>'Resource library reference','external_link'=>'External link','youtube_video'=>'YouTube video','external_video'=>'Vimeo / direct video','video'=>'Protected video asset','quiz'=>'Quiz','assignment'=>'Course assignment'] as $value=>$label)
                <option value="{{ $value }}" @selected($kind===$value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div x-show="kind==='rich_text'">
        <x-lms.rich-text-editor :id="$formId.'-text'" :value="$isOld?old('html',$payload['html']??''):($payload['html']??'')"/>
    </div>
    <div x-show="kind==='video'"><label for="{{ $formId }}-video" class="mb-1.5 block text-sm font-semibold text-slate-700">Ready video media</label><select id="{{ $formId }}-video" name="video_asset_id" :disabled="kind!=='video'" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"><option value="">Choose ready media</option>@foreach($videoAssets->where('status','ready') as $video)<option value="{{ $video->id }}" @selected((int)($block['video_asset_id']??0)===(int)$video->id)>{{ $video->label }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-500">Upload and check processing in Protected video media above. Replacing a selection retains the previous media.</p></div>
    <div x-show="['external_link','youtube_video','external_video'].includes(kind)">
        <x-lms.input label="Link address" name="url" type="url" :id="$formId.'-url'" :value="$isOld?old('url',$payload['url']??''):($payload['url']??'')" maxlength="2000" placeholder="https://..." hint="Use a safe HTTPS link. Videos support YouTube, Vimeo or direct MP4 / WebM / OGV files."/>
    </div>
    <div x-show="kind==='file'">
        <label for="{{ $formId }}-source" class="mb-1.5 block text-sm font-semibold text-slate-700">File source</label>
        <select id="{{ $formId }}-source" name="source" x-model="source" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 text-sm"><option value="asset">Course attachment</option><option value="resource">Existing Resource file</option></select>
    </div>
    <div x-show="kind==='resource'||(kind==='file'&&source==='resource')">
        <label for="{{ $formId }}-resource" class="mb-1.5 block text-sm font-semibold text-slate-700">Published Resource</label>
        <select id="{{ $formId }}-resource" name="resource_id" :disabled="!(kind==='resource'||(kind==='file'&&source==='resource'))" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 text-sm">
            <option value="">Choose a Resource</option>@foreach($resources as $resource)<option value="{{ $resource->id }}" @selected((int)($block['resource_id']??0)===(int)$resource->id)>{{ $resource->title }}</option>@endforeach
        </select>
        <p class="mt-2 text-xs text-slate-500">This keeps the original Resource and file. Removing the block does not delete either.</p>
    </div>
    <div x-show="kind==='image'||(kind==='file'&&source==='asset')">
        <label for="{{ $formId }}-asset" class="mb-1.5 block text-sm font-semibold text-slate-700">Course attachment</label>
        <select id="{{ $formId }}-asset" name="asset_id" :disabled="!(kind==='image'||(kind==='file'&&source==='asset'))" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 text-sm">
            <option value="">Choose an uploaded attachment</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" :disabled="kind!==@js($asset->kind)" @selected((int)($block['asset_id']??0)===(int)$asset->id)>{{ $asset->original_name??'Course attachment' }} ({{ $asset->kind }})</option>@endforeach
        </select>
        <p class="mt-2 text-xs text-slate-500">Upload a new image or file in Course attachments above, then choose it here.</p>
    </div>
    <div x-show="kind==='image'"><x-lms.input label="Image description" name="alt" :id="$formId.'-alt'" :value="$isOld?old('alt',$payload['alt']??''):($payload['alt']??'')" maxlength="300" hint="Describe what the image teaches for readers who cannot see it."/></div>
    <div x-show="kind==='file'"><x-lms.input label="Download button text" name="label" :id="$formId.'-label'" :value="$payload['label']??'Download file'" maxlength="300"/></div>
    @include('admin.lms.courses._assessment')
    <x-lms.button tone="neutral">{{ $block?'Save content draft':'Add content' }}</x-lms.button>
</form>
