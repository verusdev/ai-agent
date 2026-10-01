<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class LlmProvider extends Model
{
    /** @use HasFactory<\Database\Factories\LlmProviderFactory> */
    use HasFactory;

    protected $fillable = [
        'slug', 'label', 'base_url', 'api_key', 'model',
        'supports_tools', 'is_default', 'is_active', 'models', 'note',
    ];

    protected function casts(): array
    {
        return [
            // Ключи шифруются на уровне приложения (APP_KEY) — в открытом виде их нигде нет.
            'api_key' => 'encrypted',
            'models' => 'array',
            'supports_tools' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** Провайдер готов к работе: ключ есть там, где он нужен. */
    public function isReady(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (str_starts_with($this->base_url, 'http://127.0.0.1') || str_starts_with($this->base_url, 'http://localhost')) {
            return true; // локальному серверу ключ не нужен
        }

        return filled($this->api_key);
    }

    public function requiresKey(): bool
    {
        return ! str_contains($this->base_url, '127.0.0.1') && ! str_contains($this->base_url, 'localhost');
    }

    public function maskedKey(): string
    {
        if (blank($this->api_key)) {
            return '—';
        }

        $key = (string) $this->api_key;

        return mb_substr($key, 0, 4).str_repeat('•', 8).mb_substr($key, -4);
    }

    /** Ключ в виде, пригодном для заголовка Authorization. */
    public function authHeader(): string
    {
        return 'Bearer '.($this->api_key ?: 'not-needed');
    }

    public static function defaults(): LlmProvider
    {
        return static::where('is_default', true)->first()
            ?? static::query()->orderBy('id')->first();
    }
}