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

    <script>
        // expose current authenticated user's id for client-side ownership checks
        window.__auth_user_id = @json(auth()->check() ? auth()->user()->user_id : null);
    </script>

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
                    const res = await fetch('/api/runs/pin/' + encodeURIComponent(pin));
                    if (!res.ok) return null;
                    const run = await res.json();
                    return run;
                }catch(e){ console.error('pin lookup failed', e); }
                return null;
            }

            // Render helper: create card HTML for a run (similar to server)
            function renderRunCard(run){
                const ownerName = (run.user && (run.user.user_name || run.user.name)) || 'Unknown';
                const title = run.run_title || '(untitled)';
                const desc = run.run_description ? ('<p class="muted" style="margin-top:.65rem">' + (run.run_description.length>180 ? run.run_description.substring(0,177)+'...' : run.run_description) + '</p>') : '';
                const editBtn = (window.__auth_user_id && window.__auth_user_id === run.user_id) ? '<a href="/runs/' + run.run_id + '/edit" class="btn btn-primary">Edit</a>' : '';
                const viewBtn = '<a href="/runs/' + run.run_id + '" class="btn btn-secondary">View run</a>';
                return '\n<div class="card" style="margin-bottom:.75rem" data-title="' + (title||'').toLowerCase() + '" data-owner="' + (ownerName||'').toLowerCase() + '" data-pin="' + (run.run_pin||'') + '">\n  <div style="display:flex; justify-content:space-between; gap:1rem; align-items:flex-start">\n    <div style="flex:1">\n      <h3 style="margin:0; font-size:1.05rem"><a href="/runs/' + run.run_id + '">' + escapeHtml(title) + '</a></h3>\n      <div class="muted" style="font-size:.9rem; margin-top:.25rem">by ' + escapeHtml(ownerName) + desc + '</div>\n    </div>\n    <div style="text-align:right">' + editBtn + '<div style="margin-top:.5rem">' + viewBtn + '</div>\n    </div>\n  </div>\n</div>\n';
            }

            function escapeHtml(s){ return String(s).replace(/[&<>\"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"}[c]; }); }

            // Fetch paginated runs and optionally replace/append to the list
            let currentPage = 1;
            let lastPage = 1;
            const perPage = 12;

            async function fetchRunsPage(page = 1, q = '', replace = true){
                try{
                    const params = new URLSearchParams();
                    params.set('page', page);
                    params.set('per_page', perPage);
                    if (q) params.set('q', q);
                    const res = await fetch('/api/runs?' + params.toString());
                    if (!res.ok) return null;
                    const payload = await res.json();

                    // Laravel paginator returns { data: [...], current_page, last_page }
                    const runs = payload.data || payload;
                    currentPage = payload.current_page || page;
                    lastPage = payload.last_page || 1;

                    if (replace) list.innerHTML = '';
                    for (const r of runs){
                        // Skip private runs unless owner (server also filters, but double-check)
                        const runTypeName = (r.runType && r.runType.run_type_name) || '';
                        const isPrivate = runTypeName === 'Private';
                        if (isPrivate && !(window.__auth_user_id && window.__auth_user_id === r.user_id)) continue;
                        list.insertAdjacentHTML('beforeend', renderRunCard(r));
                    }

                    renderLoadMore();
                    return payload;
                }catch(e){ console.error('failed to fetch runs page', e); return null; }
            }

            function renderLoadMore(){
                // remove existing loader
                const existing = document.getElementById('runs-load-more');
                if (existing) existing.remove();
                if (currentPage < lastPage){
                    const btn = document.createElement('div');
                    btn.id = 'runs-load-more';
                    btn.style.marginTop = '.6rem';
                    btn.innerHTML = '<button class="btn btn-primary">Load more</button>';
                    btn.querySelector('button').addEventListener('click', function(){ fetchRunsPage(currentPage+1, input.value.trim(), false); });
                    list.parentNode.appendChild(btn);
                }
            }

            // wire Enter key for pin lookup
            input.addEventListener('keydown', async function(ev){
                if (ev.key === 'Enter'){
                    const val = input.value.trim();
                    if (!val) return;
                    const pinTest = /^[A-Za-z0-9]{4,8}$/; // allow 4-8 char pins
                    if (pinTest.test(val)){
                        // try pin endpoint
                        const run = await fetchRunByPin(val);
                        if (run && run.run_id){ goToRunById(run.run_id); return; }
                        // not found: fall back to search
                    }
                    // If not pin or not found, perform search
                    await fetchRunsPage(1, input.value.trim(), true);
                }
            });

            // live search: debounce user input
            let debounceTimer = null;
            input.addEventListener('input', function(){
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function(){
                    const q = input.value.trim();
                    fetchRunsPage(1, q, true);
                }, 300);
            });

            // initial load: replace server-rendered list with first page from API
            // expose a small global for ownership checks if server provided one
            try{
                window.__auth_user_id = (window.__auth_user_id !== undefined) ? window.__auth_user_id : null;
            }catch(e){ window.__auth_user_id = null; }
            fetchRunsPage(1, '', true);
        })();
    </script>
@endif
