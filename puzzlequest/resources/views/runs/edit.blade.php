@extends('layouts.app')

@section('title', 'Edit Run — ' . ($run->run_title ?? 'Run'))

@section('content')
    <h2 class="text-xl font-semibold">Edit Run</h2>

    <div class="mt-4 bg-white p-4 rounded shadow-sm">
        <label class="block text-sm font-medium">Run Title</label>
        <input id="run-title" type="text" class="mt-1 block w-full border-gray-300 rounded-md p-2" value="{{ old('run_title', $run->run_title) }}" />

        <label class="block text-sm font-medium mt-3">Run Type</label>
        <select id="run-type" class="mt-1 block w-full border-gray-300 rounded-md p-2">
            <option value="">Loading...</option>
        </select>

        <label class="block text-sm font-medium mt-3">Description</label>
        <textarea id="run-description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md p-2">{{ old('run_description', $run->run_description) }}</textarea>

        <div class="mt-4 flex gap-2">
            <button id="save-run" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
            <button id="delete-run" class="px-4 py-2 bg-red-600 text-white rounded">Delete Run</button>
            <a href="{{ route('runs.show', $run->run_id) }}" class="ml-auto text-sm text-gray-600">Cancel</a>
        </div>
        <div id="status" class="mt-2 text-sm text-gray-600"></div>
    </div>

    {{-- Ensure a server-injected JWT is available to the script if present in session --}}
    <script>window.__SERVER_JWT = @json(session('jwt_token')); </script>

