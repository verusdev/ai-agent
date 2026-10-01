<?php

namespace App\Console\Commands;

use App\Models\Agent;
use App\Services\AgentRunner;
use Illuminate\Console\Command;

class RunAgentCommand extends Command
{
    protected $signature = 'agent:run {agent? : ID или UUID задачи; без аргумента — последняя задача}';

    protected $description = 'Выполнить задачу ИИ-агента из командной строки';

    public function handle(AgentRunner $runner): int
    {
        $agent = $this->argument('agent')
            ? Agent::where('uuid', $this->argument('agent'))->orWhere('id', $this->argument('agent'))->first()
            : Agent::latest('id')->first();

        if (! $agent) {
            $this->error('Задача не найдена.');

            return self::FAILURE;
        }

        $this->info("Задача #{$agent->id}: {$agent->title}");

        while ($runner->hasMore($agent)) {
            if (! $runner->step($agent)) {
                break;
            }

            $agent->refresh();
            $this->line("  итерация {$agent->iteration} · статус {$agent->status}");
        }

        if ($agent->refresh()->status === 'running') {
            $agent->update(['status' => 'done', 'finished_at' => now()]);
        }

        $this->info("Готово: {$agent->status}, шагов {$agent->steps()->count()}, артефактов {$agent->artifacts()->count()}");

        if ($agent->error) {
            $this->error($agent->error);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}