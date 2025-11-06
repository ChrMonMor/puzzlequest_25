document.addEventListener('DOMContentLoaded', () => {
    // --- Helper to get JWT auth headers ---
    function getAuthHeaders() {
        const headers = { 'Content-Type': 'application/json' };
        const token = localStorage.getItem('jwt') || localStorage.getItem('token') || (window.__SERVER_JWT || null);
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return headers;
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

    const questionsContainer = document.getElementById('questions-container'); // you need to add this div in Blade
    const pinSelector = document.getElementById('pin-selector'); // you need to add this select in Blade

    let currentRunId = null;
    let activeTempMarker = null;
    let tempMarkerCounter = 1; // starts counting from 1 for human readability
    const savedMarkers = new Map(); // flag_id -> Leaflet marker

    // --- Utility ---
    function escapeHtml(s) {
        if (!s) return '';
        return s.replace(/[&"'<>]/g, c => ({'&':'&amp;','"':'&quot;',"'":'&#39;','<':'&lt;','>':'&gt;'})[c]);
    }

    // --- Load Run Types ---
    async function loadRunTypes() {
        try {
            const res = await fetch('/api/run-types/', { headers: getAuthHeaders() });
            if (!res.ok) return runTypeSelect.innerHTML = '<option value="">(failed to load)</option>';
            const types = await res.json();
            runTypeSelect.innerHTML = '<option value="">Select run type</option>';
            types.forEach(t => {
                const opt = document.createElement('option');
                opt.value = t.run_type_id;
                opt.textContent = t.run_type_name;
                runTypeSelect.appendChild(opt);
            });
        } catch(e) { console.error(e); runTypeSelect.innerHTML = '<option value="">(error)</option>'; }
    }

    // --- Create Run ---
    createRunBtn.addEventListener('click', async (ev) => {
        ev.preventDefault();
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
                body: JSON.stringify({ run_title: title, run_description: desc, run_type: runTypeId })
            });

            if (!res.ok) throw new Error(`Status ${res.status}`);
            const run = (await res.json()).run;
            currentRunId = run?.run_id || null;
            currentRunDisplay.textContent = `Current run: ${run?.run_title || 'Run'}`;
            createRunStatus.textContent = 'Created.';
        } catch(e) {
            console.error(e);
            createRunStatus.textContent = 'Failed to create run.';
        }
    });

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
            const res = await fetch('/api/flags/', { 
                headers: getAuthHeaders(),
                method: 'GET',
                body: JSON.stringify({ run_id: currentRunId })
            });
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
                const marker = L.marker([lat, lng]).addTo(map);

                const question = flag.questions?.[0];
                const title = question ? question.question_text : 'Flag';
                marker.bindPopup(`<strong>${escapeHtml(title)}</strong>`);

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

        // Save button for this question
        const saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.textContent = 'Save Pin & Question';
        saveBtn.classList.add('px-3', 'py-1', 'bg-green-600', 'text-white', 'rounded');

        saveBtn.addEventListener('click', async () => {
            if (!currentRunId) { alert('Please create/select a run first.'); return; }

            const questionText = questionInput.value.trim();
            if (!questionText) { alert('Question text is required.'); return; }

            const options = Array.from(answersContainer.querySelectorAll('input')).map(i => i.value.trim()).filter(t => t);

            const { lat, lng } = getLatLngCallback();

            try {
                // 1. Create the flag
                const flagPayload = { run_id: currentRunId, flag_lat: lat, flag_long: lng, flag_number: 1 };
                const flagRes = await fetch('/api/flags/', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify(flagPayload)
                });

                if (!flagRes.ok) throw new Error('Flag creation failed');
                const flagJson = await flagRes.json();
                const flagId = flagJson.flag_id || flagJson.id;

                // 1.5. Save options if present to array
                const optionTexts = [];
                for (const optText of options) {
                    if (!optText) continue;
                    optionTexts.push(optText);
                }

                // 2. Create the question
                const questionPayload = {
                    run_id: currentRunId,
                    flag_id: flagId,
                    question_type: 1, // default type id
                    question_text: questionText,
                    options: optionTexts.map(text => ({ question_option_text: text }))
                };

                // Send the request
                const qRes = await fetch('/api/questions/', {
                    method: 'POST',
                    headers: getAuthHeaders(),
                    body: JSON.stringify(questionPayload)
                });

                if (!qRes.ok) {
                    const txt = await qRes.text();
                    console.error('Question create failed', qRes.status, txt);
                }
                const qJson = await qRes.json();
                const questionId = qJson.question_id || qJson.id;


                alert('Pin & Question saved successfully!');
                savedMarkers.set(flagId, marker); // mark this marker as saved

            } catch (err) {
                console.error(err);
                alert('Error saving pin/question. Check console.');
            }
            finally {
                loadPins(); // remove the form after saving
            }
        });

        // Append elements
        questionBlock.appendChild(questionLabel);
        questionBlock.appendChild(questionInput);
        questionBlock.appendChild(answersContainer);
        questionBlock.appendChild(addAnswerBtn);
        questionBlock.appendChild(saveBtn);

        questionsContainer.appendChild(questionBlock);
    }


    map.on('click', e => {
        if (!currentRunId) { alert('Please create/select a run first.'); return; }

        const marker = L.marker(e.latlng, { draggable: true }).addTo(map);
        const tempId = tempMarkerCounter++;
        savedMarkers.set(tempId, marker);

        marker.bindPopup(`New Pin #${tempId} (drag to reposition)`).openPopup();

        marker.on('dragend', () => {
            const latlng = marker.getLatLng();
            marker.getPopup().setContent(`New Pin #${tempId} (dragged to ${latlng.lat.toFixed(5)}, ${latlng.lng.toFixed(5)})`).openPopup();
        });

        createQuestionForm(marker, tempId, () => marker.getLatLng());
    });


    // --- Initial load ---
    loadRunTypes().finally(() => loadPins());

    // --- Optional: expose reload function ---
    window.reloadMapPins = loadPins;
});
