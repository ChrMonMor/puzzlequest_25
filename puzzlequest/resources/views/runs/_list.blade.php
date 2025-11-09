@php
    // Ensure $runs is a collection for consistent API
    $runs = isset($runs) ? collect($runs) : collect();
    use Illuminate\Support\Str;
@endphp

@if($runs->isEmpty())
    <p>No runs found.</p>
@else
    <div style="margin-bottom:.6rem">
        <input id="run-search" type="search" placeholder="Search runs by title, owner or enter pin..." class="" style="width:100%; padding:.5rem .75rem; border-radius:6px; border:1px solid rgba(0,0,0,0.06); background:var(--surface); color:var(--text);" />
    </div>

    <div class="runs-list" id="runs-list">
        @foreach($runs as $run)
            {{-- Skip private runs unless the current user is the owner --}}
            @if(optional($run->runType)->run_type_name === 'Private' && !(auth()->check() && auth()->user()->user_id === $run->user_id))
                @continue
            @endif

            <div class="card" style="margin-bottom:.75rem" data-title="{{ strtolower($run->run_title ?? '') }}" data-owner="{{ strtolower(optional($run->user)->user_name ?? optional($run->user)->name ?? '') }}" data-pin="{{ $run->run_pin ?? '' }}">
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
    <script>
        (function(){
            const input = document.getElementById('run-search');
            const list = document.getElementById('runs-list');
            if (!input || !list) return;

            function goToRunById(id){
                if (!id) return;
                window.location.href = '/runs/' + encodeURIComponent(id);
            }

            async function fetchRunByPin(pin){
                if (!pin) return null;
                try{
                    // fetch all runs and find the pin (index may be heavy on very large datasets)
                    const res = await fetch('/api/runs');
                    if (!res.ok) return null;
                    const runs = await res.json();
                    for (const r of runs){
                        if (!r.run_pin) continue;
                        if (String(r.run_pin).toLowerCase() === pin.toLowerCase()) return r;
                    }
                }catch(e){ console.error('pin lookup failed', e); }
                return null;
            }

            input.addEventListener('keydown', async function(ev){
                if (ev.key === 'Enter'){
                    const val = input.value.trim();
                    if (!val) return;
                    // If value looks like a pin (6 alnum) try quick lookup
                    const pinTest = /^[A-Za-z0-9]{4,8}$/; // allow 4-8 char pins
                    if (pinTest.test(val)){
                        // first try DOM lookup
                        const cards = Array.from(list.querySelectorAll('[data-pin]'));
                        const found = cards.find(c => (c.getAttribute('data-pin') || '').toLowerCase() === val.toLowerCase());
                        if (found){
                            const link = found.querySelector('a[href*="/runs/"]');
                            if (link) { link.click(); return; }
                        }

                        // fallback to API lookup
                        const run = await fetchRunByPin(val);
                        if (run) { goToRunById(run.run_id); return; }
                        // not found: fall through to client filter
                    }
                }
            });

            input.addEventListener('input', function(){
                const q = input.value.trim().toLowerCase();
                const cards = Array.from(list.querySelectorAll('.card'));
                if (!q){
                    cards.forEach(c => c.style.display = 'block');
                    return;
                }

                cards.forEach(c => {
                    const title = (c.getAttribute('data-title') || '').toLowerCase();
                    const owner = (c.getAttribute('data-owner') || '').toLowerCase();
                    const pin = (c.getAttribute('data-pin') || '').toLowerCase();
                    if (title.includes(q) || owner.includes(q) || pin.includes(q)) c.style.display = 'block'; else c.style.display = 'none';
                });
            });
        })();
    </script>
@endif
