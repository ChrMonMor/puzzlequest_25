@php
    // Ensure $runs is a collection for consistent API
    $runs = isset($runs) ? collect($runs) : collect();
    use Illuminate\Support\Str;
@endphp

@if($runs->isEmpty())
    <p>No runs found.</p>
@else
    <div class="runs-list">
        @foreach($runs as $run)
            {{-- Skip private runs unless the current user is the owner --}}
            @if(optional($run->runType)->run_type_name === 'Private' && !(auth()->check() && auth()->user()->user_id === $run->user_id))
                @continue
            @endif

            <div class="card" style="margin-bottom:.75rem">
                <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start">
                    <div style="flex:1">
                        <h3 style="margin:0; font-size:1.05rem">
                            <a href="{{ route('runs.show', $run->run_id) }}">{{ $run->run_title ?? '(untitled)' }}</a>
                        </h3>
                        <div class="muted" style="font-size:.9rem; margin-top:.25rem">
                            by {{ optional($run->user)->user_name ?? optional($run->user)->name ?? 'Unknown' }}
                            @if(!empty($run->run_description))
                                <p class="muted" style="margin-top:.65rem">{{ Str::limit($run->run_description, 180) }}</p>
                            @endif
                        </div>
                    </div>

                    <div style="text-align:right">
                        @auth
                            @if(auth()->user()->user_id === $run->user_id)
                                <a href="{{ route('runs.edit', $run->run_id) }}" class="btn btn-primary">Edit</a>
                            @endif
                        @endauth
                        <div style="margin-top:.5rem">
                            <a href="{{ route('runs.show', $run->run_id) }}" class="btn btn-secondary">View run</a>
                        </div>
                    </div>
                </div>


            </div>
        @endforeach
    </div>
@endif
