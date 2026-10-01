@extends('layouts.app')

@section('title', 'Новая задача')

@section('sidebar')
    @foreach ($agents as $item)
        <a class="task-item {{ request()->routeIs('agents.show') && request()->route('agent')?->id === $item->id ? 'on' : '' }}"
           href="{{ route('agents.show', $item) }}">
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
        <div>
            <h1>Новая задача для агента</h1>
            <div class="small muted">Опишите результат — агент сам разобьёт задачу на шаги и выполнит их инструментами</div>
        </div>
        @if ($providers->isEmpty())
            <a class="btn btn-primary" href="{{ route('providers.index') }}">Сначала настройте модель →</a>
        @endif
    </div>

    <div class="agent-body">
        <div class="card">
            <div class="card-body task-form">
                <form method="POST" action="{{ route('agents.store') }}">
                    @csrf

                    <div class="field mb-16">
                        <label for="goal">Что нужно сделать</label>
                        <textarea id="goal" name="goal" required minlength="5" placeholder="Например: изучи цены на б/у запчасти для Toyota Camry 2019 в объявлениях, составь таблицу со ссылками и сделай CSV-файл с результатами"></textarea>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="provider_id">Модель</label>
                            <select class="select" id="provider_id" name="provider_id">
                                @foreach ($providers as $provider)
                                    <option value="{{ $provider->id }}" @selected(! $provider->isReady())>
                                        {{ $provider->label }}{{ $provider->model ? ' · '.$provider->model : '' }}{{ $provider->isReady() ? '' : ' (нужен ключ)' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="field">
                            <label for="model">Модель (необязательно)</label>
                            <input class="input" id="model" name="model" placeholder="оставьте пустым — возьмём модель провайдера по умолчанию">
                        </div>
                    </div>

                    <div class="mt-16">
                        <button class="btn btn-primary" type="submit" @disabled($providers->isEmpty())>Запустить агента</button>
                    </div>
                </form>
            </div>

            <div class="card-head"><h3>Примеры задач</h3></div>
            <div class="card-body">
                <div class="examples">
                    <button type="button" data-fill="Собери HTML-страницу-лендинг для сервиса по подписке на автомобили: 5 экранов, адаптив, тёмная тема. Сохрани как index.html.">🌐 Сверстать лендинг</button>
                    <button type="button" data-fill="Найди в интернете 5 статей про самостоятельную замену масла в двигателе, выпиши ключевые шаги и собери из них памятку в markdown.">🔎 Поиск и памятка</button>
                    <button type="button" data-fill="Посчитай бюджет запуска кофейни на 50 посетителей в день и построй график разбивки затрат по статьям в SVG.">📊 Расчёт и график</button>
                    <button type="button" data-fill="Напиши Python-скрипт, который читает CSV с продажами и считает выручку по месяцам, сохрани как report.py.">🐍 Скрипт</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-head"><h3>Что умеет агент</h3></div>
            <div class="card-body">
                <ul class="small muted" style="padding-left:18px;line-height:2;margin:0">
                    <li><b>update_plan</b> — ведёт план задачи</li>
                    <li><b>web_search</b> / <b>web_fetch</b> — поиск без ключа и разбор страниц</li>
                    <li><b>read_file</b> / <b>write_file</b> / <b>list_files</b> — файлы в папке задачи</li>
                    <li><b>create_document</b> — документы md / html / csv / json</li>
                    <li><b>make_chart</b> — графики в SVG</li>
                    <li><b>calculate</b> — точные расчёты</li>
                    <li><b>run_code</b> — запуск кода (выключен по умолчанию)</li>
                </ul>
                <div class="hint-box mt-16">
                    Всё, что агент создаёт, попадает в папку задачи и в галерею артефактов.
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.querySelectorAll('[data-fill]').forEach(function (button) {
            button.addEventListener('click', function () {
                var field = document.getElementById('goal');
                field.value = button.getAttribute('data-fill');
                field.focus();
            });
        });
    </script>
@endpush