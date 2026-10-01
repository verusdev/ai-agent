<?php

namespace App\Agents\Tools;

use App\Models\Agent;
use Illuminate\Support\Str;

class CreateDocumentTool extends Tool
{
    private const MIME = [
        'md' => ['text/markdown', 'document'],
        'html' => ['text/html', 'html'],
        'csv' => ['text/csv', 'data'],
        'txt' => ['text/plain', 'document'],
        'json' => ['application/json', 'data'],
    ];

    public function name(): string
    {
        return 'create_document';
    }

    public function description(): string
    {
        return 'Создать готовый документ-артефакт (markdown, html, csv, txt, json) — попадёт в галерею результатов.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'Название документа, используется как имя файла'],
                'format' => ['type' => 'string', 'enum' => array_keys(self::MIME)],
                'content' => ['type' => 'string', 'description' => 'Содержимое документа'],
            ],
            'required' => ['title', 'content'],
        ];
    }

    public function title(array $args): string
    {
        return 'Документ «'.$this->str($args, 'title', 'без названия').'»';
    }

    public function run(Agent $agent, array $args): array
    {
        $title = (string) $this->str($args, 'title', 'document');
        $format = strtolower((string) $this->str($args, 'format', 'md'));
        $format = array_key_exists($format, self::MIME) ? $format : 'md';
        $content = (string) ($args['content'] ?? '');

        if (trim($content) === '') {
            throw new \InvalidArgumentException('Содержимое документа пустое.');
        }

        $extension = $format === 'markdown' ? 'md' : $format;
        $filename = Str::slug($title).'.'.$extension;
        [$mime, $kind] = self::MIME[$format];

        return $this->makeArtifact($agent, $filename, $content, $kind, $mime, $title);
    }
}