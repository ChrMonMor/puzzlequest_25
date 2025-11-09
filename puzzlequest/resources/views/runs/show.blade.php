@extends('layouts.app')

@section('title', $run->run_title ?? 'Run')

@section('content')
    <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem">
            <div style="flex:1">
                <h2 class="text-xl font-semibold" style="margin:0">{{ $run->run_title ?? '(untitled)' }}</h2>
                <div class="muted" style="margin-top:.25rem; font-size:.95rem">Created: {{ $run->run_added }} · By: {{ $run->user->user_name ?? $run->user->name ?? 'Unknown' }}</div>
            </div>

            <div style="text-align:right">
                @if(auth()->check() && auth()->user()->user_id === $run->user_id)
                    {{-- Show pin to owner only --}}
                    @if(!empty($run->run_pin))
                        <div class="muted" style="margin-bottom:.5rem">Pin: <strong>{{ $run->run_pin }}</strong></div>
                    @endif
                    <div>
                        <a href="{{ route('runs.edit', $run->run_id) }}" class="btn btn-primary">Edit</a>
                        <a href="{{ route('stats.run', $run->run_id) }}" class="btn btn-secondary" style="margin-left:.5rem">View stats</a>
                    </div>
                @else
                    <div>
                        <a href="{{ route('runs.index') }}" class="btn btn-secondary">Back to runs</a>
                    </div>
                @endif
            </div>
        </div>

        @if(!empty($run->run_description))
            <div style="margin-top:.75rem">{{ $run->run_description }}</div>
        @endif

        <div style="margin-top:.75rem; display:flex; gap:1.5rem; align-items:center;">
            <div><strong>Flags:</strong> {{ $run->flags ? $run->flags->count() : 0 }}</div>
            <div><strong>Questions:</strong> {{ $run->questions ? $run->questions->count() : 0 }}</div>
            @if(optional($run->runType)->run_type_name)
                <div class="muted">Type: {{ optional($run->runType)->run_type_name }}</div>
            @endif
        </div>

    {{-- Map of flags for this run --}}
    @section('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
        <style>
            #run-map { height: 60vh; border-radius: .5rem; margin-top: 1rem; }
        </style>
    @endsection

    <div id="run-map"></div>

        {{-- Small list of recent user histories for this run --}}
        <div style="margin-top:1rem">
            <h3 class="text-lg font-medium">Recent players</h3>
            @if(!empty($histories) && $histories->count())
                <ul>
                    @foreach($histories as $h)
                        <li class="muted" style="margin-bottom:.25rem">
                            {{ $h->user->user_name ?? $h->user->name ?? 'Guest' }} — started {{ $h->history_start }} @if($h->history_end) (ended {{ $h->history_end }})@endif
                        </li>
                    @endforeach
                </ul>
            @else
                <div class="muted">No recent players recorded for this run.</div>
            @endif
        </div>
    </div>

    @section('scripts')
        {{-- Leaflet JS (CDN) -- ensure L is available before our inline script --}}
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (function(){
                function getAuthHeaders(){
                    const headers = { 'Content-Type': 'application/json' };
                    const token = sessionStorage.getItem('jwt') || localStorage.getItem('jwt') || (window.__SERVER_JWT || null);
                    if (token) headers['Authorization'] = `Bearer ${token}`;
                    return headers;
                }

                // escape helper
                function escapeText(s){ if (s === null || s === undefined) return ''; return String(s); }

                const mapEl = document.getElementById('run-map');
                if (!mapEl) return;
                const map = L.map(mapEl).setView([51.505, -0.09], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                // Fetch flags for this run and plot
                async function loadRunFlags(){
                    try{
                        const res = await fetch('/api/flags?run_id=' + encodeURIComponent('{{ $run->run_id }}'), { headers: getAuthHeaders() });
                        if (!res.ok) {
                            console.error('Failed to load flags', res.status);
                            return;
                        }
                        const flags = await res.json();
                        if (!Array.isArray(flags)) return;

                        const bounds = [];
                        flags.forEach(f => {
                            if (!f.flag_lat || !f.flag_long) return;
                            const lat = parseFloat(f.flag_lat);
                            const lng = parseFloat(f.flag_long);
                            bounds.push([lat, lng]);
                            const marker = L.marker([lat, lng]).addTo(map);
                            const question = (f.questions && f.questions.length) ? f.questions[0] : null;
                            const title = question ? escapeText(question.question_text) : ('Flag #' + (f.flag_number || f.flag_id));
                            marker.bindPopup('<strong>' + title + '</strong>');
                        });
                        if (bounds.length) {
                            try {
                                const latLngBounds = L.latLngBounds(bounds);
                                map.fitBounds(latLngBounds, { padding: [50, 50] });
                            } catch (e) {
                                // fallback to default view
                                map.setView([51.505, -0.09], 13);
                            }
                        }
                    } catch(e){ console.error('Error loading flags', e); }
                }

                loadRunFlags();
            })();
        </script>
    @endsection

@endsection
