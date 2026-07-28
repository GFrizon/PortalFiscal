<?php

namespace Database\Factories;

use App\Enums\UserStatus;
use App\Models\BusinessUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessUnit>
 */
class BusinessUnitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'legal_name' => fake()->company().' LTDA',
            'cnpj' => fake()->numerify('##############'),
            'internal_code' => fake()->bothify('UN-###'),
            'status' => UserStatus::Active,
        ];
    }
}
