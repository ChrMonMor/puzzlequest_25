@extends('layouts.app')

@section('title', 'Upgrade Account')

@section('content')
    <h2>Upgrade Guest Account</h2>
    <p>Your current username: {{ $guest['name'] ?? 'Guest' }}</p>
    <form method="POST" action="{{ route('upgrade.post') }}">
        @csrf
        <div>
            <label for="username">Username</label>
            <input id="username" name="username" type="text" maxlength="50" value="{{ old('username', $guest['name'] ?? '') }}" required />
        </div>
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required />
        </div>
        <div>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required />
        </div>
        <div>
            <label for="password_confirmation">Confirm Password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required />
        </div>
        <div>
            <button type="submit">Upgrade Account</button>
        </div>
    </form>

@endsection
