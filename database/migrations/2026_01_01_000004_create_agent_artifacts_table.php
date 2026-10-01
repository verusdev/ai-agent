<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);                   // имя файла
            $table->string('path', 400);                   // путь относительно рабочей папки задачи
            $table->string('kind', 24)->default('document'); // document|code|chart|data|html|image
            $table->string('mime', 80)->default('text/plain');
            $table->unsignedBigInteger('size')->default(0);
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_artifacts');
    }
};