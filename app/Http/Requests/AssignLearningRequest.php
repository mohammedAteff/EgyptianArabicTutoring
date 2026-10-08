<?php

namespace App\Http\Requests;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\Course;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Exceptions\DstFoldAmbiguityException;
use App\Domains\Timezone\Exceptions\DstGapException;
use App\Domains\Timezone\Services\TimezoneService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AssignLearningRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');
        $actor = $this->user('web');

        return $student instanceof Student && $actor instanceof Administrator
            && Gate::forUser($actor)->allows('manageTeaching', $student) && Gate::forUser($actor)->allows('manage', Course::class);
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['request_key' => ['required', 'uuid'], 'booking_id' => ['nullable', 'integer', 'min:1'],
            'instructions' => ['nullable', 'string', 'max:5000'], 'access_mode' => ['sometimes', Rule::in(['permanent', 'fixed', 'relative'])],
            'starts_local' => ['nullable', 'date_format:Y-m-d\TH:i'], 'expires_local' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'starts_at' => ['nullable', 'string', 'max:40'], 'expires_at' => ['nullable', 'string', 'max:40'],
            'relative_days' => ['nullable', 'integer', 'min:1', 'max:36500']];
    }

    /** @return array<string,mixed> */
    public function availability(): array
    {
        $values = $this->validated();
        $timezones = app(TimezoneService::class);
        foreach (['starts' => 'starts_at', 'expires' => 'expires_at'] as $name => $field) {
            if (! empty($values[$name.'_local'])) {
                try {
                    $values[$field] = $timezones->resolveLocalWallTime($values[$name.'_local'], $timezones->getBusinessTimezone(), 'reject')->toIso8601String();
                } catch (DstGapException|DstFoldAmbiguityException) {
                    throw ValidationException::withMessages([$name.'_local' => 'This local time is missing or repeated at a clock change. Choose an unambiguous time.']);
                }
            }
            unset($values[$name.'_local']);
        }

        return $values;
    }
}
