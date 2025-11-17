@extends('layouts.app')

@section('title', 'Forgot Password')

@section('content')
    <div style="max-width: 500px; margin: 0 auto;">
        <h2 class="text-xl font-semibold">Forgot Your Password?</h2>

        <p class="muted" style="margin-top: 1rem; margin-bottom: 1.5rem;">
            Enter your email address and we'll send you a link to reset your password.
        </p>

        @if(session('success'))
            <div class="card" style="margin-bottom: 1.5rem; border: 1px solid var(--color-success, #10b981); background: rgba(16, 185, 129, 0.1); padding: 1rem;">
                <p style="color: var(--color-success, #10b981); margin: 0;">{{ session('success') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="card" style="margin-bottom: 1.5rem; border: 1px solid var(--color-danger, #ef4444); background: rgba(239, 68, 68, 0.1); padding: 1rem;">
                <ul style="margin: 0; padding-left: 1.5rem;">
                    @foreach($errors->all() as $error)
                        <li style="color: var(--color-danger, #ef4444);">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card">
            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <div style="margin-bottom: 1.5rem;">
                    <label for="email" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        Email Address <span style="color: var(--color-danger, #ef4444);">*</span>
                    </label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="{{ old('email') }}"
                        required
                        autofocus
                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-border, #e5e7eb); border-radius: 0.5rem; background: var(--color-surface); color: var(--color-text);">
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        Send Reset Link
                    </button>
                    <a href="{{ route('login') }}" class="btn btn-secondary">
                        Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
