@extends('layouts.app')

@section('title', 'Map — PuzzleQuest')

@section('head')
    {{-- Leaflet CSS (CDN) -- used for map rendering --}}
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
    <style>
        #map { height: 70vh; border-radius: .5rem; }
    </style>
@endsection

@section('content')
    <div class="mb-4">
        <h2 class="text-xl font-semibold">Interactive Map</h2>
        <p class="text-sm text-gray-600">Click anywhere on the map to drop a marker. Drag to reposition. Create a run first using the form below, then drop markers and add questions to that run.</p>
    </div>

    <div class="mb-4 p-4 bg-white rounded shadow-sm">
        <h3 class="font-semibold">Create a Run</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-2">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Run Title</label>
                <input id="run-title" type="text" class="mt-1 block w-full border-gray-300 rounded-md p-2" placeholder="Enter run title" />
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700">Run Type</label>
                <select id="run-type" class="mt-1 block w-full border-gray-300 rounded-md p-2">
                    <option value="">Loading...</option>
                </select>
            </div>
        </div>

        <div class="mt-3">
            <label class="block text-sm font-medium text-gray-700">Run Description</label>
            <textarea id="run-description" rows="2" class="mt-1 block w-full border-gray-300 rounded-md p-2" placeholder="Optional description"></textarea>
        </div>

        <div class="mt-3 flex items-center gap-3">
            <button id="create-run" class="px-4 py-2 bg-green-600 text-white rounded">Create Run</button>
            <span id="create-run-status" class="text-sm text-gray-600"></span>
            <div id="current-run" class="ml-auto text-sm text-gray-700"></div>
        </div>
    </div>

    <div id="map" class="mb-4"></div>

    {{-- Modal (hidden by default) --}}
    <div id="marker-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black opacity-50"></div>
        <div class="bg-white rounded-lg shadow-lg z-10 w-full max-w-lg mx-4 p-6">
            <h3 class="text-lg font-semibold mb-3">Add question for marker</h3>

            <div class="space-y-3">
                <div id="modal-current-run-info" class="mb-2">
                    <label class="block text-sm font-medium text-gray-700">Run</label>
                    <div id="modal-run-display" class="mt-1 text-sm text-gray-700">No run selected. Create a run above and it will be used automatically.</div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Question text</label>
                    <textarea id="modal-question-text" rows="3" class="mt-1 block w-full border-gray-300 rounded-md p-2" placeholder="Enter the question"></textarea>
                </div>

                <div class="flex items-center gap-3">
                    <button id="modal-save" class="px-4 py-2 bg-blue-600 text-white rounded">Save</button>
                    <button id="modal-cancel" class="px-4 py-2 border rounded">Cancel</button>
                    <span id="modal-status" class="text-sm text-gray-600"></span>
                </div>
            </div>
        </div>
    </div>

    {{-- include the map script via Vite when available, otherwise fall back to a public/js bundle so the page works without running the Vite dev/build step --}}
    {{-- Leaflet JS (CDN) - ensure L is available before our bundle runs --}}
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    {{-- Inject server-side JWT (if present in session) so client JS can call authenticated APIs without requiring a manual localStorage copy. This uses a single in-memory global and does not persist to localStorage. --}}
    <script>
        window.__SERVER_JWT = @json(session('jwt_token'));
    </script>
    @php $viteManifest = public_path('build/manifest.json'); @endphp
    @if(file_exists($viteManifest))
        @vite(['resources/js/map.js'])
    @else
        {{-- Fallback: use a plain JS build shipped to public/js/map.js (module) --}}
        <script type="module" src="{{ asset('js/map.js') }}"></script>
    @endif

@endsection
