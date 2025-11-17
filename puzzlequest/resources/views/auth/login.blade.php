@extends('layouts.app')

@section('title', 'Login')

@section('content')
    <h2>Login</h2>
    <form method="POST" action="{{ route('login.post') }}">
        @csrf
        <div>
            <label for="email">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required />
        </div>
        <div>
            <label for="password">Password</label>
            <input id="password" name="password" type="password" required />
        </div>
        <div>
            <button type="submit">Login</button>
        </div>
    </form>

    <p style="margin-top: 1rem;">
        <a href="{{ route('password.request') }}" class="muted" style="text-decoration: underline;">Forgot your password?</a>
    </p>

    <p>If you don't want to create an account, you can <a href="{{ route('guest') }}">continue as a guest</a>.</p>

@endsection
