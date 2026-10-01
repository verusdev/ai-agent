<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStep extends Model
{
    protected $fillable = [
        'agent_id', 'iteration', 'type', 'tool', 'title', 'input', 'output', 'status', 'duration_ms',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'duration_ms' => 'integer',
            'iteration' => 'integer',
        ];
    }

    protected $table = 'agent_steps';

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /** Короткая подпись результата для ленты действий. */
    public function preview(int $limit = 160): string
    {
        $text = trim((string) $this->output);

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit).'…' : $text;
    }
}