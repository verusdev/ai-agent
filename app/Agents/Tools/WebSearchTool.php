<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use App\Support\TextExtractor;
use Illuminate\Support\Facades\Http;

/**
 * Поиск без API-ключа через HTML-выдачу DuckDuckGo.
 */
class WebSearchTool extends Tool
{
    public function name(): string
    {
        return 'web_search';
    }

    public function description(): string
    {
        return 'Поиск в интернете по запросу (без API-ключа). Возвращает список найденных страниц и краткие выдержки.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Поисковый запрос' ],
                'limit' => ['type' => 'integer', 'description' => 'Сколько результатов вернуть (по умолчанию 5)' ],
            ],
            'required' => ['query'],
        ];
    }

    public function title(array $args): string
    {
        return 'Поиск: '.$this->str($args, 'query', '');
    }

    public function run(Agent $agent, array $args): array
    {
        $query = (string) $this->str($args, 'query', '');
        $limit = (int) ($args['limit'] ?? 5);
        $limit = max(1, min($limit, 10));

        $response = Http::withHeaders(['User-Agent' => (string) config('agents.web.user_agent')])
            ->timeout((int) config('agents.web.timeout', 20))
            ->get('https://lite.duckduckgo.com/lite/', ['q' => $query]);

        if ($response->failed()) {
            throw new \RuntimeException('Поиск недоступен, HTTP '.$response->status());
        }

        $results = $this->parse($response->body(), $limit);

        if ($results === []) {
            $fallback = TextExtractor::fromHtml($response->body(), 2000);

            return [
                'output' => 'Ничего не найдено. Фрагмент страницы поиска:\n'.$fallback,
                'summary' => '0 результатов',
                'ok' => false,
            ];
        }

        $lines = collect($results)->map(function (array $r, int $i): string {
            return ($i + 1).'. '.$r['title']."\n   ".$r['url'].($r['snippet'] !== '' ? "\n   ".$r['snippet'] : '');
        })->implode("\n\n");

        return [
            'output' => "Результаты по запросу «{$query}»:\n\n".$lines,
            'summary' => count($results).' результат(ов)',
            'ok' => true,
        ];
    }

    /**
     * @return array<int, array{title: string, url: string, snippet: string}>
     */
    private function parse(string $html, int $limit): array
    {
        preg_match_all(
            '#<a[^>]+class="result-link"[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is',
            $html,
            $links,
            PREG_SET_ORDER
        );

        preg_match_all('#<td class="result-snippet">(.*?)</td>#is', $html, $snippets, PREG_SET_ORDER);
        preg_match_all('#class="result-url"[^>]*>(.*?)</a>#is', $html, $urls, PREG_SET_ORDER);

        $results = [];

        foreach (array_slice($links, 0, $limit) as $i => $match) {
            $url = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // DuckDuckGo перенаправляет через /l/?uddg=… — достаём настоящий адрес.
            if (str_contains($url, 'uddg=')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $params);
                $url = $params['uddg'] ?? $url;
            }

            $results[] = [
                'title' => trim(html_entity_decode(strip_tags($match[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
                'url' => $url,
                'snippet' => trim(html_entity_decode(strip_tags($snippets[$i][1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            ];
        }

        return $results;
    }
}
