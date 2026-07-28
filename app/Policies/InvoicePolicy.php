<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Enums\InvoiceStatus;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || $user->isFiscal() || $invoice->submitted_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || $user->isFiscal() || $invoice->submitted_by === $user->id;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        if ($invoice->status === InvoiceStatus::Launched) {
            return false;
        }

        return $user->isAdmin() || $user->isFiscal() || $invoice->submitted_by === $user->id;
    }

    public function review(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || $user->isFiscal();
    }

    public function markAsLaunched(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || $user->isFiscal();
    }

    public function viewPdf(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
