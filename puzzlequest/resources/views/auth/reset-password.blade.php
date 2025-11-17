@extends('layouts.app')

@section('title', 'Reset Password')

@section('content')
    <div style="max-width: 500px; margin: 0 auto;">
        <h2 class="text-xl font-semibold">Reset Your Password</h2>

        <p class="muted" style="margin-top: 1rem; margin-bottom: 1.5rem;">
            Enter your new password below.
        </p>

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
            <form method="POST" action="{{ route('password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div style="margin-bottom: 1.5rem;">
                    <label for="email-display" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        Email Address
                    </label>
                    <input 
                        type="email" 
                        id="email-display" 
                        value="{{ $email }}"
                        disabled
                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-border, #e5e7eb); border-radius: 0.5rem; background: var(--color-muted-bg, #f3f4f6); color: var(--color-text); opacity: 0.7;">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="password" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        New Password <span style="color: var(--color-danger, #ef4444);">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        autofocus
                        minlength="6"
                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-border, #e5e7eb); border-radius: 0.5rem; background: var(--color-surface); color: var(--color-text);">
                    <small class="muted" style="display: block; margin-top: 0.25rem;">Minimum 6 characters</small>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="password_confirmation" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        Confirm Password <span style="color: var(--color-danger, #ef4444);">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        required
                        minlength="6"
                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-border, #e5e7eb); border-radius: 0.5rem; background: var(--color-surface); color: var(--color-text);">
                </div>

                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary">
                        Reset Password
                    </button>
                    <a href="{{ route('login') }}" class="btn btn-secondary">
                        Back to Login
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
