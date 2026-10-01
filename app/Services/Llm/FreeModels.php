<?php

namespace App\Services\Llm;

/**
 * Пресеты бесплатных моделей: всё, что работает без оплаты —
 * локально через Ollama или на бесплатных лимитах облачных сервисов.
 */
class FreeModels
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function presets(): array
    {
        return [
            'ollama' => [
                'label' => 'Ollama — локально, без ключей и без интернета',
                'base_url' => 'http://127.0.0.1:11434/v1',
                'needs_key' => false,
                'api_key' => 'ollama',
                'supports_tools' => true,
                'note' => 'Запустите `ollama serve` и скачайте модель: `ollama pull llama3.1:8b`.',
                'models' => [
                    'llama3.1:8b' => 'Meta Llama 3.1 8B — универсальная',
                    'qwen2.5:7b-instruct' => 'Qwen 2.5 7B — хорошо держит русский',
                    'gemma2:9b' => 'Gemma 2 9B — сильная работа с текстом',
                    'llama3.1:70b' => 'Llama 3.1 70B — нужно много RAM',
                    'phi4' => 'Phi-4 — быстрая, хороша в рассуждениях',
                ],
            ],

            'groq' => [
                'label' => 'Groq — бесплатный лимит, очень быстро',
                'base_url' => 'https://api.groq.com/openai/v1',
                'needs_key' => true,
                'api_key' => null,
                'supports_tools' => true,
                'note' => 'Ключ бесплатный: console.groq.com/keys',
                'models' => [
                    'llama-3.3-70b-versatile' => 'Llama 3.3 70B — вызов инструментов',
                    'llama-3.1-8b-instant' => 'Llama 3.1 8B — быстрый и дешёвый',
                    'gemma2-9b-it' => 'Gemma 2 9B',
                    'qwen-2.5-32b' => 'Qwen 2.5 32B',
                    'deepseek-r1-distill-llama-70b' => 'DeepSeek R1 Distill — с рассуждением',
                ],
            ],

            'openrouter' => [
                'label' => 'OpenRouter — модели с пометкой :free',
                'base_url' => 'https://openrouter.ai/api/v1',
                'needs_key' => true,
                'api_key' => null,
                'supports_tools' => true,
                'note' => 'Ключ: openrouter.ai/keys. Работают только модели с суффиксом «:free».',
                'models' => [
                    'deepseek/deepseek-chat-v3-0324:free' => 'DeepSeek V3 — бесплатно',
                    'google/gemini-2.0-flash-exp:free' => 'Gemini 2.0 Flash',
                    'meta-llama/llama-3.3-70b-instruct:free' => 'Llama 3.3 70B',
                    'qwen/qwen-2.5-72b-instruct:free' => 'Qwen 2.5 72B',
                    'mistralai/mistral-small-3.1-24b-instruct:free' => 'Mistral Small 3.1',
                ],
            ],

            'gemini' => [
                'label' => 'Google AI Studio — бесплатный лимит Gemini',
                'base_url' => 'https://generativelanguage.googleapis.com/v1beta/openai',
                'needs_key' => true,
                'api_key' => null,
                'supports_tools' => true,
                'note' => 'Ключ: aistudio.google.com/apikey',
                'models' => [
                    'gemini-2.5-flash-lite' => 'Gemini 2.5 Flash Lite — очень быстро',
                    'gemini-2.0-flash' => 'Gemini 2.0 Flash',
                    'gemini-2.5-flash' => 'Gemini 2.5 Flash',
                    'gemini-2.5-pro' => 'Gemini 2.5 Pro — аккуратнее с инструментами',
                ],
            ],

            'lmstudio' => [
                'label' => 'LM Studio — локальный сервер на своём ПК',
                'base_url' => 'http://127.0.0.1:1234/v1',
                'needs_key' => false,
                'api_key' => 'lm-studio',
                'supports_tools' => true,
                'note' => 'В LM Studio включите локальный сервер на порту 1234.',
                'models' => [
                    'local-model' => 'Имя модели как в LM Studio',
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function categories(): array
    {
        return [
            'ollama' => 'Локально',
            'lmstudio' => 'Локально',
            'groq' => 'Облако',
            'openrouter' => 'Облако',
            'gemini' => 'Облако',
        ];
    }
}