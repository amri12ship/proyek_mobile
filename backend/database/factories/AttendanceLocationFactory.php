<?php

namespace Database\Factories;

use App\Models\AttendanceLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceLocation>
 */
class AttendanceLocationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Lokasi '.fake()->unique()->city(),
            'address' => fake()->address(),
            'latitude' => -6.2 + fake()->randomFloat(6, 0, 0.05),
            'longitude' => 106.8 + fake()->randomFloat(6, 0, 0.05),
            'radius' => 200,
            'public_token' => AttendanceLocation::generateToken(),
            'status' => AttendanceLocation::STATUS_ACTIVE,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AttendanceLocation::STATUS_INACTIVE,
        ]);
    }

    public function token(string $token): static
    {
        return $this->state(fn (array $attributes) => [
            'public_token' => $token,
        ]);
    }

    public function radius(int $meters): static
    {
        return $this->state(fn (array $attributes) => [
            'radius' => $meters,
        ]);
    }
}
