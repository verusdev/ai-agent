<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();          // ollama | groq | openrouter | gemini | custom…
            $table->string('label');
            $table->string('base_url');
            $table->text('api_key')->nullable();           // шифруется моделью
            $table->string('model', 120)->nullable();
            $table->boolean('supports_tools')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('models')->nullable();             // доступные модели провайдера
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_providers');
    }
};