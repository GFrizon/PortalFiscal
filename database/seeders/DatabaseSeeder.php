<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\BusinessUnit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@bakof.local'],
            [
                'name' => 'Administrador',
                'password' => Hash::make('Alterar123!'),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
                'force_password_change' => app()->isProduction(),
            ]
        );

        $units = [
            ['name' => 'Matriz', 'legal_name' => 'BAKOF Matriz LTDA', 'cnpj' => '11222333000181', 'internal_code' => 'MAT'],
            ['name' => 'Unidade 01', 'legal_name' => 'BAKOF Unidade 01 LTDA', 'cnpj' => '11222333000262', 'internal_code' => 'U01'],
            ['name' => 'Unidade 02', 'legal_name' => 'BAKOF Unidade 02 LTDA', 'cnpj' => '11222333000343', 'internal_code' => 'U02'],
        ];

        foreach ($units as $unit) {
            BusinessUnit::query()->updateOrCreate(
                ['cnpj' => $unit['cnpj']],
                $unit + ['status' => UserStatus::Active]
            );
        }
    }
}
