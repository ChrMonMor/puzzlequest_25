@extends('layouts.app')

@section('head')
    {{-- Keep page-specific fonts or small styles here if needed --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
@endsection

@section('content')
    <div class="max-w-4xl mx-auto py-16 px-6">
        @if(session('status'))
            <div class="mb-4 px-4 py-2 bg-green-100 text-green-800 rounded">{{ session('status') }}</div>
        @endif

        <h1 class="text-2xl font-semibold mb-2">Welcome to {{ config('app.name', 'PuzzleQuest') }}</h1>
        <p class="text-gray-600 mb-6">This is a lightweight landing page that links to the web auth flows (login, register) and a session-only guest flow.</p>

        <div class="flex flex-wrap gap-3 mb-6">
            @guest
                <a href="{{ route('login') }}" class="px-4 py-2 bg-gray-800 text-white rounded">Login</a>
                <a href="{{ route('register') }}" class="px-4 py-2 border border-gray-300 rounded">Register</a>
                <a href="{{ route('guest') }}" class="px-4 py-2 bg-yellow-100 text-yellow-800 rounded">Continue as guest</a>
            @else
                <span class="px-4 py-2">Hello, {{ optional(auth()->user())->user_name ?? auth()->user()->email ?? auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded">Logout</button>
                </form>
            @endguest

            @if(session('guest'))
                <a href="{{ route('upgrade') }}" class="px-4 py-2 bg-blue-600 text-white rounded">Upgrade guest to user</a>
            @endif
        </div>

        <div class="prose max-w-none">
            <h2>Quick links</h2>
            <ul>
                <li><a href="https://laravel.com/docs" target="_blank">Documentation</a></li>
                <li><a href="https://laracasts.com" target="_blank">Laracasts</a></li>
            </ul>
        </div>
    </div>
@endsection
