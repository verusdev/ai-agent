<?php

namespace Database\Seeders;

use App\Models\LlmProvider;
use App\Services\Llm\FreeModels;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $first = true;

        foreach (FreeModels::presets() as $slug => $preset) {
            LlmProvider::updateOrCreate(['slug' => $slug], [
                'label' => $preset['label'],
                'base_url' => $preset['base_url'],
                'model' => array_key_first($preset['models']),
                'models' => $preset['models'],
                'supports_tools' => $preset['supports_tools'],
                'note' => $preset['note'] ?? null,
                'is_active' => true,
                'is_default' => $first,
            ]);

            $first = false;
        }

        $this->command?->info('Провайдеры бесплатных моделей добавлены: '.LlmProvider::count());

        $this->call(DemoTaskSeeder::class);
    }
}