<?php

namespace App\Support;

/**
 * Приведение HTML к читаемому тексту — так агент получает содержимое страницы.
 */
class TextExtractor
{
    public static function fromHtml(string $html, int $maxChars = 8000): string
    {
        $title = self::title($html);

        $html = preg_replace('#<(script|style|noscript|svg|iframe|nav|footer|header)[^>]*>.*?</\1>#is', ' ', $html) ?? $html;
        $html = preg_replace('#<!--.*?-->#s', ' ', $html) ?? $html;
        $html = preg_replace('#<br\s*/?>|</p>|</div>|</li>|</h[1-6]>#i', "\n", $html) ?? $html;
        $text = strip_tags($html);

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);

        if ($title !== '') {
            $text = $title."\n\n".$text;
        }

        return mb_strlen($text) > $maxChars
            ? mb_substr($text, 0, $maxChars)."\n… (страница обрезана)"
            : $text;
    }

    public static function title(string $html): string
    {
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }

        return '';
    }
}