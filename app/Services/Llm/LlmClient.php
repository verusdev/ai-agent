<?php

namespace App\Services\Llm;

use App\Models\LlmProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Клиент OpenAI-совместимого chat-completions API.
 *
 * Один клиент покрывает и локальную Ollama, и LM Studio, и облачные
 * бесплатные API (Groq, OpenRouter, Google AI Studio) — у них одинаковый формат.
 */
class LlmClient
{
    /**
     * @param  array<int, array{role: string, content: string, tool_call_id?: string, name?: string}>  $messages
     * @param  array<int, array<string, mixed>>  $tools  схемы инструментов в формате OpenAI
     */
    public function chat(
        LlmProvider $provider,
        array $messages,
        array $tools = [],
        ?string $model = null,
    ): LlmReply {
        $model ??= $provider->model ?: array_key_first($provider->models ?? []) ?: 'llama3.1:8b';
        $useNativeTools = $provider->supports_tools && $tools !== [];

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.3,
        ];

        if ($useNativeTools) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        } else {
            $brief = array_map(fn (array $tool): array => [
                'name' => $tool['function']['name'],
                'description' => $tool['function']['description'],
            ], $tools);

            $payload['messages'][] = [
                'role' => 'system',
                'content' => ToolProtocol::instruction($brief),
            ];
        }

        try {
            $response = Http::withToken($provider->authHeader())
                ->acceptJson()
                ->timeout((int) config('agents.request_timeout', 180))
                ->post(rtrim($provider->base_url, '/').'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Не удалось связаться с моделью: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            $body = $response->json('error.message') ?: $response->body();

            throw new \RuntimeException('Модель вернула ошибку HTTP '.$response->status().': '.\Illuminate\Support\Str::limit($body, 400));
        }

        $choice = $response->json('choices.0.message') ?? [];
        $content = (string) ($choice['content'] ?? '');
        $tokens = (int) ($response->json('usage.total_tokens') ?? 0);

        $toolCalls = $useNativeTools
            ? $this->parseNativeToolCalls($choice['tool_calls'] ?? [])
            : [];

        // Модель может проигнорировать нативные tools и написать текстовый блок —
        // это обычное дело для бесплатных моделей, поэтому проверяем оба формата.
        if ($toolCalls === [] && $content !== '') {
            $parsed = ToolProtocol::parse($content);
            if ($parsed !== null) {
                $toolCalls = [$parsed];
                $content = ToolProtocol::strip($content);
            }
        }

        Log::debug('llm reply', ['model' => $model, 'tools' => count($toolCalls), 'tokens' => $tokens]);

        return new LlmReply(
            content: trim($content),
            toolCalls: $toolCalls,
            tokens: $tokens,
            usedNativeTools: $useNativeTools && $toolCalls !== [],
        );
    }

    /**
     * Проверка доступности провайдера — показываем в настройках живой статус.
     *
     * @return array{ok: bool, message: string, models: array<int, string>}
     */
    public function probe(LlmProvider $provider): array
    {
        try {
            $response = Http::withToken($provider->authHeader())
                ->acceptJson()
                ->timeout(10)
                ->get(rtrim($provider->base_url, '/').'/models');
        } catch (ConnectionException) {
            return ['ok' => false, 'message' => 'Сервер недоступен', 'models' => []];
        }

        if ($response->failed()) {
            return ['ok' => false, 'message' => 'HTTP '.$response->status(), 'models' => []];
        }

        $models = collect($response->json('data') ?? [])
            ->pluck('id')
            ->filter()
            ->take(40)
            ->values()
            ->all();

        return [
            'ok' => true,
            'message' => $models ? 'Доступно моделей: '.count($models) : 'Сервер отвечает',
            'models' => $models,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $raw
     * @return array<int, array{name: string, args: array<string, mixed>}>
     */
    private function parseNativeToolCalls(array $raw): array
    {
        $calls = [];

        foreach ($raw as $call) {
            $name = $call['function']['name'] ?? null;
            if (! $name) {
                continue;
            }

            $args = json_decode($call['function']['arguments'] ?? '{}', true);

            $calls[] = ['name' => $name, 'args' => is_array($args) ? $args : []];
        }

        return $calls;
    }
}