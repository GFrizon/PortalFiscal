<?php

namespace App\Support;

use App\Enums\InvoiceStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class InvoiceVisibility
{
    public static function apply(Builder $query, User $user): Builder
    {
        if ($user->isFiscal()) {
            $query->where('status', '!=', InvoiceStatus::Draft->value);
        }

        if (! $user->isRegularUser()) {
            return $query;
        }

        if (! $user->user_group_id) {
            return $query->where('submitted_by', $user->id);
        }

        return $query->whereHas('submitter', function (Builder $query) use ($user): void {
            $query->where('user_group_id', $user->user_group_id);
        });
    }

    public static function canView(User $user, User $submitter): bool
    {
        if (! $user->isRegularUser()) {
            return true;
        }

        if (! $user->user_group_id) {
            return $submitter->id === $user->id;
        }

        return $submitter->user_group_id === $user->user_group_id;
    }
}
