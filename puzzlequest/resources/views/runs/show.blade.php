@extends('layouts.app')

@section('title', $run->run_title ?? 'Run')

@section('content')
    <h2 class="text-xl font-semibold">{{ $run->run_title ?? '(untitled)' }}</h2>
    <p class="text-sm text-gray-600">Created: {{ $run->run_added }} | By: {{ $run->user->user_name ?? $run->user->name ?? 'Unknown' }}</p>

    <div style="margin-top:1rem">
        <p>{{ $run->run_description }}</p>
    </div>

    <div style="margin-top:1rem">
        @if(auth()->check() && auth()->user()->user_id === $run->user_id)
            <a href="{{ route('runs.edit', $run->run_id) }}">Edit this run</a>
            <a href="{{ route('stats.run', $run->run_id) }}" style="margin-left:1rem">View run stats</a>
        @endif
        <a href="{{ route('runs.index') }}" style="margin-left:1rem">Back to runs</a>
    </div>

    {{-- show flags and questions counts --}}
    <div style="margin-top:1rem">
        <strong>Flags:</strong> {{ $run->flags ? $run->flags->count() : 0 }}
        <br>
        <strong>Questions:</strong> {{ $run->questions ? $run->questions->count() : 0 }}
    </div>

    {{-- Small list of recent user histories for this run --}}
    <div style="margin-top:1rem">
        <h3 class="text-lg font-medium">Recent players</h3>
        @if(!empty($histories) && $histories->count())
            <ul>
                @foreach($histories as $h)
                    <li>
                        {{ $h->user->user_name ?? $h->user->name ?? 'Guest' }} — started {{ $h->history_start }} @if($h->history_end) (ended {{ $h->history_end }})@endif
                    </li>
                @endforeach
            </ul>
        @else
            <div class="text-sm text-gray-600">No recent players recorded for this run.</div>
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
