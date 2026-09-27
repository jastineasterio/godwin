<?php

namespace App\Enums;

/**
 * The six system roles of God-Win Daycare & Nursery School (RBAC).
 * Mirrors the `role` enum column on the `users` table.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case SeniorPastor = 'senior_pastor';
    case HeadOfSchool = 'head_of_school';
    case Teacher = 'teacher';
    case Accountant = 'accountant';
    case Parent = 'parent';

    /** Human-readable label for UI badges and menus. */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::SeniorPastor => 'Senior Pastor',
            self::HeadOfSchool => 'Head of School',
            self::Teacher => 'Teacher',
            self::Accountant => 'Accountant',
            self::Parent => 'Parent / Guardian',
        };
    }

    /** Every authenticated staff member (i.e. anyone who is not a parent). */
    public function isStaff(): bool
    {
        return in_array($this, [
            self::Admin, self::SeniorPastor, self::HeadOfSchool,
            self::Teacher, self::Accountant,
        ], true);
    }

    /** System-wide administrators only. */
    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    /** All role values — handy for validation: ['role' => [UserRole::class]] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
