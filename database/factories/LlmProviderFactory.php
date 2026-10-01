<?php

namespace Database\Factories;

use App\Models\LlmProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LlmProvider>
 */
class LlmProviderFactory extends Factory
{
    protected $model = LlmProvider::class;

    public function definition(): array
    {
        return [
            'slug' => 'test-'.fake()->unique()->slug(2),
            'label' => 'Тестовый провайдер',
            'base_url' => 'https://api.example.test/v1',
            'api_key' => 'test-key',
            'model' => 'test-model',
            'supports_tools' => true,
            'is_default' => true,
            'is_active' => true,
            'models' => ['test-model' => 'Тестовая модель'],
        ];
    }

    public function local(): static
    {
        return $this->state(fn (): array => [
            'base_url' => 'http://127.0.0.1:11434/v1',
            'api_key' => null,
        ]);
    }
}