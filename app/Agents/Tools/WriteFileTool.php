<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Support\Workspace;

class WriteFileTool extends Tool
{
    public function name(): string
    {
        return 'write_file';
    }

    public function description(): string
    {
        return 'Записать текст в файл рабочей папки задачи (создаёт или перезаписывает).';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string', 'description' => 'Путь относительно рабочей папки, например data/answer.md'],
                'content' => ['type' => 'string', 'description' => 'Содержимое файла'],
            ],
            'required' => ['path', 'content'],
        ];
    }

    public function title(array $args): string
    {
        return 'Запись в файл '.$this->str($args, 'path', '?');
    }

    public function run(Agent $agent, array $args): array
    {
        $path = $this->str($args, 'path');
        $content = (string) ($args['content'] ?? '');

        if ($path === null || $path === '') {
            throw new \InvalidArgumentException('Не указан путь файла.');
        }

        $absolute = app(Workspace::class)->write($agent, $path, $content);
        $size = filesize($absolute) ?: 0;

        return [
            'output' => "Файл {$path} записан, размер ".round($size / 1024, 1).' КБ.',
            'summary' => $path,
            'ok' => true,
        ];
    }
}