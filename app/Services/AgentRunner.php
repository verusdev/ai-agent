<?php

namespace App\Services;

use App\Agents\ToolRegistry;
use App\Models\Agent;
use App\Models\AgentMessage;
use App\Models\AgentStep;
use App\Models\LlmProvider;
use App\Services\Llm\LlmClient;
use App\Services\Llm\ToolProtocol;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Агентный цикл: план → вызов модели → выполнение инструментов → следующий шаг.
 *
 * Один запуск step() = одна итерация (один вызов модели + её инструменты).
 * Так агент работает даже без очереди: браузер просто дёргает tick-эндпоинт.
 */
class AgentRunner
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly ToolRegistry $tools,
    ) {}

    /**
     * Есть ли смысл продолжать работу агента.
     */
    public function hasMore(Agent $agent): bool
    {
        return ! $agent->isFinished() && $agent->iteration < (int) config('agents.max_iterations', 12);
    }

    /**
     * Одна итерация агента. Возвращает false, когда работа закончена.
     */
    public function step(Agent $agent): bool
    {
        if ($agent->status === 'draft') {
            $agent->update(['status' => 'running', 'started_at' => now(), 'error' => null]);
        }

        $agent->increment('iteration');
        $agent->refresh();

        $tools = $this->tools->schemas();

        try {
            $provider = $this->resolveProvider($agent);
            $reply = $this->llm->chat($provider, $this->buildMessages($agent, $provider, $tools), $tools, $agent->model);
        } catch (\Throwable $e) {
            $this->fail($agent, $e);

            return false;
        }

        $agent->increment('tokens', $reply->tokens);

        $calls = collect($reply->toolCalls)->values()->map(function (array $call, int $index) use ($agent): array {
            $call['id'] = 'call_'.$agent->iteration.'_'.$index;

            return $call;
        })->all();

        $agent->messages()->create([
            'role' => 'assistant',
            'content' => $reply->content,
            'tool_calls' => $calls,
            'tokens' => $reply->tokens,
        ]);

        if ($calls === []) {
            $this->finish($agent);

            return false;
        }

        foreach ($calls as $index => $call) {
            $tool = $this->tools->all()[$call['name']] ?? null;

            $step = AgentStep::create([
                'agent_id' => $agent->id,
                'iteration' => $agent->iteration,
                'type' => $call['name'] === 'update_plan' ? 'plan' : 'tool',
                'tool' => $call['name'],
                'title' => $tool?->title($call['args']) ?? $call['name'],
                'input' => $call['args'],
                'status' => 'running',
            ]);

            $started = microtime(true);
            $result = $this->tools->run($call['name'], $call['args'], $agent);

            $step->update([
                'output' => mb_substr((string) $result['output'], 0, 20_000),
                'status' => $result['ok'] ? 'done' : 'failed',
                'duration_ms' => (int) ((microtime(true) - $started) * 1000),
            ]);

            $agent->messages()->create([
                'role' => 'tool',
                'tool_name' => $call['name'],
                'tool_call_id' => $call['id'],
                'content' => $this->truncate((string) $result['output']),
            ]);
        }

        return $this->hasMore($agent->refresh());
    }

    /**
     * Прогнать задачу до конца — используется CLI-командой и тестами.
     */
    public function run(Agent $agent): bool
    {
        $guard = 0;

        while ($this->hasMore($agent) && $guard++ < (int) config('agents.max_iterations', 12) + 1) {
            if (! $this->step($agent)) {
                break;
            }
        }

        if ($agent->refresh()->status === 'running') {
            $this->finish($agent);
        }

        return $agent->status === 'done';
    }

    /**
     * Сообщения для модели: системный промпт + последние сообщения задачи.
     *
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<int, array<string, mixed>>
     */
    public function buildMessages(Agent $agent, LlmProvider $provider, array $tools = []): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemPrompt($agent)]];

        $history = $agent->messages()
            ->orderByDesc('id')
            ->limit((int) config('agents.max_context_messages', 40))
            ->get()
            ->reverse()
            ->values();

        foreach ($history as $message) {
            /** @var AgentMessage $message */
            $messages[] = match ($message->role) {
                'assistant' => $this->assistantMessage($message, $provider),
                'tool' => $this->toolMessage($message, $provider),
                default => ['role' => $message->role, 'content' => (string) $message->content],
            };
        }

        return $messages;
    }

    public function systemPrompt(Agent $agent): string
    {
        $tools = $this->tools->brief();
        $list = collect($tools)->map(fn (array $t) => '- '.$t['name'].' — '.$t['description'])->implode("\n");

        return <<<TXT
        Ты — ИИ-агент, который решает задачи пошагово, а не просто отвечает текстом.

        ЗАДАЧА ОТ ПОЛЬЗОВАТЕЛЯ:
        {$agent->goal}

        РАБОЧАЯ ПАПКА ЗАДАЧИ:
        Все файлы, которые ты создаёшь, клади в неё через инструменты файлов.
        Пути относительные, без ведущего слеша.

        ИНСТРУМЕНТЫ:
        {$list}

        КАК РАБОТАТЬ:
        1. Начни с update_plan — разбей задачу на 3–7 шагов и поставь первый статус doing.
        2. Выполняй шаги по одному, обновляя план (todo → doing → done).
        3. Данные из интернета получай через web_search и web_fetch; выдумывать источники нельзя.
        4. Итоговый результат оформляй артефактом: create_document, make_chart или write_file.
        5. Когда задача решена — напиши короткий итог текстом и не вызывай больше инструментов.

        ОТВЕЧАЙ ПО-РУССКИ. Пиши конкретно и по делу, без воды.
        TXT;
    }

    private function assistantMessage(AgentMessage $message, LlmProvider $provider): array
    {
        $calls = $message->tool_calls ?? [];

        if ($calls === [] || ! $provider->supports_tools) {
            $text = (string) $message->content;

            if ($calls !== []) {
                $text .= "\n\n[вызваны инструменты] ".collect($calls)
                    ->map(fn (array $c) => $c['name'].'('.json_encode($c['args'] ?? [], JSON_UNESCAPED_UNICODE).')')
                    ->implode('; ');
            }

            return ['role' => 'assistant', 'content' => $text];
        }

        return [
            'role' => 'assistant',
            'content' => $message->content,
            'tool_calls' => collect($calls)->map(fn (array $c) => [
                'id' => $c['id'] ?? 'call_'.($c['name'].'_'.$message->id),
                'type' => 'function',
                'function' => [
                    'name' => $c['name'],
                    'arguments' => json_encode($c['args'] ?? [], JSON_UNESCAPED_UNICODE),
                ],
            ])->all(),
        ];
    }

    private function toolMessage(AgentMessage $message, LlmProvider $provider): array
    {
        if (! $provider->supports_tools) {
            return ['role' => 'user', 'content' => 'Результат инструмента '.$message->tool_name.': '.$message->content];
        }

        return [
            'role' => 'tool',
            'tool_call_id' => $message->tool_call_id ?: 'call_'.$message->id,
            'content' => (string) $message->content,
        ];
    }

    private function resolveProvider(Agent $agent): LlmProvider
    {
        $provider = $agent->provider_id
            ? LlmProvider::find($agent->provider_id)
            : LlmProvider::defaults();

        if (! $provider) {
            throw new RuntimeException('Нет настроенных моделей. Откройте «Настройки моделей».');
        }

        if (! $provider->isReady()) {
            throw new RuntimeException(
                $provider->requiresKey()
                    ? "Провайдер «{$provider->label}» требует API-ключ."
                    : "Провайдер «{$provider->label}» недоступен."
            );
        }

        return $provider;
    }

    private function finish(Agent $agent): void
    {
        $agent->update(['status' => 'done', 'finished_at' => now()]);
    }

    private function fail(Agent $agent, \Throwable $e): void
    {
        Log::error('agent failed', ['agent' => $agent->id, 'error' => $e->getMessage()]);

        $agent->steps()->create([
            'iteration' => $agent->iteration,
            'type' => 'error',
            'title' => 'Ошибка модели',
            'output' => $e->getMessage()."\n".str_replace(base_path().'\\', '', $e->getFile()).':'.$e->getLine(),
            'status' => 'failed',
        ]);

        $agent->update(['status' => 'failed', 'error' => $e->getMessage(), 'finished_at' => now()]);
    }

    private function truncate(string $text): string
    {
        $max = (int) config('agents.max_tool_output', 6000);

        return mb_strlen($text) > $max ? mb_substr($text, 0, $max).'…' : $text;
    }
}