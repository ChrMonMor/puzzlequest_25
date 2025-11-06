import 'whatwg-fetch';

// Leaflet loaded via CDN in the Blade view. This script assumes `L` is available.
document.addEventListener('DOMContentLoaded', () => {
    // Helper to pull token from localStorage (project-specific)
    function getAuthHeaders() {
        const headers = { 'Content-Type': 'application/json' };
        // Prefer client-side persisted token, fall back to server-injected token for session-based pages
        const token = localStorage.getItem('jwt') || localStorage.getItem('token') || (window.__SERVER_JWT || null);
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
    }

    // Initialize map
    const mapEl = document.getElementById('map');
    if (!mapEl) return;

    const map = L.map(mapEl).setView([51.505, -0.09], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    // Marker storage
    const savedMarkers = new Map(); // key: flag_id or custom id -> marker

    // Modal elements
    const modal = document.getElementById('marker-modal');
    const modalQuestionInput = document.getElementById('modal-question-text');
    const modalSaveBtn = document.getElementById('modal-save');
    const modalCancelBtn = document.getElementById('modal-cancel');
    const modalStatus = document.getElementById('modal-status');

    // Run creation / selection elements
    const runTitleInput = document.getElementById('run-title');
    const runDescInput = document.getElementById('run-description');
    const runTypeSelect = document.getElementById('run-type');
    const createRunBtn = document.getElementById('create-run');
    const createRunStatus = document.getElementById('create-run-status');
    const currentRunDisplay = document.getElementById('current-run');
    const modalRunDisplay = document.getElementById('modal-run-display');

    let currentRunId = null;

    let activeTempMarker = null;

    function showModal() {
        modal.classList.remove('hidden');
    }
    function hideModal() {
        modal.classList.add('hidden');
        modalStatus.textContent = '';
    }

    modalCancelBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (activeTempMarker) {
            map.removeLayer(activeTempMarker);
            activeTempMarker = null;
        }
        hideModal();
    });

    // Load run types and populate select
    async function loadRunTypes() {
        try {
            const res = await fetch('/api/run-types/', { headers: getAuthHeaders() });
            if (!res.ok) {
                runTypeSelect.innerHTML = '<option value="">(failed to load)</option>';
                return;
            }
            const types = await res.json();
            runTypeSelect.innerHTML = '<option value="">Select run type</option>';
            for (const t of types) {
                const opt = document.createElement('option');
                opt.value = t.run_type_id;
                opt.textContent = t.run_type_name;
                runTypeSelect.appendChild(opt);
            }
        } catch (err) {
            console.error('Failed to load run types', err);
            runTypeSelect.innerHTML = '<option value="">(error)</option>';
        }
    }

    // Create run handler
    createRunBtn.addEventListener('click', async (ev) => {
        ev.preventDefault();
        createRunStatus.textContent = 'Creating...';
        const title = runTitleInput.value.trim();
        const desc = runDescInput.value.trim();
        const runTypeId = runTypeSelect.value;

        if (!title) {
            createRunStatus.textContent = 'Title is required.';
            return;
        }
        if (!runTypeId) {
            createRunStatus.textContent = 'Please select a run type.';
            return;
        }

        try {
            const payload = {
                run_title: title,
                run_description: desc,
                run_type_id: runTypeId,
            };
            const res = await fetch('/api/runs', {
                method: 'POST',
                headers: getAuthHeaders(),
                body: JSON.stringify(payload),
            });
            if (!res.ok) {
                const txt = await res.text();
                createRunStatus.textContent = `Create failed: ${res.status}`;
                console.error('Create run failed', res.status, txt);
                return;
            }
            const json = await res.json();
            // Accept either {run} or run
            const run = json.run || json;
            currentRunId = run.run_id || run.id || null;
            // Do not display UUIDs in the UI; show only the run title (and type if available)
            const title = run.run_title || run.title || 'Run';
            currentRunDisplay.textContent = title ? `Current run: ${title}` : 'Run created';
            modalRunDisplay.textContent = title ? `${title}` : 'Run created';
            createRunStatus.textContent = 'Created.';
            // Optionally clear inputs or leave them
        } catch (err) {
            console.error('Create run error', err);
            createRunStatus.textContent = 'Create failed (see console)';
        }
    });

    // Load existing flags (with questions) and display as pins
    async function loadPins() {
        try {
            const res = await fetch('/api/flags', { headers: getAuthHeaders() });
            if (!res.ok) {
                console.warn('Could not load flags', res.status);
                return;
            }
            const flags = await res.json();
            // Remove old markers
            savedMarkers.forEach((m) => map.removeLayer(m));
            savedMarkers.clear();

            for (const flag of flags) {
                if (!flag.flag_lat || !flag.flag_long) continue;
                const lat = parseFloat(flag.flag_lat);
                const lng = parseFloat(flag.flag_long);
                const m = L.marker([lat, lng]).addTo(map);

                const question = (flag.questions && flag.questions.length) ? flag.questions[0] : null;
                const title = question ? question.question_text : 'Flag';
                m.bindPopup(`<strong>${escapeHtml(title)}</strong>`);

                m.on('click', () => {
                    // Show question details in popup
                    const lines = [];
                    lines.push(`<div class=\"font-semibold mb-1\">${escapeHtml(title)}</div>`);
                    if (question) {
                        lines.push(`<div class=\"text-sm text-gray-600\">Type: ${escapeHtml(String(question.question_type))}</div>`);
                        if (question.options && question.options.length) {
                            lines.push('<div class="mt-2"><em>Options:</em><ul>');
                            for (const opt of question.options) {
                                lines.push(`<li>${escapeHtml(opt.question_option_text)}</li>`);
                            }
                            lines.push('</ul></div>');
                        }
                    }
                    m.getPopup().setContent(lines.join(''));
                    m.openPopup();
                });

                savedMarkers.set(flag.flag_id || `${lat}_${lng}`, m);
            }
        } catch (err) {
            console.error('Error loading pins', err);
        }
    }

    // Click on map to add draggable marker and open modal
    map.on('click', (e) => {
        if (activeTempMarker) map.removeLayer(activeTempMarker);
        activeTempMarker = L.marker(e.latlng, { draggable: true }).addTo(map);
        showModal();
    });

    // Save flow: create a flag then create a question attached to that flag
    modalSaveBtn.addEventListener('click', async (ev) => {
        ev.preventDefault();
        modalStatus.textContent = 'Saving...';
    const runId = currentRunId;
        const questionText = modalQuestionInput.value.trim();

        if (!activeTempMarker) {
            modalStatus.textContent = 'No marker present.';
            return;
        }
        if (!runId) {
            modalStatus.textContent = 'No run selected. Create a run first.';
            return;
        }
        if (!questionText) {
            modalStatus.textContent = 'Question text is required.';
            return;
        }

        const { lat, lng } = activeTempMarker.getLatLng();

        try {
            // 1) Create flag
            const flagPayload = {
                run_id: runId,
                flag_number: 1,
                flag_lat: lat,
                flag_long: lng,
            };

            let headers = getAuthHeaders();
            const flagRes = await fetch('/api/flags', {
                method: 'POST',
                headers,
                body: JSON.stringify(flagPayload),
            });

            if (!flagRes.ok) {
                const txt = await flagRes.text();
                modalStatus.textContent = `Flag create failed: ${flagRes.status}`;
                console.error('Flag create failed', flagRes.status, txt);
                return;
            }
            const flagJson = await flagRes.json();
            const flag = flagJson.flag || flagJson; // bulk endpoints return raw array; store returns {flag}
            const flagId = flag.flag_id;

            // 2) Create question pointing to the created flag
            const questionPayload = {
                run_id: runId,
                flag_id: flagId,
                question_type: 1, // default type id; adjust if needed
                question_text: questionText,
            };

            const qRes = await fetch('/api/questions', {
                method: 'POST',
                headers,
                body: JSON.stringify(questionPayload),
            });

            if (!qRes.ok) {
                const txt = await qRes.text();
                modalStatus.textContent = `Question create failed: ${qRes.status}`;
                console.error('Question create failed', qRes.status, txt);
                return;
            }

            // success: add permanent marker
            const permanentMarker = L.marker([lat, lng]).addTo(map);
            permanentMarker.bindPopup(`<strong>${escapeHtml(questionText)}</strong>`);
            savedMarkers.set(flagId, permanentMarker);

            // cleanup
            map.removeLayer(activeTempMarker);
            activeTempMarker = null;
            modalRunInput.value = '';
            modalQuestionInput.value = '';
            modalStatus.textContent = 'Saved.';
            setTimeout(() => hideModal(), 700);
            // reload pins to reflect latest server state
            loadPins();
        } catch (err) {
            console.error(err);
            modalStatus.textContent = 'Save failed (see console)';
        }
    });

    // Small utility
    function escapeHtml(s) {
        if (!s) return '';
        return s.replace(/[&"'<>]/g, (c) => ({
            '&':'&amp;', '"':'&quot;', "'":'&#39;', '<':'&lt;', '>':'&gt;'
        })[c]);
    }

    // Initial load
    // Load run types first to populate the dropdown, then load pins
    loadRunTypes().finally(() => loadPins());

    // expose a reload helper on window for convenience
    window.reloadMapPins = loadPins;
});
