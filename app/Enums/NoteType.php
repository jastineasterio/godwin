<?php

namespace App\Enums;

/** Character / spiritual development note categories (behavior_notes.type). */
enum NoteType: string
{
    case Behavior = 'behavior';
    case Spiritual = 'spiritual';
    case Character = 'character';
    case Achievement = 'achievement';
    case Health = 'health';

    public function label(): string
    {
        return match ($this) {
            self::Behavior => 'Behavior',
            self::Spiritual => 'Spiritual Growth',
            self::Character => 'Character Building',
            self::Achievement => 'Achievement',
            self::Health => 'Health & Wellbeing',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
