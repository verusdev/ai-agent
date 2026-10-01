<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Agent extends Model
{
    /** @use HasFactory<\Database\Factories\AgentFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid', 'title', 'goal', 'status', 'provider_id', 'model',
        'plan', 'iteration', 'tokens', 'error', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'plan' => 'array',
            'iteration' => 'integer',
            'tokens' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $agent): void {
            $agent->uuid ??= (string) Str::uuid();
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class)->orderBy('id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(AgentStep::class)->orderBy('id');
    }

    public function artifacts(): HasMany
    {
        return $this->hasMany(AgentArtifact::class)->orderBy('id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class, 'provider_id');
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['running', 'draft'], true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['done', 'failed', 'cancelled'], true);
    }

    /** Абсолютный путь рабочей папки задачи внутри приватного диска. */
    public function workspacePath(): string
    {
        $base = storage_path('app/private/'.config('agents.workspace.disk_path'));

        return $base.DIRECTORY_SEPARATOR.$this->uuid;
    }

    public static function statuses(): array
    {
        return [
            'draft' => ['label' => 'Черновик', 'color' => 'gray'],
            'running' => ['label' => 'Выполняется', 'color' => 'blue'],
            'done' => ['label' => 'Готово', 'color' => 'green'],
            'failed' => ['label' => 'Ошибка', 'color' => 'red'],
            'cancelled' => ['label' => 'Остановлена', 'color' => 'amber'],
        ];
    }
}