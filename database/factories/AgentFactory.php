<?php

namespace Database\Factories;

use App\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    public function definition(): array
    {
        return [
            'title' => 'Задача '.fake()->unique()->numberBetween(1, 9999),
            'goal' => fake()->sentence(8),
            'status' => 'draft',
            'iteration' => 0,
            'tokens' => 0,
        ];
    }

    public function finished(): static
    {
        return $this->state(fn (): array => [
            'status' => 'done',
            'finished_at' => now(),
        ]);
    }
}