<?php

namespace Database\Factories;

use App\Modules\MasterData\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'driver', 'mekanik', 'kepala_pool', 'bm', 'tim_logistik',
                'finance', 'admin_it_ga', 'admin_sistem',
            ]),
        ];
    }
}
