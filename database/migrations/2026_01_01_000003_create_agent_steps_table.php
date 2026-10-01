<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('iteration')->default(0);
            $table->string('type', 24)->default('tool');   // plan|thought|tool|error|artifact
            $table->string('tool', 64)->nullable();
            $table->string('title', 255)->nullable();
            $table->json('input')->nullable();
            $table->longText('output')->nullable();
            $table->string('status', 16)->default('done');  // running|done|failed
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamps();

            $table->index(['agent_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_steps');
    }
};