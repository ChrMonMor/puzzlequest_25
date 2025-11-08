@extends('layouts.app')

@section('title', 'History for ' . ($user->user_name ?? $user->name ?? 'User'))

@section('content')
    <h2 class="text-xl font-semibold">History for {{ $user->user_name ?? $user->name ?? 'User' }}</h2>

    <p class="text-sm text-gray-600">All recorded runs and timestamps for this user.</p>

    <div style="margin-top:1rem">
        <a href="{{ route('stats.index') }}">Back to users</a>
    </div>

    {{-- Map removed from this view; maps shown on run-level stats instead. --}}

    <table class="table-auto w-full mt-4">
        <thead>
            <tr>
                <th class="px-2 py-1 text-left">Run</th>
                <th class="px-2 py-1 text-left">Started</th>
                <th class="px-2 py-1 text-left">Ended</th>
                <th class="px-2 py-1 text-left">Position</th>
                <th class="px-2 py-1 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($histories as $h)
                <tr class="border-t">
                    <td class="px-2 py-2">{{ $h->run->run_title ?? '(untitled)' }}</td>
                    <td class="px-2 py-2">{{ $h->history_start }}</td>
                    <td class="px-2 py-2">{{ $h->history_end ?? '-' }}</td>
                    <td class="px-2 py-2">{{ $h->history_run_position ?? '-' }}</td>
                    <td class="px-2 py-2">
                        <a href="{{ route('runs.show', $h->run->run_id) }}">View run</a>
                        <a href="{{ route('history.show', $h->history_id) }}" style="margin-left:0.5rem">View history details</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Map scripts removed from this view; run-level stats contain maps instead. --}}

@endsection
