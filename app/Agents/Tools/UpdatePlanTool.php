<?php

namespace App\Agents\Tools;

use App\Models\Agent;

class UpdatePlanTool extends Tool
{
    public function name(): string
    {
        return 'update_plan';
    }

    public function description(): string
    {
        return 'Обновить план работ: список шагов задачи и их статус (todo, doing, done). Вызывай в начале и по ходу выполнения.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'steps' => [
                    'type' => 'array',
                    'description' => 'Шаги плана: [{"title": "...", "status": "todo|doing|done"}]',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'status' => ['type' => 'string', 'enum' => ['todo', 'doing', 'done']],
                        ],
                        'required' => ['title'],
                    ],
                ],
                'note' => ['type' => 'string', 'description' => 'Короткий комментарий о текущем состоянии'],
            ],
            'required' => ['steps'],
        ];
    }

    public function title(array $args): string
    {
        $steps = $this->arr($args, 'steps');

        return 'План обновлён: '.count($steps).' шаг(ов)';
    }

    public function run(Agent $agent, array $args): array
    {
        $steps = [];

        foreach ($this->arr($args, 'steps') as $step) {
            $title = is_array($step) ? ($step['title'] ?? '') : (string) $step;
            if (trim($title) === '') {
                continue;
            }

            $status = is_array($step) ? ($step['status'] ?? 'todo') : 'todo';

            $steps[] = [
                'title' => mb_substr(trim($title), 0, 200),
                'status' => in_array($status, ['todo', 'doing', 'done'], true) ? $status : 'todo',
            ];
        }

        if ($steps === []) {
            return ['output' => 'Пустой план не сохранён.', 'summary' => 'пустой план', 'ok' => false];
        }

        $agent->update(['plan' => $steps]);

        $done = collect($steps)->where('status', 'done')->count();

        return [
            'output' => 'План сохранён: '.count($steps).' шаг(ов), выполнено '.$done.'.',
            'summary' => $this->str($args, 'note', 'План обновлён'),
            'ok' => true,
        ];
    }
}