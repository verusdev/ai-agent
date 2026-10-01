<?php

namespace App\Agents\Tools;

use App\Models\Agent;

/**
 * Выполнение кода на том же хосте. Выключено по умолчанию
 * (config('agents.shell.enabled')) — включайте только на доверенной машине.
 */
class RunCodeTool extends Tool
{
    public function name(): string
    {
        return 'run_code';
    }

    public function description(): string
    {
        return 'Запустить команду на сервере (python / php / node). Выполняется только если это явно разрешено в настройках.';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'command' => ['type' => 'string', 'description' => 'Команда целиком, например: python3 sum.py' ],
            ],
            'required' => ['command'],
        ];
    }

    public function title(array $args): string
    {
        return 'Команда: '.mb_substr((string) $this->str($args, 'command', ''), 0, 60);
    }

    public function run(Agent $agent, array $args): array
    {
        if (! config('agents.shell.enabled')) {
            throw new \RuntimeException('Выполнение команд выключено. Включите AGENT_SHELL_ENABLED=true, если это нужно.');
        }

        $command = trim((string) $this->str($args, 'command', ''));

        if ($command === '') {
            throw new \InvalidArgumentException('Пустая команда.');
        }

        $allowed = (array) config('agents.shell.allowed', []);
        $binary = strtok($command, " \t");

        if (! in_array($binary, $allowed, true)) {
            throw new \RuntimeException('Команда «'.$binary.'» не разрешена. Разрешено: '.implode(', ', $allowed));
        }

        $cwd = $agent->workspacePath();
        if (! is_dir($cwd)) {
            mkdir($cwd, 0775, true);
        }

        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open(
            $command,
            $descriptors,
            $pipes,
            $cwd,
            null,
            ['bypass_shell' => true]
        );

        if (! is_resource($process)) {
            throw new \RuntimeException('Не удалось запустить процесс.');
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $timeout = (int) config('agents.shell.timeout', 20);
        $start = microtime(true);
        $stdout = $stderr = '';
        $maxOutput = (int) config('agents.max_tool_output', 6000);

        while (true) {
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            $status = proc_get_status($process);

            if (! $status['running']) {
                break;
            }

            if (microtime(true) - $start > $timeout) {
                proc_terminate($process, 9);
                throw new \RuntimeException('Команда превысила лимит '.$timeout.' с и была остановлена.');
            }

            usleep(50_000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);

        $output = trim($stdout).($stderr !== '' ? "\n[stderr]\n".trim($stderr) : '');
        $output = mb_strlen($output) > $maxOutput ? mb_substr($output, 0, $maxOutput).'…' : $output;

        return [
            'output' => $output !== '' ? $output : '(пустой вывод)',
            'summary' => 'код выхода '.$code,
            'ok' => $code === 0,
        ];
    }

}
