<?php

namespace App\Enums;

/** Kind of assessment entry (assessments.type). */
enum AssessmentType: string
{
    case Homework = 'homework';
    case Test = 'test';
    case Exam = 'exam';
    case Project = 'project';
    case Participation = 'participation';
    case Milestone = 'milestone'; // Nursery / Daycare developmental milestones

    public function label(): string
    {
        return match ($this) {
            self::Homework => 'Homework',
            self::Test => 'Test',
            self::Exam => 'Exam',
            self::Project => 'Project',
            self::Participation => 'Participation',
            self::Milestone => 'Milestone',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
