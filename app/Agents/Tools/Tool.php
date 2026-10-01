<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Support\Workspace;

abstract class Tool
{
    /** Имя инструмента в латинице, через которое его вызывает модель. */
    abstract public function name(): string;

    /** Короткое описание для системного промпта. */
    abstract public function description(): string;

    /** JSON-схема параметров в формате OpenAI. */
    abstract public function schema(): array;

    /**
     * Выполнение инструмента.
     *
     * @param  array<string, mixed>  $args
     * @return array{output: string, summary: string, ok: bool}
     */
    abstract public function run(Agent $agent, array $args): array;

    /**
     * @return array<string, mixed>
     */
    public function toOpenAiSchema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->schema(),
            ],
        ];
    }

    /**
     * Заголовок шага в ленте действий.
     *
     * @param  array<string, mixed>  $args
     */
    public function title(array $args): string
    {
        return $this->description();
    }

    /**
     * Создать артефакт: файл в рабочей папке задачи + запись в базе.
     *
     * @return array{output: string, summary: string, ok: bool}
     */
    protected function makeArtifact(
        Agent $agent,
        string $filename,
        string $content,
        string $kind = 'document',
        string $mime = 'text/plain',
        ?string $summary = null,
    ): array {
        $workspace = app(Workspace::class);
        $absolute = $workspace->write($agent, $filename, $content);

        $artifact = $agent->artifacts()->updateOrCreate(
            ['path' => $filename],
            [
                'name' => $filename,
                'kind' => $kind,
                'mime' => $mime,
                'size' => filesize($absolute) ?: strlen($content),
                'summary' => $summary ?? mb_substr(trim(strip_tags($content)), 0, 160),
            ]
        );

        return [
            'output' => "Файл создан: {$artifact->name} ({$artifact->humanSize()})",
            'summary' => $artifact->name,
            'ok' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    protected function str(array $args, string $key, ?string $default = null): ?string
    {
        $value = $args[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<int, mixed>
     */
    protected function arr(array $args, string $key): array
    {
        $value = $args[$key] ?? [];

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * @param  array<string, mixed>  $args
     */
    protected function num(array $args, string $key, float $default = 0.0): float
    {
        $value = $args[$key] ?? $default;

        return is_numeric($value) ? (float) $value : $default;
    }
}