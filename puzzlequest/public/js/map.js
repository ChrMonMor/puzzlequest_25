document.addEventListener('DOMContentLoaded', () => {
    // --- Helper to get JWT auth headers ---
    function getAuthHeaders() {
        const headers = { 'Content-Type': 'application/json' };
        const token = localStorage.getItem('jwt') || localStorage.getItem('token') || (window.__SERVER_JWT || null);
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    // Small utility to escape HTML for safe insertion into popups
    function escapeHtml(unsafe) {
        if (!unsafe && unsafe !== 0) return '';
        return String(unsafe)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // --- Map setup ---
    const mapEl = document.getElementById('map');
    if (!mapEl) return;

    const map = L.map(mapEl);
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            pos => map.setView([pos.coords.latitude, pos.coords.longitude], 13),
            () => map.setView([51.505, -0.09], 13)
        );
    } else map.setView([51.505, -0.09], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // --- DOM Elements ---
    const runTitleInput = document.getElementById('run-title');
    const runDescInput = document.getElementById('run-description');
    const runTypeSelect = document.getElementById('run-type');
    const createRunBtn = document.getElementById('create-run');
    const createRunStatus = document.getElementById('create-run-status');
    const currentRunDisplay = document.getElementById('current-run');

    // State
    let currentRunId = null;
    let activeTempMarker = null;
    let tempMarkerCounter = 1; // human-readable temporary ids
    const savedMarkers = new Map(); // map of tempId or server flag_id -> Leaflet marker
    const pendingFlags = []; // client-side collection of pending flags { tempId, lat, lng, marker, questions: [] }
    let questionTypes = [];

    // DOM helpers used elsewhere
    const questionsContainer = document.getElementById('questions-container');
    const pinSelector = document.getElementById('pin-selector');

    // Create run handler (user may still create manually; Save All will create a run when needed)
    if (createRunBtn) {
        createRunBtn.addEventListener('click', async (ev) => {
            ev.preventDefault();
            if (!createRunStatus) return;
            createRunStatus.textContent = 'Creating...';

            const title = runTitleInput.value.trim();
            const desc = runDescInput.value.trim();
            const runTypeId = runTypeSelect.value;

            if (!title || !runTypeId) {
                createRunStatus.textContent = 'Title and run type are required.';
                return;
            }

            try {
                const res = await fetch('/api/runs/', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify({ run_title: title, run_description: desc, run_type: parseInt(runTypeId, 10) })
                });

                if (!res.ok) throw new Error(`Status ${res.status}`);
                const run = (await res.json()).run || (await res.json());
                currentRunId = run?.run_id || null;
                currentRunDisplay.textContent = `Current run: ${run?.run_title || 'Run'}`;
                createRunStatus.textContent = 'Created.';
            } catch(e) {
                console.error(e);
                createRunStatus.textContent = 'Failed to create run.';
            }
        });
    }

    // --- Load Pins ---
    async function loadPins() {
        if (!pinSelector) return;

        // Only fetch if a run is selected
        if (!currentRunId) {
            console.warn('No run selected; skipping loadPins');
            // Clear any existing markers and dropdown
            savedMarkers.forEach(m => map.removeLayer(m));
            savedMarkers.clear();
            pinSelector.innerHTML = '<option value="">-- Select a pin --</option>';
            return;
        }

        try {
            const url = `/api/flags?run_id=${encodeURIComponent(currentRunId)}`;
            const res = await fetch(url, { headers: getAuthHeaders() });
            if (!res.ok) return;
            const flags = await res.json();

            // Clear old markers
            savedMarkers.forEach(m => map.removeLayer(m));
            savedMarkers.clear();

            // Clear pin selector
            pinSelector.innerHTML = '<option value="">-- Select a pin --</option>';

            flags.forEach(flag => {
                if (!flag.flag_lat || !flag.flag_long) return;

                const lat = parseFloat(flag.flag_lat);
                const lng = parseFloat(flag.flag_long);
                const marker = L.marker([lat, lng], { draggable: true }).addTo(map);

                const question = flag.questions?.[0];
                const title = question ? question.question_text : `Flag #${flag.flag_number || flag.flag_id}`;
                // include a delete button in the popup so pins can be removed
                const popupHtml = `<div><strong>${escapeHtml(title)}</strong>` +
                    `<div class="mt-1 text-sm">Flag #: ${escapeHtml(flag.flag_number || flag.flag_id)}</div>` +
                    `<div style="margin-top:.5rem"><button id="del-flag-${flag.flag_id}" type="button">Delete</button></div>` +
                    `</div>`;
                marker.bindPopup(popupHtml);

                // when popup opens, attach delete handler (idempotent)
                marker.on('popupopen', () => {
                    const btn = document.getElementById(`del-flag-${flag.flag_id}`);
                    if (btn && !btn.dataset.bound) {
                        btn.dataset.bound = '1';
                        btn.addEventListener('click', async (ev) => {
                            ev.preventDefault();
                            if (!confirm('Delete this flag and its questions?')) return;
                            try {
                                // delete associated questions first (if any) so UI stays consistent
                                if (Array.isArray(flag.questions) && flag.questions.length) {
                                    for (const q of flag.questions) {
                                        try {
                                            await fetch(`/api/questions/${encodeURIComponent(q.question_id)}`, { method: 'DELETE', headers: getAuthHeaders() });
                                        } catch (e) { console.warn('Failed to delete question', q, e); }
                                    }
                                }

                                const res = await fetch(`/api/flags/${encodeURIComponent(flag.flag_id)}`, { method: 'DELETE', headers: getAuthHeaders() });
                                if (!res.ok) {
                                    const txt = await res.text().catch(() => '');
                                    alert('Failed to delete flag: ' + res.status + ' ' + txt);
                                    return;
                                }
                                map.removeLayer(marker);
                                savedMarkers.delete(flag.flag_id);
                                // remove option from pin selector if present
                                const opt = pinSelector.querySelector(`option[value="${flag.flag_id}"]`);
                                if (opt) opt.remove();
                            } catch (err) { console.error('Delete failed', err); alert('Delete failed'); }
                        });
                    }
                });

                // on drag end, attempt to persist new position to server
                marker.on('dragend', async () => {
                    const ll = marker.getLatLng();
                    try {
                        const res = await fetch(`/api/flags/${encodeURIComponent(flag.flag_id)}`, {
                            method: 'PUT',
                            headers: getAuthHeaders(),
                            body: JSON.stringify({ flag_lat: ll.lat, flag_long: ll.lng })
                        });
                        if (!res.ok) {
                            const txt = await res.text().catch(() => '');
                            alert('Failed to save flag position: ' + res.status + ' ' + txt);
                        }
                    } catch (err) { console.error('Save position failed', err); alert('Failed to save position'); }
                });

                savedMarkers.set(flag.flag_id, marker);

                // Populate dropdown
                const opt = document.createElement('option');
                opt.value = flag.flag_id;
                opt.textContent = title;
                pinSelector.appendChild(opt);
            });
        } catch (e) { console.error('Error loading pins', e); }
    }

    // --- Add Question Form ---
    let questionCounter = 1;
    function createQuestionForm(marker, markerId, getLatLngCallback) {
        // Container for all questions
        const questionsContainer = document.getElementById('questions-container');
        if (!questionsContainer) return;

        // Create a new question block
        const questionBlock = document.createElement('div');
        questionBlock.classList.add('question-block', 'border', 'p-2', 'mb-2', 'rounded', 'bg-gray-50');
        // Store the temp marker id so Save All can look up the marker
        if (markerId !== undefined && markerId !== null) {
            questionBlock.dataset.tempId = String(markerId);
        }

        // Question title input
        const questionLabel = document.createElement('label');
        questionLabel.textContent = `Question #${questionCounter++}`;
        questionLabel.classList.add('font-semibold', 'block', 'mb-1');

        const questionInput = document.createElement('textarea');
        questionInput.classList.add('border', 'rounded', 'p-1', 'w-full', 'mb-2');
        questionInput.placeholder = 'Enter question text';

        // Answer options container
        const answersContainer = document.createElement('div');
        answersContainer.classList.add('answers-container', 'mb-2');

        // Question type select
        const typeSelect = document.createElement('select');
        typeSelect.classList.add('border', 'rounded', 'p-1', 'mb-2');
        const defaultOpt = document.createElement('option');
        defaultOpt.value = '';
        defaultOpt.textContent = 'Select question type';
        typeSelect.appendChild(defaultOpt);
        for (const t of questionTypes) {
            const o = document.createElement('option');
            o.value = t.question_type_id;
            o.textContent = t.question_type_name || t.name || `Type ${t.question_type_id}`;
            typeSelect.appendChild(o);
        }

        // Function to add a new answer option
        function addAnswerOption(defaultText = '') {
            const optDiv = document.createElement('div');
            optDiv.classList.add('flex', 'items-center', 'mb-1');

            const optInput = document.createElement('input');
            optInput.type = 'text';
            optInput.value = defaultText;
            optInput.placeholder = 'Option text';
            optInput.classList.add('border', 'rounded', 'p-1', 'flex-1');

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.textContent = '×';
            removeBtn.classList.add('ml-2', 'text-red-500', 'font-bold');
            removeBtn.addEventListener('click', () => optDiv.remove());

            optDiv.appendChild(optInput);
            optDiv.appendChild(removeBtn);
            answersContainer.appendChild(optDiv);
        }

    // Add initial answer option
    addAnswerOption();

        // Button to add new answer option
        const addAnswerBtn = document.createElement('button');
        addAnswerBtn.type = 'button';
        addAnswerBtn.textContent = '+ Add Option';
        addAnswerBtn.classList.add('px-2', 'py-1', 'bg-blue-600', 'text-white', 'rounded', 'mb-2');
        addAnswerBtn.addEventListener('click', () => addAnswerOption());

        // Save button for this question: add to local batch (do not send to server immediately)
        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.textContent = 'Add to Batch';
        saveBtn.classList.add('px-3', 'py-1', 'bg-yellow-600', 'text-white', 'rounded');

        saveBtn.addEventListener('click', (ev) => {
            ev.preventDefault();
            // Validate locally
            const questionText = questionInput.value.trim();
            if (!questionText) { alert('Question text is required.'); return; }

            // Mark this block as part of the pending batch
            questionBlock.dataset.pending = 'true';
            // Store serialized options for later
            const options = Array.from(answersContainer.querySelectorAll('input')).map(i => i.value.trim()).filter(t => t);
            questionBlock.dataset.options = JSON.stringify(options);
            // store question text and type
            questionBlock.dataset.questionText = questionText;
            questionBlock.dataset.questionType = typeSelect && typeSelect.value ? typeSelect.value : '';

            // Also add to the pendingFlags collection so questions persist even if the marker disappears
            try {
                const pf = pendingFlags.find(p => String(p.tempId) === String(markerId));
                if (pf) {
                    pf.questions.push({ question_text: questionText, question_type: parseInt(questionBlock.dataset.questionType || 1, 10), options });
                }
            } catch (e) { console.warn('Failed to attach question to pending flag', e); }

            // Visual feedback
            saveBtn.textContent = 'Added';
            saveBtn.disabled = true;
        });

    // Append elements
    questionBlock.appendChild(questionLabel);
    questionBlock.appendChild(questionInput);
    // question type select
    questionBlock.appendChild(typeSelect);
    questionBlock.appendChild(answersContainer);
    questionBlock.appendChild(addAnswerBtn);
    questionBlock.appendChild(saveBtn);

        questionsContainer.appendChild(questionBlock);
    }


    map.on('click', e => {
        // Require run metadata be filled (title + type), but don't require the run to be created yet
        if (!runTitleInput || !runTitleInput.value.trim() || !runTypeSelect || !runTypeSelect.value) {
            alert('Please fill run title and select run type before adding pins. You do not need to create the run yet; Save All will create it.');
            return;
        }

        const lat = e.latlng.lat;
        const lng = e.latlng.lng;
        const marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        const tempId = `t${tempMarkerCounter++}`;
        savedMarkers.set(tempId, marker);

        // add to client-side pending flags collection so data persists if marker is lost
        pendingFlags.push({ tempId, lat, lng, marker, questions: [] });

        // Create popup with delete button for pending marker
        const popupHtml = `<div><strong>New Pin #${tempId}</strong>` +
            `<div class="mt-1 text-sm">Drag to reposition</div>` +
            `<div style="margin-top:.5rem"><button id="del-temp-${tempId}" type="button">Delete</button></div>` +
            `</div>`;
        marker.bindPopup(popupHtml).openPopup();

        marker.on('dragend', () => {
            const latlng = marker.getLatLng();
            marker.getPopup().setContent(`New Pin #${tempId} (dragged to ${latlng.lat.toFixed(5)}, ${latlng.lng.toFixed(5)})`).openPopup();
            const pf = pendingFlags.find(p => p.tempId === tempId);
            if (pf) { pf.lat = latlng.lat; pf.lng = latlng.lng; }
        });

        // Attach delete handler when popup opens
        marker.on('popupopen', () => {
            const btn = document.getElementById(`del-temp-${tempId}`);
            if (btn && !btn.dataset.bound) {
                btn.dataset.bound = '1';
                btn.addEventListener('click', (ev) => {
                    ev.preventDefault();
                    if (!confirm('Delete this pending pin?')) return;
                    // remove from pendingFlags
                    const idx = pendingFlags.findIndex(p => p.tempId === tempId);
                    if (idx !== -1) pendingFlags.splice(idx, 1);
                    // remove any question blocks tied to this tempId
                    try {
                        const blocks = Array.from(document.querySelectorAll('.question-block')).filter(b => String(b.dataset.tempId) === String(tempId));
                        for (const b of blocks) b.remove();
                    } catch (e) { console.warn('Failed to remove question blocks', e); }
                    // remove marker
                    map.removeLayer(marker);
                    savedMarkers.delete(tempId);
                });
            }
        });

        createQuestionForm(marker, tempId, () => marker.getLatLng());
    });


    // --- Initial load ---
    // Load run types and question types first to populate UI, then load pins
    // Save All handler: collects unsaved question forms, creates flags and questions in bulk, then options
    async function saveAll() {
        // Collect pending blocks added to the batch
        const blocks = Array.from(document.querySelectorAll('.question-block')).filter(b => b.dataset.pending === 'true' && b.dataset.saved !== 'true');
        if (!blocks.length) { alert('Nothing to save.'); return; }

        try {
            // 1) Ensure run exists: if not, create it from the run inputs
            if (!currentRunId) {
                const title = runTitleInput.value.trim();
                const desc = runDescInput.value.trim();
                const runTypeId = runTypeSelect.value;
                if (!title || !runTypeId) { alert('Please fill run title and type before saving.'); return; }

                const rRes = await fetch('/api/runs/', {
                    method: 'POST', headers: getAuthHeaders(), body: JSON.stringify({ run_title: title, run_description: desc, run_type: parseInt(runTypeId,10) })
                });
                if (!rRes.ok) throw new Error('Run creation failed');
                const rJson = await rRes.json();
                const run = rJson.run || rJson;
                currentRunId = run.run_id || run.id;
                if (currentRunDisplay) currentRunDisplay.textContent = `Current run: ${run.run_title || run.title || ''}`;
            }

            // Disable Save All button while processing
            const saveAllBtnEl = document.getElementById('save-questions');
            if (saveAllBtnEl) { saveAllBtnEl.disabled = true; saveAllBtnEl.textContent = 'Saving...'; }

            // 2) Iterate blocks sequentially: create flag then create question (with options) referencing that flag
            for (const b of blocks) {
                const tId = b.dataset.tempId;
                // Prefer the pendingFlags entry for coordinates in case marker was removed; fall back to live marker
                const pf = pendingFlags.find(p => String(p.tempId) === String(tId));
                let ll = null;
                let marker = null;
                if (pf) {
                    ll = { lat: pf.lat, lng: pf.lng };
                    marker = pf.marker;
                } else {
                    marker = savedMarkers.get(tId) || savedMarkers.get(parseInt(tId,10));
                    if (marker) ll = marker.getLatLng();
                }

                if (!ll) { console.warn('Skipping block, coordinates not found', b); continue; }

                // Create flag (server assigns flag_number)
                const flagRes = await fetch('/api/flags/', {
                    method: 'POST', headers: getAuthHeaders(), body: JSON.stringify({ run_id: currentRunId, flag_lat: ll.lat, flag_long: ll.lng })
                });
                if (!flagRes.ok) {
                    // Read response body for debugging and attach to block dataset
                    let bodyText = '';
                    try { bodyText = await flagRes.text(); } catch (e) { bodyText = '<unreadable response>'; }
                    console.error('Flag create failed for block', { status: flagRes.status, body: bodyText, block: b });
                    b.dataset.error = `Flag create failed: ${flagRes.status} ${bodyText}`;
                    // continue to next block rather than throwing to allow other items to be saved
                    continue;
                }
                const flagJson = await flagRes.json();
                const flag = flagJson.flag || flagJson;
                const flagId = flag.flag_id || flag.id;

                // Prepare question payload from stored dataset
                const qText = b.dataset.questionText || '';
                const qType = b.dataset.questionType ? parseInt(b.dataset.questionType,10) : 1;
                let opts = [];
                try { opts = b.dataset.options ? JSON.parse(b.dataset.options) : []; } catch(e) { opts = []; }

                const questionPayload = { run_id: currentRunId, flag_id: flagId, question_type: qType, question_text: qText, options: opts.map(t => ({ question_option_text: t })) };

                const qRes = await fetch('/api/questions/', { method: 'POST', headers: getAuthHeaders(), body: JSON.stringify(questionPayload) });
                if (!qRes.ok) {
                    let qBody = '';
                    try { qBody = await qRes.text(); } catch (e) { qBody = '<unreadable response>'; }
                    console.error('Question create failed for flag', { status: qRes.status, body: qBody, flagId, block: b });
                    b.dataset.error = `Question create failed: ${qRes.status} ${qBody}`;
                }
                const qJson = await qRes.json();
                const q = qJson.question || qJson;
                const qId = q.question_id || q.id;

                // Update datasets and marker mapping
                b.dataset.saved = 'true';
                if (flagId) b.dataset.flagId = String(flagId);
                if (qId) b.dataset.questionId = String(qId);
                // remap savedMarkers from temp id to server id and update pendingFlags
                try {
                    savedMarkers.delete(tId);
                    if (flagId) savedMarkers.set(flagId, marker);
                    const idx = pendingFlags.findIndex(p => String(p.tempId) === String(tId));
                    if (idx !== -1) { pendingFlags[idx].saved = true; pendingFlags[idx].flag_id = flagId; }
                } catch (e) {}
            }

            if (saveAllBtnEl) { saveAllBtnEl.disabled = false; saveAllBtnEl.textContent = 'Save All'; }
            alert('Batch saved.');
            loadPins();
        } catch (err) {
            console.error('Save all failed', err);
            alert('Save All failed. Check console for details.');
            const saveAllBtnEl = document.getElementById('save-questions');
            if (saveAllBtnEl) { saveAllBtnEl.disabled = false; saveAllBtnEl.textContent = 'Save All'; }
        }
    }

    const saveAllBtn = document.getElementById('save-questions');
    if (saveAllBtn) saveAllBtn.addEventListener('click', (e) => { e.preventDefault(); saveAll(); });

    // Load helpers
    async function loadRunTypes() {
        try {
            const res = await fetch('/api/run-types/', { headers: getAuthHeaders() });
            if (!res.ok) return;
            const types = await res.json();
            if (runTypeSelect) {
                runTypeSelect.innerHTML = '<option value="">Select run type</option>';
                for (const t of types) {
                    const opt = document.createElement('option');
                    opt.value = t.run_type_id;
                    opt.textContent = t.run_type_name;
                    runTypeSelect.appendChild(opt);
                }
            }
        } catch (e) { console.error('Failed to load run types', e); }
    }

    async function loadQuestionTypes() {
        try {
            const res = await fetch('/api/question-types/', { headers: getAuthHeaders() });
            if (!res.ok) return;
            questionTypes = await res.json();
            const globalSelect = document.getElementById('global-question-type');
            if (globalSelect) {
                globalSelect.innerHTML = '<option value="">Select type</option>';
                for (const t of questionTypes) {
                    const opt = document.createElement('option');
                    opt.value = t.question_type_id;
                    opt.textContent = t.question_type_name || t.name || `Type ${t.question_type_id}`;
                    globalSelect.appendChild(opt);
                }
            }
        } catch (e) { console.error('Failed to load question types', e); }
    }

    Promise.all([loadRunTypes(), loadQuestionTypes()]).then(() => loadPins()).catch(() => loadPins());

    // --- Optional: expose reload function ---
    window.reloadMapPins = loadPins;
});
