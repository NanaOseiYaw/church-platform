<?php

namespace App\Enums;

/**
 * Roles a user can hold within a department workspace.
 * These are pivot-table values, not Spatie roles.
 */
enum DepartmentRole: string
{
    case COORDINATOR           = 'coordinator';
    case ASSISTANT_COORDINATOR = 'assistant_coordinator';
    case MEMBER                = 'member';

    public function label(): string
    {
        return match($this) {
            self::COORDINATOR           => 'Coordinator',
            self::ASSISTANT_COORDINATOR => 'Asst. Coordinator',
            self::MEMBER                => 'Member',
        };
    }

    /** Tailwind classes for the role badge */
    public function badgeClasses(): string
    {
        return match($this) {
            self::COORDINATOR           => 'bg-amber-100 text-amber-800',
            self::ASSISTANT_COORDINATOR => 'bg-blue-100 text-blue-700',
            self::MEMBER                => 'bg-neutral-100 text-neutral-600',
        };
    }

    /** All valid string values — useful for validation rules */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function validationRule(): string
    {
        return 'in:' . implode(',', self::values());
    }

    /**
     * Returns a portable CASE expression that maps each role to an integer
     * weight, suitable for use inside orderByRaw().
     *
     * Works on MySQL, PostgreSQL, and SQLite.
     * Do NOT use FIELD() — that function is MySQL-specific.
     *
     * Usage:
     *   ->orderByRaw(DepartmentRole::toOrderSql('department_user.role'))
     *
     * @param  string  $column  Fully-qualified column reference (table.column or alias)
     */
    public static function toOrderSql(string $column = 'role'): string
    {
        return "CASE {$column} "
            . "WHEN 'coordinator' THEN 0 "
            . "WHEN 'assistant_coordinator' THEN 1 "
            . "WHEN 'member' THEN 2 "
            . 'ELSE 3 END';
    }
}
