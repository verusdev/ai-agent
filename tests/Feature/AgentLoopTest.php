<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\LlmProvider;
use App\Services\AgentRunner;
use App\Services\Llm\ToolProtocol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentLoopTest extends TestCase
{
    use RefreshDatabase;

    private LlmProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = LlmProvider::factory()->create();
    }

    private function agent(string $goal = 'Собери отчёт по продажам'): Agent
    {
        $agent = Agent::factory()->create([
            'goal' => $goal,
            'provider_id' => $this->provider->id,
        ]);

        $agent->messages()->create(['role' => 'user', 'content' => $goal]);

        return $agent;
    }

    /**
     * Ответ модели с нативным вызовом инструмента.
     */
    private function toolCallReply(string $name, array $args): array
    {
        return [
            'choices' => [[
                'message' => [
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_1',
                        'type' => 'function',
                        'function' => [
                            'name' => $name,
                            'arguments' => json_encode($args, JSON_UNESCAPED_UNICODE),
                        ],
                    ]],
                ],
            ]],
            'usage' => ['total_tokens' => 120],
        ];
    }

    private function textReply(string $text): array
    {
        return [
            'choices' => [['message' => ['content' => $text]]],
            'usage' => ['total_tokens' => 80],
        ];
    }

    public function test_agent_executes_plan_document_and_finishes(): void
    {
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->toolCallReply('update_plan', [
                    'steps' => [
                        ['title' => 'Собрать данные', 'status' => 'done'],
                        ['title' => 'Сделать документ', 'status' => 'doing'],
                    ],
                ]))
                ->push($this->toolCallReply('create_document', [
                    'title' => 'Отчёт по продажам',
                    'format' => 'md',
                    'content' => "# Отчёт\n\nВыручка выросла на 12%.",
                ]))
                ->push($this->textReply('Готово: отчёт собран и сохранён.')),
        ]);

        $agent = $this->agent();
        $runner = app(AgentRunner::class);

        while ($runner->hasMore($agent)) {
            if (! $runner->step($agent)) {
                break;
            }
            $agent->refresh();
        }

        $agent->refresh();

        $this->assertSame('done', $agent->status);
        $this->assertSame(3, $agent->iteration);
        $this->assertSame(320, $agent->tokens);

        $this->assertCount(2, $agent->plan);
        $this->assertSame('Собрать данные', $agent->plan[0]['title']);
        $this->assertSame('done', $agent->plan[0]['status']);

        $this->assertCount(2, $agent->steps);
        $this->assertSame('update_plan', $agent->steps[0]->tool);
        $this->assertSame('done', $agent->steps[0]->status);
        $this->assertSame('create_document', $agent->steps[1]->tool);

        $this->assertCount(1, $agent->artifacts);
        $artifact = $agent->artifacts->first();
        $this->assertSame('otcet-po-prodazam.md', $artifact->name);
        $this->assertStringContainsString('Выручка выросла', file_get_contents($agent->workspacePath().'/otcet-po-prodazam.md'));
    }

    public function test_agent_works_with_text_tool_protocol(): void
    {
        // Модель без нативных tools пишет служебный блок обычным текстом.
        $this->provider->update(['supports_tools' => false]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->textReply(
                    ToolProtocol::OPEN.'{"tool": "calculate", "args": {"expression": "1200*3+450"}}'.ToolProtocol::CLOSE
                ))
                ->push($this->textReply('Итого 4050.')),
        ]);

        $agent = $this->agent('Посчитай стоимость');
        $runner = app(AgentRunner::class);

        while ($runner->hasMore($agent)) {
            if (! $runner->step($agent)) {
                break;
            }
            $agent->refresh();
        }

        $agent->refresh();

        $this->assertSame('done', $agent->status, 'Ошибка агента: '.($agent->error ?? 'нет').' | '.$agent->steps->first()->output);
        $this->assertSame('calculate', $agent->steps->first()->tool);
        $this->assertStringContainsString('4 050', $agent->steps->first()->output);
    }

    public function test_tool_failures_are_reported_to_the_model(): void
    {
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->toolCallReply('write_file', ['path' => '../escape.md', 'content' => 'взлом']))
                ->push($this->textReply('Понял, не могу писать вне папки.')),
        ]);

        $agent = $this->agent();
        $runner = app(AgentRunner::class);

        while ($runner->hasMore($agent)) {
            if (! $runner->step($agent)) {
                break;
            }
            $agent->refresh();
        }

        $agent->refresh();

        $this->assertSame('failed', $agent->steps->first()->status);
        $this->assertStringContainsString('Выход за пределы', $agent->steps->first()->output);
        $this->assertSame('done', $agent->status, 'Агент должен продолжить работу после ошибки инструмента');
    }

    public function test_unknown_tool_returns_helpful_message(): void
    {
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->toolCallReply('delete_everything', []))
                ->push($this->textReply('Инструмента нет, сделаю иначе.')),
        ]);

        $agent = $this->agent();
        $runner = app(AgentRunner::class);
        $runner->step($agent);

        $this->assertStringContainsString('не найден', $agent->steps->first()->output);
        $this->assertStringContainsString('update_plan', $agent->steps->first()->output);
    }

    public function test_chart_artifact_is_created(): void
    {
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->toolCallReply('make_chart', [
                    'title' => 'Выручка по месяцам',
                    'type' => 'bar',
                    'labels' => ['Янв', 'Фев', 'Мар'],
                    'series' => [['name' => 'Выручка', 'data' => [10, 20, 15]]],
                ]))
                ->push($this->textReply('График готов.')),
        ]);

        $agent = $this->agent('Построй график');
        $runner = app(AgentRunner::class);

        while ($runner->hasMore($agent)) {
            if (! $runner->step($agent)) {
                break;
            }
            $agent->refresh();
        }

        $artifact = $agent->artifacts()->first();
        $this->assertNotNull($artifact);
        $this->assertSame('chart', $artifact->kind);
        $this->assertStringContainsString('<svg', file_get_contents($agent->workspacePath().'/'.$artifact->path));
    }

    public function test_agent_fails_when_provider_has_no_key(): void
    {
        $provider = LlmProvider::factory()->create(['base_url' => 'https://api.example.test/v1', 'api_key' => null]);

        $agent = Agent::factory()->create(['provider_id' => $provider->id]);
        $agent->messages()->create(['role' => 'user', 'content' => 'тест']);

        app(AgentRunner::class)->step($agent);

        $agent->refresh();

        $this->assertSame('failed', $agent->status);
        $this->assertStringContainsString('API-ключ', $agent->error);
    }

    public function test_agent_stops_after_max_iterations(): void
    {
        config(['agents.max_iterations' => 2]);

        Http::fake(['*/chat/completions' => Http::sequence()
            ->push($this->toolCallReply('list_files', []))
            ->push($this->toolCallReply('list_files', []))
            ->push($this->toolCallReply('list_files', [])),
        ]);

        $agent = $this->agent();
        $runner = app(AgentRunner::class);
        $runner->run($agent);

        $agent->refresh();

        $this->assertSame(2, $agent->iteration);
        $this->assertSame('done', $agent->status);
    }

    public function test_llm_error_is_captured_as_failed_step(): void
    {
        Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'quota exceeded']], 429)]);

        $agent = $this->agent();
        app(AgentRunner::class)->step($agent);

        $agent->refresh();

        $this->assertSame('failed', $agent->status);
        $this->assertStringContainsString('429', $agent->error);
        $this->assertSame('error', $agent->steps->first()->type);
    }

    public function test_shell_tool_is_disabled_by_default(): void
    {
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->toolCallReply('run_code', ['command' => 'python3 -c "print(1)"']))
                ->push($this->textReply('Команды отключены.')),
        ]);

        $agent = $this->agent();
        $runner = app(AgentRunner::class);

        while ($runner->hasMore($agent)) {
            if (! $runner->step($agent)) {
                break;
            }
            $agent->refresh();
        }

        $this->assertStringContainsString('выключено', $agent->steps->first()->output);

        Http::assertNotSent(function (Request $request): bool {
            return false;
        });
    }
}