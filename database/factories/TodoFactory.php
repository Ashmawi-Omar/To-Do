<?php

namespace Database\Factories;

use App\Models\Todo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Todo>
 */
class TodoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'completed' => false,
            'due_date' => now()->toDateString(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['completed' => true]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn () => ['due_date' => $date]);
    }
}
