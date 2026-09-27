<?php

namespace App\Enums;

/** Online application pipeline (applications.status). */
enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Enrolled = 'enrolled'; // converted into a `students` record

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Enrolled => 'Enrolled',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Submitted => 'bg-sky-100 text-sky-700',
            self::UnderReview => 'bg-amber-100 text-amber-700',
            self::Accepted => 'bg-emerald-100 text-emerald-700',
            self::Rejected => 'bg-rose-100 text-rose-700',
            self::Enrolled => 'bg-violet-100 text-violet-700',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
