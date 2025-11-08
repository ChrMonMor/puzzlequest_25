@extends('layouts.app')

@section('title', 'History details')

@section('content')
    <h2 class="text-xl font-semibold">History for {{ $history->run->run_title ?? '(untitled)' }}</h2>

    <div class="mt-4">
        @php
            use Carbon\Carbon;
            $started = $history->history_start ? Carbon::parse($history->history_start) : null;
            $ended = $history->history_end ? Carbon::parse($history->history_end) : null;
        @endphp

        <strong>Started:</strong>
        @if($started)
            {{ $started->toDayDateTimeString() }} <span class="text-sm text-gray-600">({{ $started->diffForHumans() }})</span>
        @else
            -
        @endif
        <br>

        <strong>Ended:</strong>
        @if($ended)
            {{ $ended->toDayDateTimeString() }} <span class="text-sm text-gray-600">({{ $ended->diffForHumans() }})</span>
        @else
            <em>In progress</em>
        @endif
        <br>

        <strong>Position:</strong> {{ $history->history_run_position ?? '-' }}<br>
        <strong>Run type:</strong> {{ $history->history_run_type ?? '-' }}
    </div>

    {{-- Summary metrics: flags reached, total points, completion percentage --}}
    @php
        $totalFlags = $history->flags ? $history->flags->count() : 0;
        $reachedCount = $history->flags ? $history->flags->filter(function($f){ return !is_null($f->history_flag_reached); })->count() : 0;
        // Sum points, coerce nulls to 0
        $totalPoints = 0;
        if ($history->flags) {
            foreach ($history->flags as $hf) {
                $totalPoints += (int) ($hf->history_flag_point ?? 0);
            }
        }
        $completionPercent = $totalFlags ? round(($reachedCount / $totalFlags) * 100, 1) : 0;
    @endphp

    <div class="mt-4 p-3 border rounded bg-gray-50">
        <strong>Summary:</strong>
        <div style="margin-top:.5rem">
            Flags reached: <strong>{{ $reachedCount }}</strong> / {{ $totalFlags }}
            &nbsp;|&nbsp; Total points: <strong>{{ $totalPoints }}</strong>
            &nbsp;|&nbsp; Completion: <strong>{{ $completionPercent }}%</strong>
        </div>
    </div>

    <div style="margin-top:1rem">
        <a href="{{ route('stats.show', auth()->user()->user_id) }}">Back to my histories</a>
        <a href="{{ route('runs.show', $history->run->run_id) }}" style="margin-left:1rem">View run</a>
    </div>

    <h3 class="mt-6 text-lg">Flags reached</h3>
    @if($history->flags && $history->flags->count())
        <table class="table-auto w-full mt-2">
            <thead>
                <tr>
                    <th class="px-2 py-1 text-left">#</th>
                    <th class="px-2 py-1 text-left">Reached</th>
                    <th class="px-2 py-1 text-left">Lat</th>
                    <th class="px-2 py-1 text-left">Long</th>
                    <th class="px-2 py-1 text-left">Distance</th>
                    <th class="px-2 py-1 text-left">Point</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history->flags as $idx => $hf)
                    <tr class="border-t">
                        <td class="px-2 py-2">{{ $idx + 1 }}</td>
                        <td class="px-2 py-2">{{ $hf->history_flag_reached ? 'Yes' : 'No' }}</td>
                        <td class="px-2 py-2">{{ $hf->history_flag_lat }}</td>
                        <td class="px-2 py-2">{{ $hf->history_flag_long }}</td>
                        <td class="px-2 py-2">{{ $hf->history_flag_distance ?? '-' }}</td>
                        <td class="px-2 py-2">{{ $hf->history_flag_point ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="text-sm text-gray-600">No flags recorded for this history.</div>
    @endif

    {{-- Map of history flags --}}
    @section('head')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
        <style>
            #history-map { height: 50vh; border-radius: .5rem; margin-top: 1rem; }
        </style>
    @endsection

    <div id="history-map"></div>

    @php
        $flagPoints = $history->flags->map(function($f){
            return [
                'lat' => $f->history_flag_lat,
                'lng' => $f->history_flag_long,
                'reached' => (bool)$f->history_flag_reached,
                'point' => $f->history_flag_point,
                'distance' => $f->history_flag_distance,
            ];
        })->toArray();
    @endphp

    @section('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            (function(){
                const mapEl = document.getElementById('history-map');
                if (!mapEl) return;
                const map = L.map(mapEl).setView([51.505, -0.09], 13);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(map);

                const flags = @json($flagPoints);

                const bounds = [];
                flags.forEach(f => {
                    if (!f.lat || !f.lng) return;
                    const lat = parseFloat(f.lat);
                    const lng = parseFloat(f.lng);
                    bounds.push([lat, lng]);
                    const marker = L.circleMarker([lat, lng], { radius: 8, color: f.reached ? 'green' : 'red' }).addTo(map);
                    marker.bindPopup('Reached: ' + (f.reached ? 'Yes' : 'No') + (f.point ? ('<br>Points: ' + f.point) : '') + (f.distance ? ('<br>Distance: ' + f.distance) : ''));
                });

                if (bounds.length) {
                    try { map.fitBounds(L.latLngBounds(bounds), { padding: [40,40] }); } catch(e) { map.setView([51.505, -0.09], 13); }
                }
            })();
        </script>
    @endsection

@endsection
