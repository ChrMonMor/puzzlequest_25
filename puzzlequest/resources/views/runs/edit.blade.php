@extends('layouts.app')

@section('title', 'Edit Run')

@section('content')
    <h2 class="text-xl font-semibold">Edit Run</h2>

    <p>This is a simple edit placeholder. Integrate with API or form handling as needed.</p>

    <div style="margin-top:1rem">
        <a href="{{ route('runs.show', $run->run_id) }}">Back</a>
    </div>

@endsection
