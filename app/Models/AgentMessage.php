<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMessage extends Model
{
    protected $fillable = ['agent_id', 'role', 'content', 'tool_calls', 'tool_name', 'tool_call_id', 'tokens'];

    protected function casts(): array
    {
        return [
            'tool_calls' => 'array',
            'tokens' => 'integer',
        ];
    }

    protected $table = 'agent_messages';

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }
}