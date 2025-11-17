@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
    <div style="max-width: 600px; margin: 0 auto;">
        <h2 class="text-xl font-semibold">Change Password</h2>

        @if($errors->any())
            <div class="card" style="margin-top: 1rem; border: 1px solid var(--color-danger, #ef4444); background: rgba(239, 68, 68, 0.1);">
                <ul style="margin: 0; padding-left: 1.5rem;">
                    @foreach($errors->all() as $error)
                        <li style="color: var(--color-danger, #ef4444);">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card" style="margin-top: 1.5rem;">
            <form method="POST" action="{{ route('password.change') }}">
                @csrf
                @method('PUT')

                <div style="margin-bottom: 1.5rem;">
                    <label for="current_password" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        Current Password <span style="color: var(--color-danger, #ef4444);">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="current_password" 
                        name="current_password" 
                        required
                        autocomplete="current-password"
                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-border, #e5e7eb); border-radius: 0.5rem; background: var(--color-surface); color: var(--color-text);">
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
                        minlength="6"
                        autocomplete="new-password"
                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-border, #e5e7eb); border-radius: 0.5rem; background: var(--color-surface); color: var(--color-text);">
                    <p class="muted" style="margin-top: 0.25rem; font-size: 0.875rem;">
                        Must be at least 6 characters long.
                    </p>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label for="password_confirmation" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        Confirm New Password <span style="color: var(--color-danger, #ef4444);">*</span>
                    </label>
                    <input 
                        type="password" 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        required
                        minlength="6"
                        autocomplete="new-password"
                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-border, #e5e7eb); border-radius: 0.5rem; background: var(--color-surface); color: var(--color-text);">
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        Change Password
                    </button>
                    <a href="{{ route('profile') }}" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <div class="card" style="margin-top: 1.5rem; border: 1px solid var(--color-warning, #f59e0b); background: rgba(245, 158, 11, 0.1);">
            <p style="margin: 0; color: var(--color-warning, #f59e0b);">
                <strong>Note:</strong> After changing your password, you will remain logged in on this device. You may need to log in again on other devices.
            </p>
        </div>
    </div>
@endsection
