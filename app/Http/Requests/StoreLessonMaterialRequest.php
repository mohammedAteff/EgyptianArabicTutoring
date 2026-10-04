<?php

namespace App\Http\Requests;

use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Resources\Models\Resource;
use App\Rules\PassiveLessonPdf;
use App\Rules\SafeLessonUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreLessonMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageLessonWorkspace', $this->route('booking'));
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(LessonMaterial::KINDS)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'student_visible' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
            'file' => [Rule::requiredIf($this->input('kind') === 'private_file'), Rule::prohibitedIf($this->input('kind') !== 'private_file'), 'file', 'mimes:pdf', 'extensions:pdf', 'max:10240', new PassiveLessonPdf],
            'resource_id' => [Rule::requiredIf($this->input('kind') === 'resource'), Rule::prohibitedIf($this->input('kind') !== 'resource'), 'nullable', 'integer', Rule::exists(Resource::class, 'id')->whereNull('deleted_at')->where('status', 'published')->whereNotNull('published_at')->where(fn ($query) => $query->where('published_at', '<=', now()))],
            'url' => [Rule::requiredIf(in_array($this->input('kind'), ['external_link', 'recording'], true)), Rule::prohibitedIf(! in_array($this->input('kind'), ['external_link', 'recording'], true)), 'nullable', 'string', 'max:2048', new SafeLessonUrl],
            'path' => ['prohibited'], 'disk' => ['prohibited'], 'booking_id' => ['prohibited'], 'created_by' => ['prohibited'], 'withdrawn_at' => ['prohibited'],
        ];
    }
}
