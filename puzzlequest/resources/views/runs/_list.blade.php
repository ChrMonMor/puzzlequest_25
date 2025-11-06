@php
    // Ensure $runs is a collection for consistent API
    $runs = isset($runs) ? collect($runs) : collect();
@endphp

@if($runs->isEmpty())
    <p>No runs found.</p>
@else
    <ul>
        @foreach($runs as $run)
            <li style="margin-bottom:.6rem">
                <a href="{{ route('runs.show', $run->run_id) }}">{{ $run->run_title ?? '(untitled)' }}</a>
                <small class="text-muted">by {{ optional($run->user)->user_name ?? optional($run->user)->name ?? 'Unknown' }}</small>
                @auth
                    @if(auth()->user()->user_id === $run->user_id)
                        - <a href="{{ route('runs.edit', $run->run_id) }}">Edit</a>
                    @endif
                @endauth
            </li>
        @endforeach
    </ul>
@endif
