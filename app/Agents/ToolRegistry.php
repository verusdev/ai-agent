<?php

namespace App\Agents;

use App\Agents\Tools\CalculateTool;
use App\Agents\Tools\CreateDocumentTool;
use App\Agents\Tools\ListFilesTool;
use App\Agents\Tools\MakeChartTool;
use App\Agents\Tools\ReadFileTool;
use App\Agents\Tools\RunCodeTool;
use App\Agents\Tools\Tool;
use App\Agents\Tools\UpdatePlanTool;
use App\Agents\Tools\WebFetchTool;
use App\Agents\Tools\WebSearchTool;
use App\Agents\Tools\WriteFileTool;
use App\Models\Agent;
use Illuminate\Support\Facades\Log;

class ToolRegistry
{
    /**
     * @var array<string, Tool>|null
     */
    private ?array $tools = null;

    /**
     * @return array<string, Tool>
     */
    public function all(): array
    {
        return $this->tools ??= [
            'update_plan' => new UpdatePlanTool,
            'write_file' => new WriteFileTool,
            'read_file' => new ReadFileTool,
            'list_files' => new ListFilesTool,
            'create_document' => new CreateDocumentTool,
            'make_chart' => new MakeChartTool,
            'web_search' => new WebSearchTool,
            'web_fetch' => new WebFetchTool,
            'calculate' => new CalculateTool,
            'run_code' => new RunCodeTool,
        ];
    }

    public function has(string $name): bool
    {
        return isset($this->all()[$name]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function schemas(): array
    {
        return array_values(array_map(
            fn (Tool $tool) => $tool->toOpenAiSchema(),
            $this->all()
        ));
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    public function brief(): array
    {
        return array_map(
            fn (Tool $tool) => ['name' => $tool->name(), 'description' => $tool->description()],
            array_values($this->all())
        );
    }

    /**
     * Запуск инструмента с безопасной обработкой ошибок: модель всегда получает текст.
     *
     * @param  array<string, mixed>  $args
     * @return array{output: string, summary: string, ok: bool}
     */
    public function run(string $name, array $args, Agent $agent): array
    {
        $tool = $this->all()[$name] ?? null;

        if (! $tool) {
            $available = implode(', ', array_keys($this->all()));

            return [
                'output' => "Инструмент «{$name}» не найден. Доступные: {$available}.",
                'summary' => 'неизвестный инструмент',
                'ok' => false,
            ];
        }

        try {
            $result = $tool->run($agent, $args);
            $result['ok'] ??= true;

            return $result;
        } catch (\Throwable $e) {
            Log::warning('tool failed', ['tool' => $name, 'error' => $e->getMessage()]);

            return [
                'output' => 'Ошибка инструмента «'.$name.'»: '.$e->getMessage(),
                'summary' => 'ошибка',
                'ok' => false,
            ];
        }
    }
}