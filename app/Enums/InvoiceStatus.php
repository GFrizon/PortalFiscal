<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case AwaitingReview = 'awaiting_review';
    case InReview = 'in_review';
    case Pending = 'pending';
    case Launched = 'launched';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingReview => 'Aguardando conferencia',
            self::InReview => 'Em conferencia',
            self::Pending => 'Com pendencia',
            self::Launched => 'Lancada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::AwaitingReview => 'Ag. conf.',
            self::InReview => 'Em conf.',
            self::Pending => 'Pendencia',
            self::Launched => 'Lancada',
            self::Cancelled => 'Cancelada',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::AwaitingReview => 'text-bg-secondary',
            self::InReview => 'text-bg-primary',
            self::Pending => 'text-bg-warning',
            self::Launched => 'text-bg-success',
            self::Cancelled => 'text-bg-danger',
        };
    }
}
