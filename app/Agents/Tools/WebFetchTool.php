<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Support\TextExtractor;
use Illuminate\Support\Facades\Http;

class WebFetchTool extends Tool
{
    public function name(): string
    {
        return 'web_fetch';
    }

    public function description(): string
    {
        return 'Скачать страницу по URL и вернуть её текст без разметки.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => ['type' => 'string', 'description' => 'Полный адрес, включая https://' ],
            ],
            'required' => ['url'],
        ];
    }

    public function title(array $args): string
    {
        return 'Загрузка страницы '.$this->shortUrl($this->str($args, 'url', ''));
    }

    public function run(Agent $agent, array $args): array
    {
        $url = (string) $this->str($args, 'url', '');

        if (! preg_match('#^https?://#i', $url)) {
            throw new \InvalidArgumentException('Нужен полный адрес с http:// или https://');
        }

        $response = Http::withHeaders(['User-Agent' => (string) config('agents.web.user_agent')])
            ->timeout((int) config('agents.web.timeout', 20))
            ->get($url);

        if ($response->failed()) {
            throw new \RuntimeException('Страница вернула HTTP '.$response->status());
        }

        $text = TextExtractor::fromHtml($response->body(), (int) config('agents.web.max_chars', 8000));

        return [
            'output' => "Источник: {$url}\n\n".$text,
            'summary' => $this->shortUrl($url),
            'ok' => true,
        ];
    }

    private function shortUrl(string $url): string
    {
        return mb_substr((string) str_replace(['https://', 'http://'], '', $url), 0, 60);
    }
}
