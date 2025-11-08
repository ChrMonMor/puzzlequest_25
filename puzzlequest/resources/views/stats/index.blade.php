@extends('layouts.app')

@section('title', 'User stats')

@section('content')
    <h2 class="text-xl font-semibold">User statistics</h2>

    <p class="text-sm text-gray-600">List of users and how many completed runs they have.</p>

    <table class="table-auto w-full mt-4">
        <thead>
            <tr>
                <th class="px-2 py-1 text-left">User</th>
                <th class="px-2 py-1 text-left">Completed runs</th>
                <th class="px-2 py-1 text-left">Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
                <tr class="border-t">
                    <td class="px-2 py-2">{{ $user->user_name ?? $user->name ?? 'Unknown' }}</td>
                    <td class="px-2 py-2">{{ $user->completed_runs_count }}</td>
                    <td class="px-2 py-2"><a href="{{ route('stats.show', $user->user_id) }}">View history</a></td>
                </tr>
            @endforeach
        </tbody>
    </table>

@endsection
