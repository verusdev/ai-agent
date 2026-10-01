<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentArtifact extends Model
{
    protected $fillable = ['agent_id', 'name', 'path', 'kind', 'mime', 'size', 'summary'];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }

    protected $table = 'agent_artifacts';

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function humanSize(): string
    {
        $size = (float) $this->size;

        return match (true) {
            $size >= 1_048_576 => round($size / 1_048_576, 1).' МБ',
            $size >= 1024 => round($size / 1024, 1).' КБ',
            default => $size.' Б',
        };
    }

    public static function kinds(): array
    {
        return [
            'document' => 'Документ',
            'code' => 'Код',
            'chart' => 'График',
            'data' => 'Данные',
            'html' => 'Страница',
        ];
    }
}