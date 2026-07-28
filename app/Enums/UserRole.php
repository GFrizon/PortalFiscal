<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Fiscal = 'fiscal';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Fiscal => 'Fiscal',
            self::User => 'User',
        };
    }
}
