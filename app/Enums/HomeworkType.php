<?php

namespace App\Enums;

/** Homework vs class activity (homeworks.type). */
enum HomeworkType: string
{
    case Homework = 'homework';
    case Activity = 'activity';

    public function label(): string
    {
        return match ($this) {
            self::Homework => 'Homework',
            self::Activity => 'Class Activity',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
