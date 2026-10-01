@extends('layouts.app')

@section('title', 'Модели и ключи')

@section('sidebar')
    <a class="btn btn-sm btn-block mb-16" style="background:#1e293b;border-color:#334155;color:#cbd5e1" href="{{ route('agents.index') }}">← К задачам</a>
    <div class="small" style="color:#64748b;line-height:1.7">
        Локальные модели работают без ключей и без интернета.
        Облачные — по бесплатному ключу.
    </div>
@endsection

@section('main')
    <div class="agent-top">
        <div>
            <h1>Бесплатные модели</h1>
            <div class="small muted">Один и тот же агент работает и на локальной Ollama, и на облачных API с бесплатным лимитом</div>
        </div>
    </div>

    <div class="agent-body">
        <div class="stack">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                </div>
            @endif

            @foreach ($providers as $provider)
                <div class="card">
                    <div class="card-head">
                        <h3>
                            {{ $provider->label }}
                            @if ($provider->is_default)<span class="badge b-green">по умолчанию</span>@endif
                            <span class="badge {{ $provider->isReady() ? 'b-gray' : 'b-red' }}" id="status-{{ $provider->id }}">
                                {{ $provider->isReady() ? 'готова' : 'нужен ключ' }}
                            </span>
                        </h3>
                        <div class="flex">
                            <button class="btn btn-sm" data-probe="{{ $provider->id }}">Проверить связь</button>
                            <form method="POST" action="{{ route('providers.destroy', $provider) }}"
                                  data-confirm="Удалить провайдера?">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-ghost-danger" type="submit">Удалить</button>
                            </form>
                        </div>
                    </div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('providers.update', $provider) }}" id="form-{{ $provider->id }}">
                            @csrf @method('PUT')

                            <div class="form-grid">
                                <div class="field">
                                    <label>Название</label>
                                    <input class="input" name="label" value="{{ old('label', $provider->label) }}">
                                </div>
                                <div class="field">
                                    <label>Адрес API</label>
                                    <input class="input mono" name="base_url" value="{{ old('base_url', $provider->base_url) }}">
                                </div>
                                <div class="field">
                                    <label>API-ключ {{ $provider->requiresKey() ? '' : '(не нужен)' }}</label>
                                    <input class="input" type="password" name="api_key" placeholder="{{ $provider->requiresKey() ? $provider->maskedKey() : 'оставьте пустым' }}" autocomplete="off">
                                    <div class="help">Ключ хранится в базе в зашифрованном виде</div>
                                </div>
                                <div class="field">
                                    <label>Модель</label>
                                    <select class="select" name="model" id="model-{{ $provider->id }}">
                                        @foreach (($provider->models ?? []) as $id => $name)
                                            <option value="{{ $id }}" @selected(old('model', $provider->model) === $id)>{{ is_string($name) ? $name : $id }}</option>
                                        @endforeach
                                        @if (empty($provider->models))
                                            <option value="{{ old('model', $provider->model) }}">{{ old('model', $provider->model) }}</option>
                                        @endif
                                    </select>
                                </div>
                            </div>

                            <div class="pill-list mt-16">
                                <label class="check">
                                    <input type="checkbox" name="supports_tools" value="1" @checked(old('supports_tools', $provider->supports_tools))>
                                    Модель умеет вызывать инструменты
                                </label>
                                <label class="check">
                                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $provider->is_active))>
                                    Активна
                                </label>
                                <label class="check">
                                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $provider->is_default))>
                                    Использовать по умолчанию
                                </label>
                            </div>

                            @if ($provider->note)
                                <div class="hint-box mt-16">{{ $provider->note }}</div>
                            @endif

                            <div class="mt-16">
                                <button class="btn btn-primary" type="submit">Сохранить</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach

            <div class="card">
                <div class="card-head"><h3>Добавить провайдера</h3></div>
                <div class="card-body">
                    <p class="small muted">Любой OpenAI-совместимый endpoint: Together, Fireworks, Nebius, llama.cpp, vLLM.</p>
                    <form method="POST" action="{{ route('providers.store') }}">
                        @csrf
                        <div class="form-grid">
                            <div class="field">
                                <label>Название</label>
                                <input class="input" name="label" required placeholder="Мой провайдер">
                            </div>
                            <div class="field">
                                <label>Адрес API</label>
                                <input class="input mono" name="base_url" required placeholder="https://api.example.com/v1">
                            </div>
                            <div class="field">
                                <label>Ключ</label>
                                <input class="input" type="password" name="api_key" autocomplete="off">
                            </div>
                            <div class="field">
                                <label>Модель</label>
                                <input class="input mono" name="model" placeholder="например qwen2.5:7b-instruct">
                            </div>
                        </div>
                        <label class="check mt-16">
                            <input type="checkbox" name="supports_tools" value="1" checked> Умеет вызывать инструменты
                        </label>
                        <div class="mt-16"><button class="btn btn-primary" type="submit">Добавить</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Готовые пресеты</h3></div>
            <div class="card-body">
                <div class="stack" style="gap:10px">
                    @foreach (config('agents.providers') as $slug => $preset)
                        @php $added = $providers->contains('slug', $slug); @endphp
                        <div style="border:1px solid var(--border);border-radius:10px;padding:11px">
                            <div class="flex-between">
                                <b style="font-size:13px">{{ $preset['label'] }}</b>
                                <span class="badge b-gray">{{ $categories[$slug] ?? '' }}</span>
                            </div>
                            <div class="small muted mono" style="margin-top:4px">{{ $preset['base_url'] }}</div>
                            <div class="small muted" style="margin-top:6px">
                                {{ collect(array_keys($preset['models']))->take(3)->join(', ') }}
                            </div>
                            <form method="POST" action="{{ route('providers.preset') }}" class="mt-16">
                                @csrf
                                <input type="hidden" name="slug" value="{{ $slug }}">
                                <button class="btn btn-sm {{ $added ? '' : 'btn-primary' }}" @disabled($added) type="submit">
                                    {{ $added ? '✓ Уже добавлен' : 'Добавить' }}
                                </button>
                            </form>
                        </div>
                    @endforeach
                </div>

                <div class="hint-box mt-16">
                    <b>Локально и бесплатно:</b> поставьте <code>ollama serve</code> и выполните
                    <code>ollama pull llama3.1:8b</code> — ключ не нужен, интернет не нужен.
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-probe]').forEach(function (button) {
            button.addEventListener('click', function () {
                var badge = document.getElementById('status-' + button.dataset.probe);
                badge.textContent = 'проверяю…';

                fetch('/providers/' + button.dataset.probe + '/probe', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json'
                    }
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        badge.textContent = data.ok ? 'связь есть' : 'нет связи';
                        badge.className = 'badge ' + (data.ok ? 'b-green' : 'b-red');
                        if (data.models && data.models.length) {
                            var select = document.getElementById('model-' + button.dataset.probe);
                            data.models.forEach(function (id) {
                                if (select.querySelector('option[value="' + id + '"]')) return;
                                var option = new Option(id, id);
                                select.appendChild(option);
                            });
                        }
                        if (!data.ok) window.alert('Не удалось связаться: ' + data.message);
                    })
                    .catch(function (e) {
                        badge.textContent = 'нет связи';
                        badge.className = 'badge b-red';
                    });
            });
        });

        document.querySelectorAll('[data-confirm]').forEach(function (form) {
            form.addEventListener('submit', function (e) {
                if (!window.confirm(form.getAttribute('data-confirm'))) e.preventDefault();
            });
        });
    </script>
@endpush