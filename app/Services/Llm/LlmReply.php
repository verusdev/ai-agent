<?php

namespace App\Services\Llm;

/**
 * Ответ модели: текст, запрошенные вызовы инструментов и расход токенов.
 */
class LlmReply
{
    /**
     * @param  array<int, array{name: string, args: array<string, mixed>}>  $toolCalls
     */
    public function __construct(
        public readonly string $content = '',
        public readonly array $toolCalls = [],
        public readonly int $tokens = 0,
        public readonly bool $usedNativeTools = false,
    ) {}

    public function wantsTools(): bool
    {
        return $this->toolCalls !== [];
    }
}