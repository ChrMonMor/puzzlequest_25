@extends('layouts.app')

@section('title', 'Continue as Guest')

@section('content')
    <h2>Continue as Guest</h2>
    <p>As a guest you can view the site and try features, but you won't be able to perform actions that modify data. You can upgrade anytime to save progress.</p>

    <form method="POST" action="{{ route('guest.create') }}">
        @csrf
        <div>
            <label for="guest_name">Display name (optional)</label>
            <input id="guest_name" name="guest_name" type="text" maxlength="50" value="{{ session('guest.name') ?? old('guest_name') }}" />
        </div>
        <div>
            <button type="submit">Continue as Guest</button>
        </div>
    </form>

    <p>Already decided? <a href="{{ route('register') }}">Create an account</a> or <a href="{{ route('login') }}">log in</a>.</p>

@endsection
