<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Enums\UserStatus;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'force_password_change',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
            'force_password_change' => 'boolean',
        ];
    }

    public function submittedInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'submitted_by');
    }

    public function fiscalInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'fiscal_user_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(InvoiceHistory::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isFiscal(): bool
    {
        return $this->role === UserRole::Fiscal;
    }

    public function isRegularUser(): bool
    {
        return $this->role === UserRole::User;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }
}
