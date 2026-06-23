<?php

namespace App\Enums;

enum DepartmentVisibility: string
{
    case PUBLIC       = 'public';
    case MEMBERS_ONLY = 'members_only';
    case PRIVATE      = 'private';

    public function label(): string
    {
        return match($this) {
            self::PUBLIC       => 'Public',
            self::MEMBERS_ONLY => 'Members only',
            self::PRIVATE      => 'Private',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::PUBLIC       => 'All church members can see this workspace',
            self::MEMBERS_ONLY => 'Only assigned members can see content',
            self::PRIVATE      => 'Hidden from non-members — invite only',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Return all cases as a frontend-ready options array.
     * @return array<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $v) => [
                'value'       => $v->value,
                'label'       => $v->label(),
                'description' => $v->description(),
            ],
            self::cases(),
        );
    }
}