@endsection
@section('scripts')
    <script>
        (function(){
            function getAuthHeaders(){
                const headers = { 'Content-Type': 'application/json' };
                const token = localStorage.getItem('jwt') || localStorage.getItem('token') || (window.__SERVER_JWT || null);
                if (token) headers['Authorization'] = `Bearer ${token}`;
                return headers;
            }

            const runId = '{{ $run->run_id }}';
            const runTypeSelect = document.getElementById('run-type');
            const titleInput = document.getElementById('run-title');
            const descInput = document.getElementById('run-description');
            const statusEl = document.getElementById('status');

            async function loadRunTypes(){
                try{
                    const res = await fetch('/api/run-types/', { headers: getAuthHeaders() });
                    if (!res.ok) { runTypeSelect.innerHTML = '<option value="">(failed)</option>'; return; }
                    const types = await res.json();
                    runTypeSelect.innerHTML = '<option value="">Select run type</option>';
                    for (const t of types){
                        const opt = document.createElement('option');
                        opt.value = t.run_type_id;
                        opt.textContent = t.run_type_name || t.name || t.run_type_id;
                        runTypeSelect.appendChild(opt);
                    }
                    // set current value
                    runTypeSelect.value = '{{ $run->run_type }}';
                }catch(e){ console.error('Failed to load run types', e); }
            }

            async function saveRun(ev){
                ev && ev.preventDefault();
                statusEl.textContent = 'Saving...';
                const payload = {
                    run_title: titleInput.value.trim(),
                    run_description: descInput.value.trim(),
                    run_type: runTypeSelect.value ? parseInt(runTypeSelect.value,10) : null
                };
                try{
                    const res = await fetch('/api/runs/' + encodeURIComponent(runId), { method: 'PUT', headers: getAuthHeaders(), body: JSON.stringify(payload) });
                    if (!res.ok) {
                        const txt = await res.text().catch(()=>'');
                        statusEl.textContent = 'Save failed: ' + res.status + ' ' + txt;
                        return;
                    }
                    const json = await res.json();
                    statusEl.textContent = 'Saved.';
                    // Redirect to the run show page
                    window.location = '/runs/' + encodeURIComponent(runId);
                }catch(e){ console.error(e); statusEl.textContent = 'Save failed'; }
            }

            async function deleteRun(ev){
                ev && ev.preventDefault();
                if (!confirm('Delete this run and all its flags/questions? This action cannot be undone.')) return;
                statusEl.textContent = 'Deleting...';
                try{
                    const res = await fetch('/api/runs/' + encodeURIComponent(runId), { method: 'DELETE', headers: getAuthHeaders() });
                    if (!res.ok) {
                        const txt = await res.text().catch(()=>'');
                        statusEl.textContent = 'Delete failed: ' + res.status + ' ' + txt;
                        return;
                    }
                    // on success go back to runs index
                    window.location = '/runs';
                }catch(e){ console.error(e); statusEl.textContent = 'Delete failed'; }
            }

            document.getElementById('save-run').addEventListener('click', saveRun);
            document.getElementById('delete-run').addEventListener('click', deleteRun);

            loadRunTypes();
        })();
    </script>

    <!-- Leaflet map for editing flags -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        #edit-map { height: 50vh; border-radius: .5rem; margin-top: 1rem; }
        #flag-editor { margin-top: 1rem; }
        .option-row { display:flex; gap:8px; align-items:center; margin-bottom:6px }
    </style>

    <div id="edit-map"></div>
    <div id="flag-editor" class="bg-white p-4 rounded shadow-sm">
        <em>Select a pin to edit its question(s) and options.</em>
    </div>

    <script>
        (function(){
            const runId = '{{ $run->run_id }}';
            function getAuthHeaders(){
                const headers = { 'Content-Type': 'application/json' };
                const token = localStorage.getItem('jwt') || localStorage.getItem('token') || (window.__SERVER_JWT || null);
                if (token) headers['Authorization'] = `Bearer ${token}`;
                return headers;
            }

            const mapEl = document.getElementById('edit-map');
            if (!mapEl) return;
            const map = L.map(mapEl).setView([51.505, -0.09], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom:19 }).addTo(map);

            let currentMarkers = new Map(); // flag_id => marker

            async function loadFlags(){
                try{
                    const res = await fetch('/api/flags?run_id=' + encodeURIComponent(runId), { headers: getAuthHeaders() });
                    if (!res.ok) return;
                    const flags = await res.json();
                    // clear markers
                    currentMarkers.forEach(m => map.removeLayer(m));
                    currentMarkers.clear();

                    const bounds = [];
                    for (const f of flags){
                        if (!f.flag_lat || !f.flag_long) continue;
                        const lat = parseFloat(f.flag_lat); const lng = parseFloat(f.flag_long);
                        bounds.push([lat,lng]);
                        const marker = L.marker([lat,lng], { draggable: true }).addTo(map);
                        marker.bindPopup(`Flag #${f.flag_number || f.flag_id}`);
                        marker.on('click', () => openEditorForFlag(f));
                        marker.on('dragend', async () => {
                            const ll = marker.getLatLng();
                            try{
                                const r = await fetch('/api/flags/' + encodeURIComponent(f.flag_id), { method: 'PUT', headers: getAuthHeaders(), body: JSON.stringify({ flag_lat: ll.lat, flag_long: ll.lng }) });
                                if (!r.ok) { alert('Failed to save flag position'); }
                            }catch(e){ console.error(e); alert('Failed to save flag position'); }
                        });
                        currentMarkers.set(f.flag_id, marker);
                    }
                    if (bounds.length){ map.fitBounds(L.latLngBounds(bounds), { padding: [40,40] }); }
                }catch(e){ console.error('Failed to load flags', e); }
            }

            function clearEditor(){
                const ed = document.getElementById('flag-editor');
                ed.innerHTML = '<em>Select a pin to edit its question(s) and options.</em>';
            }

            function createOptionRow(option, questionId, questionAnswer, container){
                const row = document.createElement('div'); row.className='option-row';
                // radio indicates which option is the answer for the question
                const radio = document.createElement('input'); radio.type='radio'; radio.name = 'answer_' + questionId;
                radio.checked = !!(option.question_option_id && questionAnswer && option.question_option_id === questionAnswer);
                row.appendChild(radio);

                const input = document.createElement('input'); input.type='text'; input.value = option.question_option_text || '';
                const saveBtn = document.createElement('button'); saveBtn.textContent='Save';
                const delBtn = document.createElement('button'); delBtn.textContent='Delete';
                row.appendChild(input); row.appendChild(saveBtn); row.appendChild(delBtn);

                // when radio selected, update question.question_answer
                radio.addEventListener('change', async () => {
                    if (!radio.checked) return;
                    try{
                        const payload = { question_answer: option.question_option_id };
                        const res = await fetch('/api/questions/' + encodeURIComponent(questionId), { method: 'PUT', headers: getAuthHeaders(), body: JSON.stringify(payload) });
                        if (!res.ok) {
                            alert('Failed to set answer');
                            radio.checked = false;
                        } else {
                            // success: nothing else to do; leave radio checked
                        }
                    }catch(e){ console.error(e); alert('Failed to set answer'); radio.checked = false; }
                });

                saveBtn.addEventListener('click', async () => {
                    try{
                        const res = await fetch('/api/question-options/' + encodeURIComponent(option.question_option_id), { method: 'PUT', headers: getAuthHeaders(), body: JSON.stringify({ question_option_text: input.value.trim() }) });
                        if (!res.ok) alert('Failed to update option');
                    }catch(e){ console.error(e); alert('Failed to update option'); }
                });

                delBtn.addEventListener('click', async () => {
                    if (!confirm('Delete this option?')) return;
                    try{
                        const res = await fetch('/api/question-options/' + encodeURIComponent(option.question_option_id), { method: 'DELETE', headers: getAuthHeaders() });
                        if (!res.ok) { alert('Failed to delete option'); return; }
                        row.remove();
                    }catch(e){ console.error(e); alert('Failed to delete option'); }
                });

                container.appendChild(row);
            }

            async function openEditorForFlag(flag){
                const ed = document.getElementById('flag-editor');
                ed.innerHTML = '';
                const h = document.createElement('h3'); h.textContent = 'Flag #' + (flag.flag_number || flag.flag_id);
                ed.appendChild(h);

                // Questions list
                const qList = document.createElement('div');
                if (!flag.questions || !flag.questions.length){
                    qList.innerHTML = '<div>No questions on this flag.</div>';
                }
                for (const q of (flag.questions || [])){
                    const qBox = document.createElement('div'); qBox.className='mb-3 p-2 border rounded';
                    const qt = document.createElement('textarea'); qt.rows=2; qt.className='w-full'; qt.value = q.question_text || '';
                    const typeSel = document.createElement('input'); typeSel.type='number'; typeSel.value = q.question_type || '';
                    const saveQ = document.createElement('button'); saveQ.textContent='Save Question';
                    const delQ = document.createElement('button'); delQ.textContent='Delete Question';
                    qBox.appendChild(qt); qBox.appendChild(document.createElement('br'));
                    qBox.appendChild(document.createTextNode('Type: ')); qBox.appendChild(typeSel); qBox.appendChild(document.createElement('br'));
                    qBox.appendChild(saveQ); qBox.appendChild(delQ);

                    // options container
                    const optsDiv = document.createElement('div'); optsDiv.className='mt-2';
                    if (q.options && q.options.length){
                        for (const opt of q.options){ createOptionRow(opt, q.question_id, q.question_answer, optsDiv); }
                    }
                    // add option UI
                    const newOptInput = document.createElement('input'); newOptInput.type='text'; newOptInput.placeholder='New option text';
                    const addOptBtn = document.createElement('button'); addOptBtn.textContent='Add Option';
                    addOptBtn.addEventListener('click', async () => {
                        const text = newOptInput.value.trim(); if (!text) return alert('Option text required');
                        try{
                            const res = await fetch('/api/question-options/', { method: 'POST', headers: getAuthHeaders(), body: JSON.stringify({ question_id: q.question_id, question_option_text: text }) });
                            if (!res.ok) { alert('Failed to create option'); return; }
                            const json = await res.json();
                            const opt = json.option || json;
                            createOptionRow(opt, q.question_id, q.question_answer, optsDiv);
                            newOptInput.value = '';
                        }catch(e){ console.error(e); alert('Failed to create option'); }
                    });

                    qBox.appendChild(optsDiv);
                    qBox.appendChild(newOptInput); qBox.appendChild(addOptBtn);

                    saveQ.addEventListener('click', async () => {
                        try{
                            const payload = { question_text: qt.value.trim(), question_type: parseInt(typeSel.value || 0,10) };
                            const res = await fetch('/api/questions/' + encodeURIComponent(q.question_id), { method: 'PUT', headers: getAuthHeaders(), body: JSON.stringify(payload) });
                            if (!res.ok) { alert('Failed to save question'); return; }
                            alert('Saved');
                        }catch(e){ console.error(e); alert('Failed to save question'); }
                    });

                    delQ.addEventListener('click', async () => {
                        if (!confirm('Delete this question?')) return;
                        try{
                            const res = await fetch('/api/questions/' + encodeURIComponent(q.question_id), { method: 'DELETE', headers: getAuthHeaders() });
                            if (!res.ok) { alert('Failed to delete question'); return; }
                            qBox.remove();
                        }catch(e){ console.error(e); alert('Failed to delete question'); }
                    });

                    qList.appendChild(qBox);
                }

                // Delete flag button (also delete questions)
                const delFlagBtn = document.createElement('button'); delFlagBtn.textContent = 'Delete Flag'; delFlagBtn.className='mt-2';
                delFlagBtn.addEventListener('click', async () => {
                    if (!confirm('Delete this flag and its questions?')) return;
                    try{
                        // delete questions
                        for (const q of (flag.questions || [])){
                            try { await fetch('/api/questions/' + encodeURIComponent(q.question_id), { method: 'DELETE', headers: getAuthHeaders() }); } catch(e){}
                        }
                        const r = await fetch('/api/flags/' + encodeURIComponent(flag.flag_id), { method: 'DELETE', headers: getAuthHeaders() });
                        if (!r.ok) { alert('Failed to delete flag'); return; }
                        // remove marker
                        const m = currentMarkers.get(flag.flag_id); if (m) map.removeLayer(m);
                        clearEditor();
                    }catch(e){ console.error(e); alert('Failed to delete flag'); }
                });

                ed.appendChild(qList);
                ed.appendChild(delFlagBtn);
            }

            // initial load
            loadFlags();
        })();
    </script>
@endsection
