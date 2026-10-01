<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Support\Workspace;

class ReadFileTool extends Tool
{
    public function name(): string
    {
        return 'read_file';
    }

    public function description(): string
    {
        return 'Прочитать файл из рабочей папки задачи.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'path' => ['type' => 'string'],
            ],
            'required' => ['path'],
        ];
    }

    public function title(array $args): string
    {
        return 'Чтение файла '.$this->str($args, 'path', '?');
    }

    public function run(Agent $agent, array $args): array
    {
        $path = $this->str($args, 'path');
        $content = app(Workspace::class)->read($agent, (string) $path, (int) config('agents.max_tool_output', 6000));

        return [
            'output' => $content,
            'summary' => mb_substr($content, 0, 80),
            'ok' => true,
        ];
    }
}