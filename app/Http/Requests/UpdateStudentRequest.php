<?php

namespace App\Http\Requests;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Validation for updating an existing student record. */
class UpdateStudentRequest extends FormRequest
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
        $student = $this->route('student');

        return [
            // reg_no is immutable once issued (parents quote it on receipts)
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'other_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', Rule::in(Gender::values())],
            'dob' => ['nullable', 'date', 'before:today'],
            'class_id' => ['required', Rule::exists('classes', 'id')],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
            'admission_date' => ['nullable', 'date', 'before_or_equal:today'],
            'previous_school' => ['nullable', 'string', 'max:150'],
            'status' => ['required', Rule::in(StudentStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function attributes(): array
    {
        return ['class_id' => 'class', 'dob' => 'date of birth'];
    }
}
