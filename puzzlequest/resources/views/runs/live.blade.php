@extends('layouts.app')

@section('title', 'Live: ' . ($run->run_title ?? 'Run'))

@section('head')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<style>
    #live-map { height: 70vh; border-radius: .5rem; margin-top: 1rem; }
    .runner-legend { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.75rem; }
    .legend-item { display:flex; align-items:center; gap:.5rem; padding:.25rem .5rem; border:1px solid #e5e7eb; border-radius:.375rem; }
    .legend-swatch { width:14px; height:14px; border-radius:3px; }
</style>
@endsection

@section('content')
<div class="card">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1rem">
        <div style="flex:1">
            <h2 class="text-xl font-semibold" style="margin:0">Live runners — {{ $run->run_title ?? '(untitled)' }}</h2>
            <div class="muted" style="margin-top:.25rem; font-size:.95rem">Only visible to you (owner)</div>
        </div>
        <div>
            <a href="{{ route('runs.show', $run->run_id) }}" class="btn btn-secondary">Back to run</a>
        </div>
    </div>

    <div style="margin-top:.75rem; display:flex; gap:1rem; align-items:center; flex-wrap:wrap;">
        <div id="live-status" class="muted">Last updated: --</div>
        <button id="toggle-poll" type="button" class="btn btn-secondary">Pause</button>
    </div>
    <div id="live-map"></div>
    <div class="runner-legend" id="runner-legend"></div>
</div>
@endsection

@section('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function(){
    const runId = @json($run->run_id);
    const mapEl = document.getElementById('live-map');
    if (!mapEl) return;
    const map = L.map(mapEl).setView([51.505, -0.09], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Utility: consistent color per user_id
    const colorCache = new Map();
    function colorFor(id){
        if (!id) return '#7c3aed';
        if (colorCache.has(id)) return colorCache.get(id);
        const colors = ['#ef4444','#f59e0b','#10b981','#3b82f6','#8b5cf6','#ec4899','#14b8a6','#84cc16'];
        // simple hash
        let h = 0; String(id).split('').forEach(c => { h = (h*31 + c.charCodeAt(0)) >>> 0; });
        const col = colors[h % colors.length];
        colorCache.set(id, col);
        return col;
    }

    const layers = new Map(); // history_id -> { polyline, marker }

    function updateLegend(runners){
        const legend = document.getElementById('runner-legend');
        legend.innerHTML = '';
        runners.forEach(r => {
            const color = colorFor(r.user.user_id || r.history_id);
            const item = document.createElement('div');
            item.className = 'legend-item';
            const sw = document.createElement('span');
            sw.className = 'legend-swatch';
            sw.style.background = color;
            const name = document.createElement('span');
            name.textContent = r.user.name || 'Guest';
            item.appendChild(sw);
            item.appendChild(name);
            legend.appendChild(item);
        });
    }

    function fitMap(bounds){
        if (!bounds.length) return;
        try { map.fitBounds(L.latLngBounds(bounds), { padding: [40, 40] }); } catch(e) {}
    }

    async function fetchData(){
        const res = await fetch(`{{ route('runs.live.data', $run->run_id) }}`);
        if (!res.ok) return null;
        return res.json();
    }

    function formatAgo(iso){
        if (!iso) return 'no flags yet';
        const then = new Date(iso);
        const diff = (Date.now() - then.getTime()) / 1000;
        if (diff < 5) return 'just now';
        const h = Math.floor(diff / 3600);
        const m = Math.floor((diff % 3600) / 60);
        const s = Math.floor(diff % 60);
        let out = '';
        if (h) out += h + 'h ';
        if (m) out += m + 'm ';
        out += s + 's ago';
        return out.trim();
    }

    function render(data){
        if (!data || !Array.isArray(data.runners)) return;
        const allBounds = [];
        // Remove layers for histories no longer present
        const present = new Set(data.runners.map(r => r.history_id));
        for (const [hid, grp] of layers.entries()){
            if (!present.has(hid)){
                if (grp.polyline) grp.polyline.remove();
                if (grp.marker) grp.marker.remove();
                layers.delete(hid);
            }
        }
        data.runners.forEach(r => {
            const color = colorFor(r.user.user_id || r.history_id);
            const latlngs = (r.path || []).map(p => [p.lat, p.lng]).filter(p => !isNaN(p[0]) && !isNaN(p[1]));
            const lastPoint = r.last && !isNaN(parseFloat(r.last.lat)) && !isNaN(parseFloat(r.last.lng)) ? [parseFloat(r.last.lat), parseFloat(r.last.lng)] : null;
            if (latlngs.length){ allBounds.push(...latlngs); }
            if (lastPoint){ allBounds.push(lastPoint); }

            let grp = layers.get(r.history_id);
            if (!grp){ grp = {}; layers.set(r.history_id, grp); }

            if (grp.polyline){ grp.polyline.setLatLngs(latlngs); grp.polyline.setStyle({ color }); }
            else if (latlngs.length){ grp.polyline = L.polyline(latlngs, { color, weight: 4 }).addTo(map); }

            const status = formatAgo(r.last ? r.last.reached_at : null);
            const popupHtml = `<strong>${(r.user && r.user.name) ? r.user.name : 'Guest'}</strong><br><span class="muted">${status}</span>`;

            if (grp.marker){ if (lastPoint) grp.marker.setLatLng(lastPoint); if (grp.marker.getPopup()) grp.marker.getPopup().setContent(popupHtml); else grp.marker.bindPopup(popupHtml); }
            else if (lastPoint){
                grp.marker = L.circleMarker(lastPoint, { radius: 6, color: color, weight: 2, fillColor: color, fillOpacity: .8 }).addTo(map);
                grp.marker.bindPopup(popupHtml);
            }
        });

        // Fit map initially if we don't have a set view
        if (!render.didFit && allBounds.length){ fitMap(allBounds); render.didFit = true; }
        updateLegend(data.runners);
    }

    const statusEl = document.getElementById('live-status');
    const toggleBtn = document.getElementById('toggle-poll');
    let paused = false;
    let lastUpdateIso = null;

    function updateStatus(){
        if (!statusEl) return;
        const label = paused ? 'Paused' : 'Active';
        const ts = lastUpdateIso ? new Date(lastUpdateIso) : null;
        const tsStr = ts ? ts.toLocaleTimeString() : '--';
        statusEl.textContent = `Status: ${label} · Last updated: ${tsStr}`;
    }

    toggleBtn && toggleBtn.addEventListener('click', function(){
        paused = !paused;
        toggleBtn.textContent = paused ? 'Resume' : 'Pause';
        updateStatus();
    });

    async function tick(){
        if (paused) { updateStatus(); return; }
        try {
            const data = await fetchData();
            if (data && data.updated) lastUpdateIso = data.updated;
            render(data);
        } catch(e) { console.error('live fetch failed', e); }
        updateStatus();
    }

    // initial draw + polling
    tick();
    const intervalMs = 5000; // poll every 5s
    const timer = setInterval(tick, intervalMs);

    // Cleanup on navigation
    window.addEventListener('beforeunload', () => clearInterval(timer));
})();
</script>
@endsection
