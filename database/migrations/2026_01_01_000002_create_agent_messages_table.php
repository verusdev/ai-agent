<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);                    // user|assistant|tool|system
            $table->longText('content')->nullable();
            $table->json('tool_calls')->nullable();        // что просил вызвать у модели
            $table->string('tool_name', 64)->nullable();   // результат какого инструмента
            $table->string('tool_call_id', 64)->nullable(); // связка с вызовом в ответе модели
            $table->unsignedInteger('tokens')->default(0);
            $table->timestamps();

            $table->index(['agent_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_messages');
    }
};