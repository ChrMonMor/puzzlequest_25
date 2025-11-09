@extends('layouts.app')

@section('head')
    {{-- Keep page-specific fonts or small styles here if needed --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
@endsection

@section('content')
    <div class="max-w-4xl mx-auto py-12 px-6">
        @if(session('status'))
            <div class="mb-4 px-4 py-2 flash flash-success">{{ session('status') }}</div>
        @endif

        <div class="card" style="margin-bottom:1rem">
            <h1 class="text-2xl font-semibold mb-2">{{ config('app.name', 'PuzzleQuest') }}</h1>
            <p class="muted">Create and play geolocated puzzle runs. Drop pins on the interactive map to create flags, add questions and options, then use the "Save All" flow to publish a run. Players (or guests) can join runs, enter the pin to play, and you can view aggregated stats with charts and maps.</p>

            <div style="margin-top:1rem; display:flex; gap: .5rem; flex-wrap:wrap; align-items:center">
                @auth
                    <a href="{{ route('map') }}" class="btn btn-primary">Create New Run</a>
                    <a href="{{ route('runs.mine') }}" class="btn btn-secondary">My runs</a>
                    <a href="{{ route('stats.show', auth()->user()->user_id) }}" class="btn btn-secondary">My stats</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary">Log in</a>
                    <a href="{{ route('register') }}" class="btn btn-secondary">Register</a>
                    <form method="POST" action="{{ route('guest.create') }}" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-secondary">Start guest session</button>
                    </form>
                @endauth
            </div>
        </div>

        @php
            $runs = \App\Models\Run::with('runType','user')->orderBy('run_added', 'desc')->take(50)->get();
        @endphp

        <div>
            <h2 class="text-lg font-semibold">Recent runs</h2>
            @include('runs._list')
        </div>
    </div>
@endsection
