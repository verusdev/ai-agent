@extends('layouts.app')

@section('title', $agent->title)

@section('sidebar')
    @php
        $tasks = \App\Models\Agent::with('provider')->latest('updated_at')->limit(20)->get();
    @endphp
    <a class="task-item {{ $agent->id === request()->route('agent')?->id ? 'on' : '' }}" href="{{ route('agents.index') }}">← Все задачи</a>
    @foreach ($tasks as $item)
        <a class="task-item {{ $item->id === $agent->id ? 'on' : '' }}" href="{{ route('agents.show', $item) }}">
            <div class="t">{{ $item->title }}</div>
            <div class="m">
                <span class="badge b-{{ \App\Models\Agent::statuses()[$item->status]['color'] }}" style="padding:1px 6px;font-size:10px">
                    {{ \App\Models\Agent::statuses()[$item->status]['label'] }}
                </span>
                <span>{{ $item->updated_at->diffForHumans(short: true) }}</span>
            </div>
        </a>
    @endforeach
@endsection

@section('main')
    <div class="agent-top">
        <div style="min-width:0">
            <h1 class="truncate" title="{{ $agent->goal }}">{{ $agent->title }}</h1>
            <div class="small muted">
                <span id="status-badge" class="badge b-{{ \App\Models\Agent::statuses()[$agent->status]['color'] }}">
                    {{ \App\Models\Agent::statuses()[$agent->status]['label'] }}
                </span>
                · {{ $agent->provider?->label ?? 'модель не выбрана' }}
                @if ($agent->model) <span class="mono">({{ $agent->model }})</span> @endif
                · итераций <b id="iteration">{{ $agent->iteration }}</b>
                · токенов <b id="tokens">{{ $agent->tokens }}</b>
            </div>
        </div>
        <div class="flex">
            <button class="btn" id="run-btn" @disabled($agent->isFinished() && $agent->status !== 'failed')>
                ▶ Запустить
            </button>
            <form method="POST" action="{{ route('agents.destroy', $agent) }}" data-confirm="Удалить задачу и её файлы?">
                @csrf @method('DELETE')
                <button class="btn btn-ghost-danger" type="submit">Удалить</button>
            </form>
        </div>
    </div>

    <div class="agent-body">
        <div>
            @if ($agent->plan)
                <div class="card mb-16">
                    <div class="card-head"><h3>План работы</h3></div>
                    <div class="card-body">
                        <div class="plan" id="plan">@include('agents.partials.plan', ['plan' => $agent->plan])</div>
                    </div>
                </div>
            @else
                <div class="card mb-16" id="plan-card" style="display:none">
                    <div class="card-head"><h3>План работы</h3></div>
                    <div class="card-body"><div class="plan" id="plan"></div></div>
                </div>
            @endif

            <div class="card">
                <div class="card-head">
                    <h3>Диалог</h3>
                    <span class="small muted" id="hint"></span>
                </div>
                <div class="card-body">
                    <div class="chat" id="chat">
                        @foreach ($messages as $message)
                            @include('agents.partials.message', ['message' => $message])
                        @endforeach
                        <div class="msg assistant" id="thinking" style="display:none">
                            <div class="who">агент</div>
                            <span class="thinking"><i></i><i></i><i></i></span>
                            <span id="thinking-text" class="small muted">работаю…</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="stack">
            <div class="card">
                <div class="card-head"><h3>Действия агента</h3><span class="small muted" id="steps-count">{{ $agent->steps->count() }}</span></div>
                <div class="card-body">
                    <div class="feed" id="feed">
                        @foreach ($agent->steps as $step)
                            @include('agents.partials.step', ['step' => $step])
                        @endforeach
                        @if ($agent->steps->isEmpty())
                            <div class="muted small">Агент ещё не выполнял действия.</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <h3>Артефакты</h3>
                    <div class="flex">
                        <span class="small muted" id="arts-count">{{ $agent->artifacts->count() }}</span>
                        <button class="btn btn-sm" id="open-workspace" style="display:none" title="Открыть папку с файлами задач">📂 Папка</button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="arts" id="arts">
                        @foreach ($agent->artifacts as $artifact)
                            @include('agents.partials.artifact', ['artifact' => $artifact])
                        @endforeach
                        @if ($agent->artifacts->isEmpty())
                            <div class="muted small">Пока пусто — агент создаст здесь документы и графики.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="composer">
        <form method="POST" action="{{ route('agents.messages.store', $agent) }}" id="composer">
            @csrf
            <textarea name="content" placeholder="Уточнить задачу или попросить продолжить…" rows="1"></textarea>
            <div class="flex-between mt-16">
                <span class="small muted">Ctrl+Enter — отправить. Ответ появится после нажатия «Запустить».</span>
                <button class="btn btn-primary" type="submit">Отправить</button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        window.AGENT = {
            id: {{ $agent->id }},
            tickUrl: @json(route('agents.tick', $agent)),
            eventsUrl: @json(route('agents.events', $agent)),
            finished: @json($agent->isFinished() && $agent->status !== 'failed'),
            i18n: {
                thinking: 'думаю…',
                calling: 'вызываю модель…',
                done: 'готово',
                failed: 'ошибка'
            }
        };
    </script>
    <script src="{{ asset('js/agent.js') }}"></script>
@endpush