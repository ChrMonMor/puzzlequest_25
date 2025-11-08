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
        <div class="mt-2 flex items-center gap-2">
            <div class="text-sm text-gray-700">Pin: <strong id="run-pin-display">{{ $run->run_pin ?? '(none)' }}</strong></div>
            <button id="generate-pin" class="px-3 py-1 bg-indigo-600 text-white rounded text-sm">Generate Pin</button>
            <button id="clear-pin" class="px-2 py-1 bg-gray-200 text-sm rounded">Clear</button>
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
            const genBtn = document.getElementById('generate-pin');
            const pinDisplay = document.getElementById('run-pin-display');
            const clearPinBtn = document.getElementById('clear-pin');
            if (genBtn) genBtn.addEventListener('click', async () => {
                if (!confirm('Generate a new unique 6-character pin for this run?')) return;
                try{
                    genBtn.disabled = true; genBtn.textContent = 'Generating...';
                    const res = await fetch('/api/runs/' + encodeURIComponent(runId) + '/generate-pin', { method: 'POST', headers: getAuthHeaders() });
                    if (!res.ok) { const txt = await res.text().catch(()=>''); alert('Failed to generate pin: '+res.status+' '+txt); return; }
                    const j = await res.json();
                    pinDisplay.textContent = j.pin || (j.run && j.run.run_pin) || '(none)';
                    alert('Pin generated: ' + pinDisplay.textContent);
                }catch(e){ console.error(e); alert('Failed to generate pin'); }
                finally{ genBtn.disabled = false; genBtn.textContent = 'Generate Pin'; }
            });
            if (clearPinBtn) clearPinBtn.addEventListener('click', async () => {
                if (!confirm('Clear the run pin?')) return;
                try{
                    const res = await fetch('/api/runs/' + encodeURIComponent(runId), { method: 'PUT', headers: getAuthHeaders(), body: JSON.stringify({ run_pin: null }) });
                    if (!res.ok) { const txt = await res.text().catch(()=>''); alert('Failed to clear pin: '+res.status+' '+txt); return; }
                    pinDisplay.textContent = '(none)';
                }catch(e){ console.error(e); alert('Failed to clear pin'); }
            });

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

    <div class="mt-2 mb-2">
        <button id="add-pin-btn" class="px-3 py-1 bg-green-600 text-white rounded">Add Pin</button>
        <span id="add-pin-hint" class="ml-2 text-sm text-gray-600"></span>
    </div>
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

            // question types for dropdowns in the editor
            let questionTypes = [];
            async function loadQuestionTypes(){
                try{
                    const res = await fetch('/api/question-types/', { headers: getAuthHeaders() });
                    if (!res.ok) return;
                    questionTypes = await res.json();
                }catch(e){ console.error('Failed to load question types', e); }
            }

            let currentMarkers = new Map(); // flag_id => marker
            let pendingMarkers = new Map(); // tempId => pending flag object
            let addingPin = false;
            const addPinBtn = document.getElementById('add-pin-btn');
            const addPinHint = document.getElementById('add-pin-hint');
            if (addPinBtn) {
                addPinBtn.addEventListener('click', () => {
                    addingPin = !addingPin;
                    addPinBtn.textContent = addingPin ? 'Click map to place pin — Cancel' : 'Add Pin';
                    addPinHint.textContent = addingPin ? 'Click anywhere on the map to place a new pin.' : '';
                    if (addingPin) {
                        // one-time map click to place new pin
                        map.once('click', (e) => {
                            createPendingFlag(e.latlng);
                            addingPin = false;
                            addPinBtn.textContent = 'Add Pin';
                            addPinHint.textContent = '';
                        });
                    }
                });
            }

            // Helpers for pending flags/questions UI (client-only until saved)
            function renderPendingQuestionEditor(q, container, pendingFlag){
                const qBox = document.createElement('div'); qBox.className='mb-3 p-2 border rounded';
                const qt = document.createElement('textarea'); qt.rows=2; qt.className='w-full'; qt.value = q.question_text || '';
                // question type select populated from questionTypes
                const typeSel = document.createElement('select');
                const emptyOpt3 = document.createElement('option'); emptyOpt3.value=''; emptyOpt3.textContent='Select type'; typeSel.appendChild(emptyOpt3);
                for (const t of questionTypes){ const o = document.createElement('option'); o.value = t.question_type_id; o.textContent = t.question_type_name || t.name || t.question_type_id; typeSel.appendChild(o); }
                typeSel.value = q.question_type || '';
                const delQ = document.createElement('button'); delQ.textContent='Remove Question';
                qBox.appendChild(qt); qBox.appendChild(document.createElement('br'));
                qBox.appendChild(document.createTextNode('Type: ')); qBox.appendChild(typeSel); qBox.appendChild(document.createElement('br'));

                const optsDiv = document.createElement('div'); optsDiv.className='mt-2';
                q.options = q.options || [];
                q.options.forEach((opt, idx) => {
                    createPendingOptionRow(opt, q, idx, optsDiv);
                });

                const newOptInput = document.createElement('input'); newOptInput.type='text'; newOptInput.placeholder='New option text';
                const addOptBtn = document.createElement('button'); addOptBtn.textContent='Add Option';
                addOptBtn.addEventListener('click', () => {
                    const text = newOptInput.value.trim(); if (!text) return alert('Option text required');
                    const opt = { question_option_text: text };
                    q.options.push(opt);
                    createPendingOptionRow(opt, q, q.options.length-1, optsDiv);
                    newOptInput.value = '';
                });

                delQ.addEventListener('click', () => {
                    // remove from pendingFlag.questions
                    const idx = pendingFlag.questions.indexOf(q);
                    if (idx !== -1) pendingFlag.questions.splice(idx,1);
                    qBox.remove();
                });

                qBox.appendChild(optsDiv);
                qBox.appendChild(newOptInput); qBox.appendChild(addOptBtn); qBox.appendChild(delQ);
                container.appendChild(qBox);
            }

            function createPendingOptionRow(opt, q, idx, container){
                const row = document.createElement('div'); row.className='option-row';
                const radio = document.createElement('input'); radio.type='radio'; radio.name = 'pending_answer_' + q._tempId; radio.value = '' + idx;
                radio.checked = (q.selectedAnswerIndex === idx);
                radio.addEventListener('change', () => { if (radio.checked) q.selectedAnswerIndex = idx; });
                const span = document.createElement('span'); span.textContent = opt.question_option_text || '';
                row.appendChild(radio); row.appendChild(span);
                container.appendChild(row);
            }

            function createPendingFlag(latlng){
                const tempId = 'temp_' + Date.now();
                const marker = L.marker([latlng.lat, latlng.lng], { draggable: true }).addTo(map);
                marker.bindPopup('New Flag').openPopup();
                const pending = { _isPending: true, _tempId: tempId, flag_lat: latlng.lat, flag_long: latlng.lng, questions: [] , marker };
                marker.on('click', () => openEditorForFlag(pending));
                marker.on('dragend', () => { const ll = marker.getLatLng(); pending.flag_lat = ll.lat; pending.flag_long = ll.lng; });
                pendingMarkers.set(tempId, pending);
                openEditorForFlag(pending);
            }

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
                // ensure radio carries the option id so saves can read the selected id
                radio.value = option.question_option_id || '';
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
                    // mark question id for later selection/highlighting
                    if (q.question_id) qBox.dataset.questionId = q.question_id;
                    const qt = document.createElement('textarea'); qt.rows=2; qt.className='w-full'; qt.value = q.question_text || '';
                    // type select populated from questionTypes
                    const typeSel = document.createElement('select');
                    const emptyOpt = document.createElement('option'); emptyOpt.value=''; emptyOpt.textContent='Select type'; typeSel.appendChild(emptyOpt);
                    for (const t of questionTypes){ const o = document.createElement('option'); o.value = t.question_type_id; o.textContent = t.question_type_name || t.name || t.question_type_id; typeSel.appendChild(o); }
                    typeSel.value = q.question_type || '';
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
                            // detect selected answer radio for this question and include it in update payload
                            const selected = document.querySelector(`input[name="answer_${q.question_id}"]:checked`);
                            const payload = { question_text: qt.value.trim(), question_type: parseInt(typeSel.value || 0,10), question_answer: selected ? selected.value : null };
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

                // If this is a pending flag, allow adding questions locally and saving the flag+
                if (flag._isPending) {
                    const addQbtn = document.createElement('button'); addQbtn.textContent = 'Add Question'; addQbtn.className='mr-2';
                    addQbtn.addEventListener('click', () => {
                        const newQ = { _tempId: 'q_' + Date.now(), question_text: '', question_type: null, options: [], selectedAnswerIndex: null };
                        flag.questions = flag.questions || [];
                        flag.questions.push(newQ);
                        renderPendingQuestionEditor(newQ, qList, flag);
                    });

                    const saveFlagBtn = document.createElement('button'); saveFlagBtn.textContent = 'Save Flag'; saveFlagBtn.className='px-3 py-1 bg-blue-600 text-white rounded';
                    saveFlagBtn.addEventListener('click', async () => {
                        // create flag first
                        try{
                            const payload = { run_id: runId, flag_lat: flag.flag_lat, flag_long: flag.flag_long };
                            const res = await fetch('/api/flags', { method: 'POST', headers: getAuthHeaders(), body: JSON.stringify(payload) });
                            if (!res.ok) { const txt = await res.text().catch(()=>''); return alert('Failed to create flag: '+res.status+' '+txt); }
                            const json = await res.json();
                            const createdFlag = json.flag || json;

                            // create questions sequentially for this flag
                            for (const q of (flag.questions || [])){
                                const optionsPayload = (q.options || []).map(o => ({ question_option_text: o.question_option_text }));
                                const qPayload = { run_id: runId, flag_id: createdFlag.flag_id, question_type: parseInt(q.question_type || 0,10), question_text: q.question_text || '' };
                                if (q.options && q.options.length) qPayload.options = optionsPayload;
                                const rq = await fetch('/api/questions', { method: 'POST', headers: getAuthHeaders(), body: JSON.stringify(qPayload) });
                                if (!rq.ok) { console.error('Failed to create question', await rq.text()); continue; }
                                const qjson = await rq.json();
                                const createdQ = qjson.question || qjson;
                                // if the user selected a non-first option as answer, update question_answer
                                if (typeof q.selectedAnswerIndex === 'number' && q.selectedAnswerIndex > 0 && createdQ.options && createdQ.options.length > q.selectedAnswerIndex) {
                                    const chosen = createdQ.options[q.selectedAnswerIndex];
                                    if (chosen && chosen.question_option_id) {
                                        await fetch('/api/questions/' + encodeURIComponent(createdQ.question_id), { method: 'PUT', headers: getAuthHeaders(), body: JSON.stringify({ question_answer: chosen.question_option_id }) });
                                    }
                                }
                            }

                            // remove pending marker and reload flags
                            try { flag.marker && map.removeLayer(flag.marker); } catch(e){}
                            pendingMarkers.delete(flag._tempId);
                            await loadFlags();
                            clearEditor();
                        }catch(e){ console.error(e); alert('Failed to save flag and questions'); }
                    });

                    const discardBtn = document.createElement('button'); discardBtn.textContent='Discard'; discardBtn.className='ml-2';
                    discardBtn.addEventListener('click', () => {
                        if (!confirm('Discard this new pin?')) return;
                        try { flag.marker && map.removeLayer(flag.marker); } catch(e){}
                        pendingMarkers.delete(flag._tempId);
                        clearEditor();
                    });

                    ed.appendChild(addQbtn);
                    ed.appendChild(saveFlagBtn);
                    ed.appendChild(discardBtn);
                }

                // If this is an existing (saved) flag, allow adding a new question via the API
                if (!flag._isPending) {
                    const addQbtn2 = document.createElement('button'); addQbtn2.textContent = 'Add Question'; addQbtn2.className='mr-2';
                    const newQBox = document.createElement('div'); newQBox.style.marginTop = '8px';
                    let newQVisible = false;

                    addQbtn2.addEventListener('click', () => {
                        if (newQVisible) { newQBox.innerHTML = ''; newQVisible = false; addQbtn2.textContent = 'Add Question'; return; }
                        newQVisible = true; addQbtn2.textContent = 'Cancel';

                        // build inline new question form
                        const qBox = document.createElement('div'); qBox.className='mb-3 p-2 border rounded';
                        const qt = document.createElement('textarea'); qt.rows=2; qt.className='w-full'; qt.placeholder='Enter question text';
                        // question type select populated from questionTypes
                        const typeSel = document.createElement('select');
                        const emptyOpt2 = document.createElement('option'); emptyOpt2.value=''; emptyOpt2.textContent='Select type'; typeSel.appendChild(emptyOpt2);
                        for (const t of questionTypes){ const o = document.createElement('option'); o.value = t.question_type_id; o.textContent = t.question_type_name || t.name || t.question_type_id; typeSel.appendChild(o); }
                        const optsDiv = document.createElement('div'); optsDiv.className='mt-2';
                        const newOptInput = document.createElement('input'); newOptInput.type='text'; newOptInput.placeholder='New option text';
                        const addOptBtn = document.createElement('button'); addOptBtn.textContent='Add Option'; addOptBtn.className='ml-2';
                        addOptBtn.addEventListener('click', () => {
                            const text = newOptInput.value.trim(); if (!text) return alert('Option text required');
                            const row = document.createElement('div'); row.className='option-row';
                            row.textContent = text;
                            // store text on dataset for later collection
                            row.dataset.optText = text;
                            optsDiv.appendChild(row);
                            newOptInput.value = '';
                        });

                        const saveNewQ = document.createElement('button'); saveNewQ.textContent='Save Question'; saveNewQ.className='px-2 py-1 bg-blue-600 text-white rounded';
                        saveNewQ.addEventListener('click', async () => {
                            const qText = (qt.value || '').trim(); if (!qText) return alert('Question text required');
                            const qType = parseInt(typeSel.value || 0,10) || 1;
                            const opts = Array.from(optsDiv.querySelectorAll('.option-row')).map(r => ({ question_option_text: r.dataset.optText || r.textContent }));
                            const payload = { run_id: runId, flag_id: flag.flag_id, question_type: qType, question_text: qText };
                            if (opts.length) payload.options = opts;
                            try{
                                const res = await fetch('/api/questions', { method: 'POST', headers: getAuthHeaders(), body: JSON.stringify(payload) });
                                if (!res.ok) { const txt = await res.text().catch(()=>''); return alert('Failed to create question: '+res.status+' '+txt); }
                                const qjson = await res.json();
                                const createdQ = qjson.question || qjson;
                                // fetch updated flag and re-open editor for this flag (no full flags reload)
                                const fres = await fetch('/api/flags/' + encodeURIComponent(flag.flag_id), { headers: getAuthHeaders() });
                                if (fres.ok) {
                                    const fjson = await fres.json();
                                    openEditorForFlag(fjson);
                                    // highlight newly created question
                                    setTimeout(() => {
                                        const el = document.querySelector(`[data-question-id="${createdQ.question_id}"]`);
                                        if (el) {
                                            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                            el.style.boxShadow = '0 0 0 3px rgba(59,130,246,0.35)';
                                            setTimeout(() => { el.style.boxShadow = ''; }, 3000);
                                        }
                                    }, 250);
                                }
                            }catch(e){ console.error(e); alert('Failed to create question'); }
                        });

                        qBox.appendChild(qt); qBox.appendChild(document.createElement('br'));
                        qBox.appendChild(document.createTextNode('Type: ')); qBox.appendChild(typeSel); qBox.appendChild(document.createElement('br'));
                        qBox.appendChild(optsDiv); qBox.appendChild(newOptInput); qBox.appendChild(addOptBtn); qBox.appendChild(document.createElement('br'));
                        qBox.appendChild(saveNewQ);
                        newQBox.appendChild(qBox);
                    });

                    ed.appendChild(addQbtn2);
                    ed.appendChild(newQBox);
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

            // initial load: load question types first, then flags
            loadQuestionTypes().then(() => loadFlags()).catch(() => loadFlags());
        })();
    </script>
@endsection
