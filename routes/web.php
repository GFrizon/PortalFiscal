<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\BusinessUnitController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FiscalReviewController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceHistoryController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\UserController;
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
    Route::get('alterar-senha', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('alterar-senha', [PasswordChangeController::class, 'update'])->name('password.update');

    Route::middleware('password_not_forced')->group(function (): void {
        Route::get('dashboard', DashboardController::class)->name('dashboard');
        Route::get('historico', [InvoiceHistoryController::class, 'index'])->name('histories.index');
        Route::get('historico/{invoice}', [InvoiceHistoryController::class, 'show'])->name('histories.show');
        Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::patch('invoices/{invoice}/unit', [FiscalReviewController::class, 'updateUnit'])->name('invoices.unit.update');
        Route::post('invoices/{invoice}/mark-as-pending', [FiscalReviewController::class, 'markAsPending'])->name('invoices.mark-as-pending');
        Route::post('invoices/{invoice}/mark-as-launched', [FiscalReviewController::class, 'markAsLaunched'])->name('invoices.mark-as-launched');
        Route::post('invoices/{invoice}/cancel', [FiscalReviewController::class, 'cancel'])->name('invoices.cancel');
        Route::post('invoices/{invoice}/alerts/{alert}/resolve', [FiscalReviewController::class, 'resolveAlert'])->name('invoices.alerts.resolve');
        Route::get('invoices/{invoice}/pdf', [PdfController::class, 'show'])->name('invoices.pdf.show');
        Route::get('invoices/{invoice}/pdf/download', [PdfController::class, 'download'])->name('invoices.pdf.download');

        Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
            Route::resource('users', UserController::class)->except(['show']);
            Route::resource('business-units', BusinessUnitController::class)->except(['show']);
            Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        });
    });
});
