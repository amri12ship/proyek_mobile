<?php

namespace Database\Factories;

use App\Models\AttendanceLocation;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_EMPLOYEE,
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Attach a full employee profile (department, position, allowed locations).
     */
    public function withEmployee(?AttendanceLocation $location = null): static
    {
        return $this->afterCreating(function (User $user) use ($location): void {
            $employee = Employee::create([
                'user_id' => $user->id,
                'nik' => 'EMP'.fake()->unique()->numerify('####'),
                'name' => $user->name,
                'department_id' => Department::create(['name' => 'Dept '.fake()->unique()->numerify('####')])->id,
                'position_id' => Position::create(['name' => 'Pos '.fake()->unique()->numerify('####')])->id,
                'status' => Employee::STATUS_ACTIVE,
            ]);

            if ($location !== null) {
                $employee->locations()->attach($location->id, ['is_primary' => true]);
            }
        });
    }
}
