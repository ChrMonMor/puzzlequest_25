@extends('layouts.app')

@section('title', 'Run statistics')

@section('content')
    <h2 class="text-xl font-semibold">Statistics for {{ $run->run_title ?? '(untitled)' }}</h2>

    <div class="card" style="margin-top:1rem; display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-start">
        <div style="flex:1; min-width:260px">
            <h3 style="margin:0 0 .5rem 0">Overview</h3>
            <div style="line-height:1.6">
                <div><strong>Total plays:</strong> {{ $totalHistories }}</div>
                <div><strong>Completed plays:</strong> {{ $completedHistories }}</div>
                <div><strong>Unique players:</strong> {{ $uniquePlayers }}</div>
                <div><strong>Total flags reached:</strong> {{ $totalReached }}</div>
                <div><strong>Total points across plays:</strong> {{ $totalPoints }}</div>
                <div><strong>Completion (overall):</strong> {{ $completionPercent }}%</div>
                <div><strong>Average points per play:</strong> {{ $averagePoints }}</div>
                <div><strong>Average duration:</strong>
                    @if($avgDurationSeconds)
                        {{ gmdate('H:i:s', $avgDurationSeconds) }} (hh:mm:ss)
                    @else
                        -
                    @endif
                </div>
            </div>
        </div>

        <div style="flex:2; min-width:320px">
            <h3 style="margin:0 0 .5rem 0">Visuals</h3>
            <div style="display:flex; gap:1rem; flex-wrap:wrap">
                <div style="flex:1 1 32%; min-width:180px; height:200px;">
                    <div class="card" style="height:100%; padding:.5rem">
                        <canvas id="pointsChart" style="height:100%"></canvas>
                    </div>
                </div>
                <div style="flex:1 1 32%; min-width:180px; height:200px;">
                    <div class="card" style="height:100%; padding:.5rem">
                        <canvas id="reachedChart" style="height:100%"></canvas>
                    </div>
                </div>
                <div style="flex:1 1 32%; min-width:180px; height:200px;">
                    <div class="card" style="height:100%; padding:.5rem">
                        <canvas id="completionChart" style="height:100%"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Map: show canonical flag positions and where players reached them for this run --}}
    @section('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
        <style>
            #run-stats-map { height: 50vh; border-radius: .5rem; margin-top: 1rem; }
        </style>
    @endsection

    <div id="run-stats-map" class="card" style="margin-top:1rem; padding:0; overflow:hidden;"></div>

    @php
        $canonicalPoints = [];
        if ($run->flags && $run->flags->count()) {
            foreach ($run->flags as $f) {
                if (!empty($f->flag_lat) && !empty($f->flag_long)) {
                    $canonicalPoints[] = [
                        'lat' => (float) $f->flag_lat,
                        'lng' => (float) $f->flag_long,
                        'flag_number' => $f->flag_number ?? $f->flag_id,
                    ];
                }
            }
        }

        $historyFlagPoints = [];
        if (!empty($histories)) {
            foreach ($histories as $h) {
                if ($h->flags) {
                    foreach ($h->flags as $hf) {
                        if (!is_null($hf->history_flag_lat) && !is_null($hf->history_flag_long)) {
                            $historyFlagPoints[] = [
                                'lat' => (float) $hf->history_flag_lat,
                                'lng' => (float) $hf->history_flag_long,
                                'history_id' => $h->history_id,
                                'player' => $h->user->user_name ?? $h->user->name ?? 'Guest',
                                'reached' => $hf->history_flag_reached,
                                'point' => $hf->history_flag_point,
                            ];
                        }
                    }
                }
            }
        }
    @endphp

    <h3 class="mt-6 text-lg">Recent histories</h3>
    @if($histories && $histories->count())
        <div class="card" style="margin-top:1rem; padding:1rem">

        @section('scripts')
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script>
                (function(){
                    const labels = @json($labels ?? []);
                    const points = @json($pointsSeries ?? []);
                    const reached = @json($reachedSeries ?? []);
                    const totalPossiblePerHistory = {{ $run->flags_count }};
                    const overallReached = {{ $totalReached }};
                    const overallPossible = {{ $totalPossible }};

                    // Points over time (line)
                    const ctx1 = document.getElementById('pointsChart').getContext('2d');
                    new Chart(ctx1, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Points per play',
                                data: points,
                                borderColor: 'rgba(54,162,235,1)',
                                backgroundColor: 'rgba(54,162,235,0.2)',
                                fill: true,
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });

                    // Reached flags per play (bar)
                    const ctx2 = document.getElementById('reachedChart').getContext('2d');
                    new Chart(ctx2, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Flags reached',
                                data: reached,
                                backgroundColor: 'rgba(75,192,192,0.6)'
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });

                    // Overall completion (doughnut)
                    const ctx3 = document.getElementById('completionChart').getContext('2d');
                    new Chart(ctx3, {
                        type: 'doughnut',
                        data: {
                            labels: ['Reached', 'Remaining'],
                            datasets: [{
                                data: [overallReached, Math.max(0, overallPossible - overallReached)],
                                backgroundColor: ['#36A2EB', '#FF6384']
                            }]
                        },
                        options: { responsive: true, maintainAspectRatio: false }
                    });
                })();
            </script>

            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
                (function(){
                    const mapEl = document.getElementById('run-stats-map');
                    if (!mapEl) return;
                    const map = L.map(mapEl).setView([51.505, -0.09], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '&copy; OpenStreetMap contributors'
                    }).addTo(map);

                    const canonical = @json($canonicalPoints ?? []);
                    const historyPts = @json($historyFlagPoints ?? []);
                    const allBounds = [];

                    function escapeHtml(s){ if (s === null || s === undefined) return ''; return String(s).replace(/[&<>"'`]/g, function(ch){ return '&#'+ch.charCodeAt(0)+';'; }); }

                    canonical.forEach(c => {
                        try {
                            const lat = parseFloat(c.lat); const lng = parseFloat(c.lng);
                            if (isNaN(lat) || isNaN(lng)) return;
                            allBounds.push([lat, lng]);
                            const m = L.marker([lat, lng], { title: (c.flag_number || '') }).addTo(map);
                            m.bindPopup(`<strong>Flag ${escapeHtml(String(c.flag_number))}</strong>`);
                        } catch (e) {}
                    });

                    const params = new URLSearchParams(window.location.search);
                    const focusHistory = params.get('focusHistory');
                    const focusBounds = [];

                    historyPts.forEach(p => {
                        try {
                            const lat = parseFloat(p.lat); const lng = parseFloat(p.lng);
                            if (isNaN(lat) || isNaN(lng)) return;
                            allBounds.push([lat, lng]);

                            const isFocus = focusHistory && String(p.history_id) === String(focusHistory);
                            if (isFocus) {
                                focusBounds.push([lat, lng]);
                                const cm = L.circleMarker([lat, lng], { radius: 8, color: '#ff8c00', fillColor: '#ff8c00', fillOpacity: 1 }).addTo(map);
                                cm.bindPopup(`<strong>${escapeHtml(p.player)}</strong><br/>Reached: ${escapeHtml(p.reached || '')}<br/>Points: ${escapeHtml(String(p.point || ''))}`).openPopup();
                            } else {
                                const cm = L.circleMarker([lat, lng], { radius: 6, color: '#2ecc71', fillColor: '#2ecc71', fillOpacity: 0.9 }).addTo(map);
                                cm.bindPopup(`<strong>${escapeHtml(p.player)}</strong><br/>Reached: ${escapeHtml(p.reached || '')}<br/>Points: ${escapeHtml(String(p.point || ''))}`);
                            }
                        } catch (e) {}
                    });

                    // if focusing on a history, fit to its points; otherwise fit all
                    if (focusBounds.length) {
                        try { map.fitBounds(L.latLngBounds(focusBounds), { padding: [40,40] }); } catch (e) { /* fallback below */ }
                    } else if (allBounds.length) {
                        try { map.fitBounds(L.latLngBounds(allBounds), { padding: [40,40] }); } catch (e) { map.setView([51.505, -0.09], 13); }
                    }

                    if (allBounds.length) {
                        try { map.fitBounds(L.latLngBounds(allBounds), { padding: [40,40] }); } catch (e) { map.setView([51.505, -0.09], 13); }
                    }
                })();
            </script>
        @endsection
        <div style="overflow:auto; margin-top:1rem">
            <table class="table-auto w-full mt-2">
            <thead>
                <tr>
                    <th class="px-2 py-1 text-left">Player</th>
                    <th class="px-2 py-1 text-left">Started</th>
                    <th class="px-2 py-1 text-left">Ended</th>
                    <th class="px-2 py-1 text-left">Reached count</th>
                    <th class="px-2 py-1 text-left">Points</th>
                    <th class="px-2 py-1 text-left">Details</th>
                </tr>
            </thead>
            <tbody>
                @foreach($histories as $h)
                    @php
                        $reached = $h->flags ? $h->flags->filter(function($f){ return !is_null($f->history_flag_reached); })->count() : 0;
                        $points = $h->flags ? $h->flags->sum('history_flag_point') : 0;
                    @endphp
                    <tr class="border-t">
                        <td class="px-2 py-2">{{ $h->user->user_name ?? $h->user->name ?? 'Guest' }}</td>
                        <td class="px-2 py-2">{{ $h->history_start }}</td>
                        <td class="px-2 py-2">{{ $h->history_end ?? '-' }}</td>
                        <td class="px-2 py-2">{{ $reached }}</td>
                        <td class="px-2 py-2">{{ $points }}</td>
                        <td class="px-2 py-2"><a href="{{ route('history.show', $h->history_id) }}">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="card muted" style="margin-top:1rem; padding:.75rem">No plays recorded yet for this run.</div>
    @endif

@endsection
