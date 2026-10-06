<?php

namespace App\Http\Requests\Admin;

use App\Domains\System\Services\DevelopmentDataCatalog;
use App\Domains\System\Services\DevelopmentToolsAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DevelopmentOperationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $actor = $this->user('web');
        if ($actor === null) {
            return false;
        }
        app(DevelopmentToolsAccess::class)->authorize($actor);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['reset', 'export'])],
            'domain' => ['required', Rule::in([...array_keys(DevelopmentDataCatalog::PHRASES), 'selection'])],
            'scope' => ['nullable', 'array:resources,categories,history,files,snapshot,export_before,modules,filters,backup_filenames'],
            'scope.resources' => ['sometimes', 'boolean'], 'scope.categories' => ['sometimes', 'boolean'],
            'scope.history' => ['sometimes', 'boolean'], 'scope.files' => ['sometimes', 'boolean'],
            'scope.snapshot' => ['sometimes', 'boolean'], 'scope.export_before' => ['sometimes', 'boolean'],
            'scope.modules' => ['required_if:type,export', 'array', 'min:1'],
            'scope.modules.*' => ['string', Rule::in(array_keys(DevelopmentDataCatalog::MODULES))],
            'scope.backup_filenames' => ['nullable', 'array', 'max:100'],
            'scope.backup_filenames.*' => ['string', 'regex:/^backup-[a-zA-Z0-9_-]+\.zip$/D'],
            'scope.filters' => ['nullable', 'array:student_id,currency,date_from,date_to'],
            'scope.filters.student_id' => ['nullable', 'integer', 'exists:students,id'],
            'scope.filters.currency' => ['nullable', 'regex:/^[A-Z]{3}$/'],
            'scope.filters.date_from' => ['nullable', 'date_format:Y-m-d'],
            'scope.filters.date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:scope.filters.date_from'],
        ];
    }
}
