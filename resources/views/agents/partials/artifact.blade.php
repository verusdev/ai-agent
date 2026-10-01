@php
    $icons = ['document' => '📄', 'code' => '💻', 'chart' => '📈', 'data' => '🗂️', 'html' => '🌐'];
@endphp
<div class="art" data-id="{{ $artifact->id }}">
    <span class="ic">{{ $icons[$artifact->kind] ?? '📎' }}</span>
    <div style="min-width:0;flex:1">
        <div class="nm">{{ $artifact->name }}</div>
        <div class="mt">{{ \App\Models\AgentArtifact::kinds()[$artifact->kind] ?? $artifact->kind }} · {{ $artifact->humanSize() }}</div>
    </div>
    <div class="row-actions">
        <a class="btn btn-sm" href="{{ route('artifacts.show', $artifact) }}" target="_blank">Открыть</a>
        <a class="btn btn-sm" href="{{ route('artifacts.download', $artifact) }}">⬇</a>
    </div>
</div>