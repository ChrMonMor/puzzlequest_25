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
            {{-- Create Run button removed; Save All will create the run if needed --}}
            <span id="create-run-status" class="text-sm text-gray-600"></span>
            <div id="current-run" class="ml-auto text-sm text-gray-700"></div>
        </div>
    </div>
    

    <div class="flex gap-4">
        <!-- Map -->
        <div class="flex-1">
            <div id="map"></div>
        </div>

        <!-- Sidebar -->
        <div class="sidebar bg-white p-4 rounded shadow-sm">
            <h3 class="font-semibold mb-2">Pins & Questions</h3>

            <!-- Pin selector -->
            <label class="block text-sm font-medium">Select Pin:</label>
            <select id="pin-selector" class="w-full border-gray-300 rounded-md p-2 mb-3">
                <option value="">-- Select a pin --</option>
            </select>

            <!-- Questions container -->
            <div class="mb-3">
                <label class="block text-sm font-medium">Default question type</label>
                <select id="global-question-type" class="w-full border-gray-300 rounded-md p-2 mb-2">
                    <option value="">Loading...</option>
                </select>
            </div>
            <div id="questions-container" class="w-1/3 ml-4 overflow-y-auto max-h-[70vh]"></div>

            {{-- Individual Add Question button removed — questions are attached to pins when created. --}}
            <button id="save-questions" class="mt-2 ml-2 px-3 py-1 bg-blue-600 text-white rounded">Save All</button>
        </div>
    </div>

    {{-- Leaflet JS (CDN) - ensure L is available before our bundle runs --}}
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        window.__SERVER_JWT = @json(session('jwt_token'));
    </script>
    {{-- Load the bundled map script from public/js — Vite/mix builds are not used for this page. --}}
    <script type="module" src="{{ asset('js/map.js') }}"></script>

@endsection
