<?php

namespace App\Services\Llm;

/**
 * Запасной протокол вызова инструментов для моделей без нативного tool calling.
 *
 * Модель в ответе пишет блок:
 *   <tool_call>{"tool": "write_file", "args": {"path": "a.md", "content": "…"}}</tool_call>
 *
 * Так работают почти все бесплатные модели, включая те, где OpenAI-совместимый
 * эндпоинт не поддерживает поле tools.
 */
class ToolProtocol
{
    public const OPEN = '<tool_call>';

    public const CLOSE = '</tool_call>';

    /**
     * Инструкция для системного промпта.
     *
     * @param  array<int, array{name: string, description: string}>  $tools
     */
    public static function instruction(array $tools): string
    {
        $list = collect($tools)
            ->map(fn (array $tool): string => '- '.$tool['name'].' — '.$tool['description'])
            ->implode("\n");

        return <<<TXT
        Если нужно выполнить действие, ответь ОДНИМ блоком вида:

        <tool_call>
        {"tool": "имя_инструмента", "args": {"параметр": "значение"}}
        </tool_call>

        Доступные инструменты:
        {$list}

        Правила:
        - Один блок — один вызов. Если нужно несколько действий — вызови их по очереди.
        - В args пиши только параметры этого инструмента.
        - Результат вызова придёт отдельным сообщением с ролью tool.
        - Когда задача решена и новых действий не требуется — просто ответь текстом без блока.
        TXT;
    }

    /**
     * Разбирает первый найденный блок вызова.
     *
     * @return array{name: string, args: array<string, mixed>}|null
     */
    public static function parse(string $content): ?array
    {
        if (! str_contains($content, self::OPEN)) {
            return null;
        }

        if (! preg_match('/'.preg_quote(self::OPEN, '/').'(.*?)'.preg_quote(self::CLOSE, '/').'/s', $content, $matches)) {
            return null;
        }

        $json = trim($matches[1]);

        // Модели часто оборачивают JSON в блок ```json … ``` — снимаем обёртку.
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $json) ?? $json;

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return null;
        }

        $name = $decoded['tool'] ?? $decoded['name'] ?? null;
        if (! is_string($name) || $name === '') {
            return null;
        }

        $args = $decoded['args'] ?? $decoded['arguments'] ?? [];
        if (is_string($args)) {
            $args = json_decode($args, true) ?? [];
        }

        return ['name' => $name, 'args' => is_array($args) ? $args : []];
    }

    /** Убирает служебный блок из текста, который увидит пользователь. */
    public static function strip(string $content): string
    {
        return trim((string) preg_replace(
            '/'.preg_quote(self::OPEN, '/').'.*?'.preg_quote(self::CLOSE, '/').'/s',
            '',
            $content
        ));
    }
}