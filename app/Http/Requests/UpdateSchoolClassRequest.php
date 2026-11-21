<?php

namespace App\Http\Requests;

use App\Enums\ClassLevel;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation for updating an existing school class (Admin & Head of School). */
class UpdateSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole([
            UserRole::Admin,
            UserRole::HeadOfSchool,
        ]) ?? false;
    }

    public function rules(): array
    {
        $class = $this->route('class');

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('classes', 'code')->ignore($class->id)],
            'level' => ['required', Rule::in(ClassLevel::values())],
            'teacher_id' => ['nullable', $this->activeTeacherRule()],
            'capacity' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** The class teacher must be an active user with the teacher role. */
    protected function activeTeacherRule()
    {
        return function ($attribute, $value, $fail) {
            if ($value === null || $value === '') {
                return;
            }

            $teacher = \App\Models\User::where('id', $value)
                ->where('role', UserRole::Teacher)
                ->where('status', 'active')
                ->first();

            if (! $teacher) {
                $fail('The selected class teacher must be an active teacher.');
            }
        };
    }

    public function attributes(): array
    {
        return [
            'teacher_id' => 'class teacher',
        ];
    }
}
