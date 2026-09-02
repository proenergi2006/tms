<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\MasterData\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'sso_id' => Str::uuid()->toString(),
            'role_id' => Role::factory(),
            'status' => 'aktif',
        ];
    }
}
