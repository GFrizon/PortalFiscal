<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BusinessUnitController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FiscalReviewController;
use App\Http\Controllers\InvoiceAnnotationController;
use App\Http\Controllers\InvoiceAttachmentController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceHistoryController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserGroupController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

Route::middleware(['auth', 'active_user'])->group(function (): void {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('csrf-token', fn () => response()->json(['token' => csrf_token()]))->name('csrf-token.refresh');
    Route::get('alterar-senha', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('alterar-senha', [PasswordChangeController::class, 'update'])->name('password.update');

    Route::middleware('password_not_forced')->group(function (): void {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('historico', [InvoiceHistoryController::class, 'index'])->name('histories.index');
        Route::get('historico/{invoice}', [InvoiceHistoryController::class, 'show'])->name('histories.show');
        Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
        Route::post('invoices/{invoice}/draft-follow-ups', [InvoiceController::class, 'storeDraftFollowUp'])->name('invoices.draft-follow-ups.store');
        Route::patch('invoices/{invoice}/unit', [FiscalReviewController::class, 'updateUnit'])->name('invoices.unit.update');
        Route::post('invoices/{invoice}/mark-as-pending', [FiscalReviewController::class, 'markAsPending'])->name('invoices.mark-as-pending');
        Route::post('invoices/{invoice}/mark-as-launched', [FiscalReviewController::class, 'markAsLaunched'])->name('invoices.mark-as-launched');
        Route::post('invoices/{invoice}/alerts/{alert}/resolve', [FiscalReviewController::class, 'resolveAlert'])->name('invoices.alerts.resolve');
        Route::post('invoices/{invoice}/attachments', [InvoiceAttachmentController::class, 'store'])->name('invoices.attachments.store');
        Route::get('invoices/{invoice}/attachments/{attachment}', [InvoiceAttachmentController::class, 'show'])->name('invoices.attachments.show');
        Route::get('invoices/{invoice}/attachments/{attachment}/download', [InvoiceAttachmentController::class, 'download'])->name('invoices.attachments.download');
        Route::delete('invoices/{invoice}/attachments/{attachment}', [InvoiceAttachmentController::class, 'destroy'])->name('invoices.attachments.destroy');
        Route::put('invoices/{invoice}/annotations', [InvoiceAnnotationController::class, 'update'])->name('invoices.annotations.update');
        Route::get('invoices/{invoice}/pdf', [PdfController::class, 'show'])->name('invoices.pdf.show');
        Route::get('invoices/{invoice}/pdf/download', [PdfController::class, 'download'])->name('invoices.pdf.download');

        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
            Route::resource('users', UserController::class)->except(['show']);
            Route::resource('user-groups', UserGroupController::class)->except(['show']);
            Route::resource('business-units', BusinessUnitController::class)->except(['show']);
            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        });
    });
});
