@php
    $role = $message->role;
    $who = match ($role) {
        'user' => 'вы',
        'assistant' => 'агент',
        'tool' => 'инструмент · '.($message->tool_name ?: '?'),
        default => 'система',
    };
@endphp
<div class="msg {{ $role }}" data-id="{{ $message->id }}">
    <div class="who">{{ $who }}</div>
    <div class="body">{{ $message->content }}</div>
</div>