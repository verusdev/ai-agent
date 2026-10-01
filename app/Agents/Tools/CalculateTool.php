<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Support\Calculator;

class CalculateTool extends Tool
{
    public function name(): string
    {
        return 'calculate';
    }

    public function description(): string
    {
        return 'Посчитать арифметическое выражение: + - * / % ^, скобки, sqrt, abs, round, min, max, log, sin, cos.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'expression' => ['type' => 'string', 'description' => 'Например: (1200*3 + 450)/1.18' ],
            ],
            'required' => ['expression'],
        ];
    }

    public function title(array $args): string
    {
        return 'Расчёт: '.$this->str($args, 'expression', '');
    }

    public function run(Agent $agent, array $args): array
    {
        $expression = (string) $this->str($args, 'expression', '');
        $result = app(Calculator::class)->evaluate($expression);

        return [
            'output' => $expression.' = '.$this->pretty($result),
            'summary' => $this->pretty($result),
            'ok' => true,
        ];
    }

    private function pretty(float $value): string
    {
        return rtrim(rtrim(number_format($value, 6, ',', ' '), '0'), ',');
    }
}
