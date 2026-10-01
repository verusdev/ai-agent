@foreach ($plan as $step)
    <div class="plan-step {{ $step['status'] ?? 'todo' }}">
        <span class="mark">{{ ($step['status'] ?? 'todo') === 'done' ? '✓' : '' }}</span>
        <span>{{ $step['title'] }}</span>
    </div>
@endforeach