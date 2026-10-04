<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLessonMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageLessonWorkspace', $this->route('booking'));
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'student_visible' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
            'kind' => ['prohibited'], 'resource_id' => ['prohibited'], 'file' => ['prohibited'], 'url' => ['prohibited'],
            'path' => ['prohibited'], 'disk' => ['prohibited'], 'booking_id' => ['prohibited'], 'created_by' => ['prohibited'], 'withdrawn_at' => ['prohibited'],
        ];
    }
}
