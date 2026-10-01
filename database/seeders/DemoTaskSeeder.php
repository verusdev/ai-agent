<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\LlmProvider;
use App\Support\SvgChart;
use App\Support\Workspace;
use Illuminate\Database\Seeder;

/**
 * Пример выполненной задачи — чтобы интерфейс не был пустым при первом запуске.
 * Ни одна модель не вызывается: всё подготовлено заранее.
 */
class DemoTaskSeeder extends Seeder
{
    public function run(): void
    {
        $provider = LlmProvider::defaults();

        $agent = Agent::create([
            'title' => 'Разбор отчёта по подпискам за квартал',
            'goal' => 'Собери краткий разбор: посчитай выручку по месяцам, сделай график и запиши выводы в markdown.',
            'status' => 'done',
            'provider_id' => $provider?->id,
            'model' => $provider?->model,
            'plan' => [
                ['title' => 'Собрать исходные цифры по месяцам', 'status' => 'done'],
                ['title' => 'Посчитать средний чек и прирост', 'status' => 'done'],
                ['title' => 'Построить график выручки', 'status' => 'done'],
                ['title' => 'Собрать документ с выводами', 'status' => 'done'],
            ],
            'iteration' => 4,
            'tokens' => 3870,
            'started_at' => now()->subMinutes(24),
            'finished_at' => now()->subMinutes(19),
        ]);

        $agent->messages()->create(['role' => 'user', 'content' => $agent->goal]);

        $agent->messages()->create([
            'role' => 'assistant',
            'content' => 'Разберу квартал: соберу цифры, посчитаю метрики, построю график и запишу выводы в markdown.',
        ]);

        $agent->messages()->create([
            'role' => 'tool',
            'tool_name' => 'update_plan',
            'content' => 'План сохранён: 4 шаг(ов), выполнено 0.',
        ]);

        $steps = [
            ['update_plan', 'plan', 'План обновлён: 4 шаг(ов)', 'План сохранён: 4 шаг(ов)', 120],
            ['calculate', 'tool', 'Расчёт: (412000+438500+501300)/3', 'Выручка за квартал: 1 351 800 ₽; средний чек: 450 600 ₽', 40],
            ['calculate', 'tool', 'Расчёт: (501300-412000)/412000*100', 'Прирост к последнему месяцу: 21,7 %', 30],
            ['make_chart', 'tool', 'График «Выручка по месяцам квартала»', 'Файл создан: vyruchka-po-mesyacam.svg (2.4 КБ)', 310],
            ['create_document', 'tool', 'Документ «Выводы по подпискам за квартал»', 'Файл создан: vyvody-po-podpiskam.md (1.1 КБ)', 260],
        ];

        foreach ($steps as $i => [$tool, $type, $title, $output, $duration]) {
            $agent->steps()->create([
                'iteration' => $i + 1,
                'type' => $type,
                'tool' => $tool,
                'title' => $title,
                'output' => $output,
                'status' => 'done',
                'duration_ms' => $duration,
                'created_at' => now()->subMinutes(24 - $i),
            ]);
        }

        $agent->messages()->create([
            'role' => 'tool',
            'tool_name' => 'create_document',
            'content' => 'Файл создан: vyvody-po-podpiskam.md',
        ]);

        $agent->messages()->create([
            'role' => 'assistant',
            'content' => "Готово. Выручка выросла с 412 000 до 501 300 ₽ (+21,7%), средний чек — 450 600 ₽.\n\nСобрал два файла: график и markdown с выводами.",
        ]);

        $workspace = app(Workspace::class);
        $workspace->ensure($agent);

        $chart = app(SvgChart::class)->bar(
            ['Январь', 'Февраль', 'Март'],
            [412000, 438500, 501300],
            'Выручка по месяцам квартала',
            true
        );

        $workspace->write($agent, 'vyruchka-po-mesyacam.svg', $chart);

        $workspace->write($agent, 'vyvody-po-podpiskam.md', <<<'MD'
        # Выводы по подпискам за квартал

        ## Цифры

        | Месяц | Выручка | Чеков | Средний чек |
        |---|---|---|---|
        | Январь | 412 000 ₽ | 980 | 420 ₽ |
        | Февраль | 438 500 ₽ | 1 010 | 434 ₽ |
        | Март | 501 300 ₽ | 1 060 | 473 ₽ |

        ## Что произошло

        - Выручка выросла на **21,7 %** к январю.
        - Средний чек вырос с 420 до 473 ₽ — сработал переход на годовые тарифы.
        - Отток снизился: повторных отмен 4,1 % против 6,8 % в прошлом квартале.

        ## Что делать дальше

        1. Масштабировать годовые тарифы на весь сегмент.
        2. Проверить гипотезу роста чека на семейном тарифе.
        MD);

        $agent->artifacts()->create([
            'name' => 'vyruchka-po-mesyacam.svg',
            'path' => 'vyruchka-po-mesyacam.svg',
            'kind' => 'chart',
            'mime' => 'image/svg+xml',
            'size' => strlen($chart),
            'summary' => 'Выручка по месяцам квартала',
        ]);

        $agent->artifacts()->create([
            'name' => 'vyvody-po-podpiskam.md',
            'path' => 'vyvody-po-podpiskam.md',
            'kind' => 'document',
            'mime' => 'text/markdown',
            'size' => 1100,
            'summary' => 'Выводы по подпискам за квартал',
        ]);

        $this->command?->info('Демонстрационная задача создана: #'.$agent->id);
    }
}