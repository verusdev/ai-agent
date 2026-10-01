<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\LlmProvider;
use App\Services\AgentRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AgentController extends Controller
{
    public function __construct(private readonly AgentRunner $runner) {}

    public function index(): View
    {
        $agents = Agent::with('provider')
            ->withCount(['steps', 'artifacts'])
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return view('agents.index', [
            'agents' => $agents,
            'providers' => $this->providers(),
        ]);
    }

    public function show(Agent $agent): View
    {
        $agent->load(['provider', 'steps', 'artifacts']);

        return view('agents.show', [
            'agent' => $agent,
            'messages' => $agent->messages()->get(),
            'providers' => $this->providers(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'goal' => ['required', 'string', 'min:5', 'max:4000'],
            'provider_id' => ['nullable', 'exists:llm_providers,id'],
            'model' => ['nullable', 'string', 'max:120'],
        ], [], ['goal' => 'Задача']);

        $provider = $data['provider_id'] ?? LlmProvider::defaults()?->id;

        $agent = Agent::create([
            'title' => mb_substr(trim(preg_split('/[\r\n]+/', $data['goal'])[0] ?? $data['goal']), 0, 120),
            'goal' => $data['goal'],
            'provider_id' => $provider,
            'model' => ($data['model'] ?? '') ?: null,
            'status' => 'draft',
        ]);

        $agent->messages()->create(['role' => 'user', 'content' => $data['goal']]);

        return redirect()->route('agents.show', $agent);
    }

    /** Добавить сообщение пользователя к существующей задаче. */
    public function addMessage(Request $request, Agent $agent): RedirectResponse
    {
        $data = $request->validate([
            'content' => ['required', 'string', 'min:2', 'max:4000'],
        ], [], ['content' => 'Сообщение']);

        $agent->messages()->create(['role' => 'user', 'content' => $data['content']]);
        $agent->update(['status' => $agent->isFinished() ? 'draft' : $agent->status, 'error' => null]);

        return back();
    }

    /**
     * Одна итерация агента. Браузер дёргает её, пока has_more = true.
     */
    public function tick(Agent $agent): JsonResponse
    {
        if (! $agent->isFinished() && $this->runner->hasMore($agent)) {
            $this->runner->step($agent);
        }

        return $this->events($agent);
    }

    /** Снимок состояния задачи для интерфейса. */
    public function events(Agent $agent): JsonResponse
    {
        $agent->load('provider');

        $messages = $agent->messages()->orderBy('id')->get()->map(fn ($m) => [
            'id' => $m->id,
            'role' => $m->role,
            'content' => (string) $m->content,
            'tool' => $m->tool_name,
        ]);

        $steps = $agent->steps()->orderBy('id')->get()->map(fn ($s) => [
            'id' => $s->id,
            'type' => $s->type,
            'tool' => $s->tool,
            'title' => $s->title,
            'output' => $s->preview(400),
            'status' => $s->status,
            'duration' => $s->duration_ms,
            'at' => $s->created_at->format('H:i:s'),
        ]);

        $artifacts = $agent->artifacts()->orderBy('id')->get()->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'kind' => $a->kind,
            'size' => $a->humanSize(),
            'url' => route('artifacts.show', $a),
        ]);

        return response()->json([
            'status' => $agent->status,
            'status_label' => Agent::statuses()[$agent->status]['label'] ?? $agent->status,
            'iteration' => $agent->iteration,
            'tokens' => $agent->tokens,
            'error' => $agent->error,
            'plan' => $agent->plan ?? [],
            'messages' => $messages,
            'steps' => $steps,
            'artifacts' => $artifacts,
            'has_more' => $this->runner->hasMore($agent),
        ]);
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $agent->delete();

        return redirect()->route('agents.index')->with('success', 'Задача удалена');
    }

    /**
     * @return \Illuminate\Support\Collection<int, LlmProvider>
     */
    private function providers()
    {
        return LlmProvider::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }
}