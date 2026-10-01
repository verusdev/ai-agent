<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Support\Workspace;

class ListFilesTool extends Tool
{
    public function name(): string
    {
        return 'list_files';
    }

    public function description(): string
    {
        return 'Показать список файлов рабочей папки задачи.';
    }

    public function schema(): array
    {
        return ['type' => 'object', 'properties' => new \stdClass, 'required' => []];
    }

    public function title(array $args): string
    {
        return 'Список файлов';
    }

    public function run(Agent $agent, array $args): array
    {
        $files = app(Workspace::class)->listFiles($agent);

        if ($files === []) {
            return ['output' => 'Рабочая папка пуста.', 'summary' => 'пусто', 'ok' => true];
        }

        $lines = collect($files)
            ->map(fn (array $f) => sprintf('- %s (%s КБ, %s)', $f['path'], round($f['size'] / 1024, 1), $f['modified']))
            ->implode("\n");

        return [
            'output' => 'Файлов: '.count($files)."\n".$lines,
            'summary' => count($files).' файл(ов)',
            'ok' => true,
        ];
    }
}