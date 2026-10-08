<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'nik' => 'EMP'.Str::upper(Str::random(6)),
            'name' => fake()->name(),
            'department_id' => Department::factory(),
            'position_id' => Position::factory(),
            'phone' => fake()->numerify('08##-####-####'),
            'gender' => fake()->randomElement(['L', 'P']),
            'hire_date' => fake()->dateTimeBetween('-5 years', '-1 year')->format('Y-m-d'),
            'status' => Employee::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Employee::STATUS_INACTIVE,
        ]);
    }
}
