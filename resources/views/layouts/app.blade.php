<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ИИ-агент') — {{ config('app.name') }}</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🤖</text></svg>">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/agent.css') }}">
</head>
<body>
<div class="agent-layout">
    <aside class="agent-side">
        <a class="flex" href="{{ route('agents.index') }}" style="gap:10px;margin-bottom:16px">
            <div class="logo" style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#2563eb,#7c3aed);display:grid;place-items:center;font-size:19px;flex:0 0 36px">🤖</div>
            <div>
                <div style="font-weight:700;color:#fff;font-size:14px">{{ config('app.name') }}</div>
                <div style="font-size:11px;color:#64748b">на бесплатных моделях</div>
            </div>
        </a>

        <a class="btn btn-primary btn-block mb-16" href="{{ route('agents.index') }}">+ Новая задача</a>

        @yield('sidebar')

        <div style="margin-top:18px;padding-top:12px;border-top:1px solid rgba(148,163,184,.15)">
            <a class="btn btn-sm btn-block" style="background:#1e293b;border-color:#334155;color:#cbd5e1" href="{{ route('providers.index') }}">⚙ Модели и ключи</a>
        </div>
    </aside>

    <div class="agent-main">
        @yield('main')
    </div>
</div>
@stack('scripts')
</body>
</html>