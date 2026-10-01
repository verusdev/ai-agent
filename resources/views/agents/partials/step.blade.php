<div class="feed-item {{ $step->status }}" data-id="{{ $step->id }}">
    <div class="h">
        <span>{{ $step->title ?: $step->tool }}</span>
        <span class="small muted mono">{{ $step->duration_ms ? $step->duration_ms.' мс' : '' }}</span>
    </div>
    <div class="o">{{ \Illuminate\Support\Str::limit($step->output, 240) }}</div>
</div>