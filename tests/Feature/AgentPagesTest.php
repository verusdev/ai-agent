<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\AgentArtifact;
use App\Models\LlmProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AgentPagesTest extends TestCase
{
    use RefreshDatabase;

    private LlmProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = LlmProvider::factory()->create();
    }

    public function test_index_redirects_from_root(): void
    {
        $this->get('/')->assertRedirect('/agents');
    }

    public function test_pages_render(): void
    {
        $agent = Agent::factory()->create(['provider_id' => $this->provider->id]);
        $agent->messages()->create(['role' => 'user', 'content' => 'Привет']);
        $agent->steps()->create(['title' => 'Тестовый шаг', 'output' => 'ок']);
        $agent->artifacts()->create(['name' => 'a.md', 'path' => 'a.md', 'kind' => 'document', 'size' => 10]);

        $this->get('/agents')->assertOk()->assertSee('Новая задача');
        $this->get("/agents/{$agent->id}")->assertOk()->assertSee('Привет');
        $this->get('/providers')->assertOk()->assertSee('Тестовый провайдер');
    }

    public function test_task_can_be_created(): void
    {
        $this->withoutExceptionHandling();

        $response = $this->post('/agents', [
            'goal' => 'Собери лендинг из пяти экранов',
            'provider_id' => $this->provider->id,
        ]);

        $agent = Agent::firstOrFail();

        $response->assertRedirect(route('agents.show', $agent));
        $this->assertSame('Собери лендинг из пяти экранов', $agent->goal);
        $this->assertSame('draft', $agent->status);
        $this->assertDatabaseHas('agent_messages', ['agent_id' => $agent->id, 'role' => 'user']);
    }

    public function test_task_creation_validates_goal(): void
    {
        $this->post('/agents', ['goal' => 'а'])->assertSessionHasErrors('goal');
    }

    public function test_tick_returns_state_and_runs_a_step(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'Ответ готов']]],
                'usage' => ['total_tokens' => 42],
            ]),
        ]);

        $agent = Agent::factory()->create(['provider_id' => $this->provider->id]);
        $agent->messages()->create(['role' => 'user', 'content' => 'Привет']);

        $payload = $this->postJson("/agents/{$agent->id}/tick")->json();

        $this->assertSame('done', $payload['status']);
        $this->assertFalse($payload['has_more']);
        $this->assertSame(42, $payload['tokens']);
        $this->assertNotEmpty($payload['messages']);
    }

    public function test_user_can_add_a_message_to_running_task(): void
    {
        $agent = Agent::factory()->finished()->create(['provider_id' => $this->provider->id]);

        $this->post("/agents/{$agent->id}/messages", ['content' => 'Добавь раздел'])
            ->assertRedirect();

        $this->assertDatabaseHas('agent_messages', ['agent_id' => $agent->id, 'role' => 'user']);
        $this->assertSame('draft', $agent->refresh()->status, 'Задача должна снова стать готовой к запуску');
    }

    public function test_artifact_is_served_and_downloaded(): void
    {
        $agent = Agent::factory()->create(['provider_id' => $this->provider->id]);

        $path = $agent->workspacePath();
        @mkdir($path, 0775, true);
        file_put_contents($path.'/report.md', '# Отчёт');

        $artifact = $agent->artifacts()->create([
            'name' => 'report.md', 'path' => 'report.md', 'kind' => 'document',
            'mime' => 'text/markdown', 'size' => 9,
        ]);

        $this->get("/artifacts/{$artifact->id}")->assertOk()->assertSee('# Отчёт');

        $this->get("/artifacts/{$artifact->id}/download")
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename="report.md"');
    }

    public function test_provider_settings_are_saved_and_key_is_not_returned(): void
    {
        $this->put("/providers/{$this->provider->id}", [
            'label' => 'Groq',
            'base_url' => 'https://api.groq.com/openai/v1',
            'api_key' => 'gsk-secret-value',
            'model' => 'llama-3.3-70b-versatile',
            'supports_tools' => '1',
            'is_active' => '1',
            'is_default' => '1',
        ])->assertRedirect();

        $provider = $this->provider->refresh();

        $this->assertSame('gsk-secret-value', $provider->api_key, 'Ключ должен расшифровываться обратно');
        $this->assertStringContainsString('••••••••', $provider->maskedKey());
        $this->assertStringNotContainsString('gsk-secret-value', $provider->maskedKey());

        $this->get('/providers')->assertOk()->assertDontSee('gsk-secret-value');
    }

    public function test_provider_can_be_deleted(): void
    {
        LlmProvider::factory()->create(['is_default' => false]);

        $this->delete("/providers/{$this->provider->id}")->assertRedirect();

        $this->assertDatabaseMissing('llm_providers', ['id' => $this->provider->id]);
    }

    public function test_probe_reports_unreachable_provider(): void
    {
        Http::fake(['*/models' => Http::response(['error' => 'nope'], 401)]);

        $payload = $this->postJson("/providers/{$this->provider->id}/probe")->json();

        $this->assertFalse($payload['ok']);
        $this->assertSame('HTTP 401', $payload['message']);
    }

    public function test_preset_can_be_added(): void
    {
        $this->provider->delete();

        $this->post('/providers/preset', ['slug' => 'groq'])->assertRedirect();

        $this->assertDatabaseHas('llm_providers', ['slug' => 'groq', 'is_default' => true]);
    }
}