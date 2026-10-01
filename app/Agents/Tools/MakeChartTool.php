<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Support\SvgChart;
use Illuminate\Support\Str;

class MakeChartTool extends Tool
{
    public function name(): string
    {
        return 'make_chart';
    }

    public function description(): string
    {
        return 'Построить график (линия, столбцы, кольцевая) и сохранить его как SVG-артефакт.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string'],
                'type' => ['type' => 'string', 'enum' => ['line', 'bar', 'donut']],
                'labels' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Подписи по оси X (для line/bar)'],
                'series' => [
                    'type' => 'array',
                    'description' => 'Ряды для line: [{"name": "Выручка", "data": [1,2,3]}]. Для bar: [{"name":"...","data":[1,2,3]}].',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'data' => ['type' => 'array', 'items' => ['type' => 'number']],
                        ],
                    ],
                ],
                'money' => ['type' => 'boolean', 'description' => 'Подписи в рублях'],
            ],
            'required' => ['type', 'series'],
        ];
    }

    public function title(array $args): string
    {
        return 'График «'.$this->str($args, 'title', 'без названия').'»';
    }

    public function run(Agent $agent, array $args): array
    {
        $type = strtolower((string) $this->str($args, 'type', 'bar'));
        $title = (string) $this->str($args, 'title', 'График');
        $money = filter_var($args['money'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $labels = array_map('strval', $this->arr($args, 'labels'));

        $series = [];
        foreach ($this->arr($args, 'series') as $row) {
            if (! is_array($row)) {
                continue;
            }

            $data = array_map(fn ($v) => is_numeric($v) ? (float) $v : 0.0, (array) ($row['data'] ?? []));

            $series[] = [
                'name' => (string) ($row['name'] ?? 'Ряд'),
                'data' => $data,
            ];
        }

        if ($series === [] || collect($series)->sum(fn (array $s) => count($s['data'])) === 0) {
            throw new \InvalidArgumentException('Нужны данные: series = [{"name": "...", "data": [1,2,3]}]');
        }

        $chart = app(SvgChart::class);

        $svg = match ($type) {
            'line' => $chart->line($labels, $series, $title, $money),
            'donut' => $chart->donut(
                collect($series[0]['data'])->values()
                    ->map(fn ($v, $i) => ['label' => $labels[$i] ?? ('Доля '.($i + 1)), 'value' => $v])
                    ->all(),
                $title,
                $money
            ),
            default => $chart->bar($labels, $series[0]['data'], $title, $money),
        };

        return $this->makeArtifact(
            $agent,
            Str::slug($title).'.svg',
            $svg,
            'chart',
            'image/svg+xml',
            $title
        );
    }
}
