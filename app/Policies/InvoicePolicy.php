<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Enums\InvoiceStatus;
use App\Support\InvoiceVisibility;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isActive();
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            return $user->isAdmin()
                || InvoiceVisibility::canView($user, $invoice->submitter ?? $invoice->submitter()->firstOrFail());
        }

        return $user->isAdmin()
            || $user->isFiscal()
            || InvoiceVisibility::canView($user, $invoice->submitter ?? $invoice->submitter()->firstOrFail());
    }

    public function create(User $user): bool
    {
        return $user->isActive();
    }

    public function update(User $user, Invoice $invoice): bool
    {
        if ($invoice->status === InvoiceStatus::Launched) {
            return false;
        }

        $submitter = $invoice->submitter ?? $invoice->submitter()->firstOrFail();

        if (
            $invoice->status === InvoiceStatus::Pending
            && $user->isRegularUser()
            && InvoiceVisibility::canView($user, $submitter)
        ) {
            return true;
        }

        return $user->isAdmin()
            || $user->isFiscal()
            || $invoice->submitted_by === $user->id;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($invoice->status === InvoiceStatus::Launched) {
            return false;
        }

        return $user->isRegularUser() && $invoice->submitted_by === $user->id;
    }

    public function review(User $user, Invoice $invoice): bool
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            return false;
        }

        return $user->isAdmin() || $user->isFiscal();
    }

    public function markAsLaunched(User $user, Invoice $invoice): bool
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            return false;
        }

        return $user->isAdmin() || $user->isFiscal();
    }

    public function viewPdf(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice);
    }
}
