<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 200)->default('Новая задача');
            $table->text('goal');                                // что нужно сделать
            $table->string('status', 24)->default('draft');      // draft|running|done|failed|cancelled
            $table->foreignId('provider_id')->nullable();         // выбранный провайдер
            $table->string('model', 120)->nullable();
            $table->json('plan')->nullable();                     // план работ агента
            $table->unsignedSmallInteger('iteration')->default(0);
            $table->unsignedInteger('tokens')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};