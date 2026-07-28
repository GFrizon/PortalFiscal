<?php

namespace App\Enums;

enum AlertLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Informacao',
            self::Warning => 'Atencao',
            self::Critical => 'Critico',
        };
    }
}
