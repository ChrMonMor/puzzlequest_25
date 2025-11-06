@extends('layouts.app')

@section('title', $run->run_title ?? 'Run')

@section('content')
    <h2 class="text-xl font-semibold">{{ $run->run_title ?? '(untitled)' }}</h2>
    <p class="text-sm text-gray-600">Created: {{ $run->run_added }} | By: {{ $run->user->user_name ?? $run->user->name ?? 'Unknown' }}</p>

    <div style="margin-top:1rem">
        <p>{{ $run->run_description }}</p>
    </div>

    <div style="margin-top:1rem">
        @if(auth()->check() && auth()->user()->user_id === $run->user_id)
            <a href="{{ route('runs.edit', $run->run_id) }}">Edit this run</a>
        @endif
        <a href="{{ route('runs.index') }}" style="margin-left:1rem">Back to runs</a>
    </div>

    {{-- show flags and questions counts --}}
    <div style="margin-top:1rem">
        <strong>Flags:</strong> {{ $run->flags ? $run->flags->count() : 0 }}
        <br>
        <strong>Questions:</strong> {{ $run->questions ? $run->questions->count() : 0 }}
    </div>

@endsection
