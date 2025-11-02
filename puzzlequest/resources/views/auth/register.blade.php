@extends('layouts.app')

@section('title', 'Register')

@section('content')
    <h2>Register</h2>
    <form method="POST" action="{{ route('register.post') }}">
        @csrf
        <div>
            <label for="username">Username</label>
            <input id="username" name="username" type="text" value="{{ old('username') }}" required />
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
            <button type="submit">Create account</button>
        </div>
    </form>

@endsection
