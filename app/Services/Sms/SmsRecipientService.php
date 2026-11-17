<?php

namespace App\Services\Sms;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * SMS recipient selection and phone number normalization.
 *
 * Tanzanian mobile numbers are normalized to the DARSMS-accepted format:
 *   2557XXXXXXXX  (9 digits after country code, no leading +)
 */
class SmsRecipientService
{
    /** Tanzanian mobile prefixes (2557XX format). */
    protected const MOBILE_PREFIXES = ['75', '76', '77', '78', '74', '71', '62', '65', '67', '68'];

    /**
     * Normalize a phone number to 2557XXXXXXXX format.
     *
     * Accepts:
     *   - 0751234567  (local format with leading 0)
     *   - 255751234567 (international without +)
     *   - +255751234567 (international with +)
     *   - 0751 234 567 (with spaces)
     *
     * Returns null if the number cannot be normalized.
     */
    public function normalize(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        $normalized = preg_replace('/[\s\-\(\)]+/', '', trim($phone));

        // Remove leading +
        if (str_starts_with($normalized, '+')) {
            $normalized = substr($normalized, 1);
        }

        // Convert local format 07XX... to 2557XX...
        if (str_starts_with($normalized, '0')) {
            $normalized = '255' . substr($normalized, 1);
        }

        // Must be exactly 12 digits starting with 255
        if (! preg_match('/^255[0-9]{9}$/', $normalized)) {
            return null;
        }

        // Verify it's a mobile prefix (7xx)
        $prefix = substr($normalized, 3, 2);

        if (! in_array($prefix, self::MOBILE_PREFIXES, true)) {
            return null;
        }

        return $normalized;
    }

    /**
     * Normalize a list of phone numbers, returning valid and invalid separately.
     */
    public function normalizeList(array $phones): array
    {
        $valid = [];
        $invalid = [];

        foreach ($phones as $phone) {
            $normalized = $this->normalize($phone);

            if ($normalized) {
                $valid[] = $normalized;
            } else {
                $invalid[] = $phone;
            }
        }

        return ['valid' => array_values(array_unique($valid)), 'invalid' => array_values($invalid)];
    }

    /**
     * Get all parents/guardians with valid phone numbers.
     *
     * Optionally filtered by class ID(s) or specific student IDs.
     */
    public function getParents(
        ?array $classIds = null,
        ?array $studentIds = null
    ): array {
        $query = User::parents()->active();

        if ($studentIds && ! empty($studentIds)) {
            $query->whereHas('students', function ($q) use ($studentIds) {
                $q->whereIn('students.id', $studentIds);
            });
        } elseif ($classIds && ! empty($classIds)) {
            $query->whereHas('students.class', function ($q) use ($classIds) {
                $q->whereIn('classes.id', $classIds);
            });
        }

        $parents = $query->with('students')->get();

        $result = [];

        foreach ($parents as $parent) {
            $phone = $this->normalize($parent->phone);

            if (! $phone) {
                continue;
            }

            $linkedStudents = $parent->students
                ->filter(fn ($s) => !$studentIds || in_array($s->id, $studentIds))
                ->filter(fn ($s) => !$classIds || in_array($s->class_id, $classIds))
                ->values();

            if ($linkedStudents->isEmpty()) {
                continue;
            }

            $result[] = [
                'id' => $parent->id,
                'name' => $parent->name,
                'phone' => $phone,
                'raw_phone' => $parent->phone,
                'students' => $linkedStudents->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->full_name,
                    'reg_no' => $s->reg_no,
                    'class' => $s->class?->name,
                    'class_id' => $s->class_id,
                ])->toArray(),
                'is_primary' => true,
            ];
        }

        return $result;
    }

    /**
     * Get all teachers with valid phone numbers.
     */
    public function getTeachers(): array
    {
        $teachers = User::whereIn('role', ['teacher', 'head_of_school', 'admin'])
            ->active()
            ->get();

        $result = [];

        foreach ($teachers as $teacher) {
            $phone = $this->normalize($teacher->phone);

            if (! $phone) {
                continue;
            }

            $result[] = [
                'id' => $teacher->id,
                'name' => $teacher->name,
                'phone' => $phone,
                'raw_phone' => $teacher->phone,
                'role' => $teacher->role->value,
            ];
        }

        return $result;
    }

    /**
     * Get all staff with valid phone numbers.
     */
    public function getStaff(): array
    {
        return $this->getTeachers(); // Same query — staff = non-parent users with phones
    }

    /**
     * Get custom phone numbers (raw input, validated).
     */
    public function getCustom(array $phoneNumbers): array
    {
        $normalized = $this->normalizeList($phoneNumbers);

        $result = [];

        foreach ($normalized['valid'] as $phone) {
            $result[] = [
                'id' => 'custom_' . $phone,
                'name' => 'Custom Recipient',
                'phone' => $phone,
                'raw_phone' => $phone,
                'is_custom' => true,
            ];
        }

        return [
            'recipients' => $result,
            'invalid' => $normalized['invalid'],
        ];
    }

    /**
     * Detect duplicate phone numbers across recipient lists.
     */
    public function findDuplicates(array $recipients): array
    {
        $phones = [];

        foreach ($recipients as $recipient) {
            $phone = $recipient['phone'] ?? null;
            if (! $phone) {
                continue;
            }

            if (! isset($phones[$phone])) {
                $phones[$phone] = [];
            }

            $phones[$phone][] = $recipient;
        }

        $duplicates = [];

        foreach ($phones as $phone => $recips) {
            if (count($recips) > 1) {
                $duplicates[$phone] = $recips;
            }
        }

        return $duplicates;
    }

    /**
     * Get all active classes for recipient selection dropdowns.
     */
    public function getClasses(): array
    {
        return SchoolClass::active()->ordered()->get(['id', 'name', 'code', 'level'])->toArray();
    }

    /**
     * Get students in given classes for parent selection.
     */
    public function getStudentsInClasses(array $classIds): array
    {
        return Student::active()->inClassIds($classIds)->with('class')->get()->toArray();
    }
}
