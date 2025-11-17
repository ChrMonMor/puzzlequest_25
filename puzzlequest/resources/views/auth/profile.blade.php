@extends('layouts.app')

@section('title', 'My Profile')

@section('content')
    <div style="max-width: 800px; margin: 0 auto;">
        <h2 class="text-xl font-semibold">My Profile</h2>

        @if(session('success'))
            <div class="card" style="margin-top: 1rem; border: 1px solid var(--color-success, #10b981); background: rgba(16, 185, 129, 0.1);">
                <p style="margin: 0; color: var(--color-success, #10b981);">{{ session('success') }}</p>
            </div>
        @endif

        <div class="card" style="margin-top: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem;">
                @if(auth()->user()->user_img)
                                <img src="{{ asset('images/profiles/' . auth()->user()->user_img) }}" alt="Profile picture" 
                         style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover;">
                @else
                    <div style="width: 120px; height: 120px; border-radius: 50%; background: var(--color-surface); display: flex; align-items: center; justify-content: center; font-size: 3rem; color: var(--color-muted);">
                        {{ strtoupper(substr(auth()->user()->user_name ?? 'U', 0, 1)) }}
                    </div>
                @endif

                <div style="flex: 1;">
                    <h3 style="font-size: 1.5rem; font-weight: 600; margin-bottom: 0.5rem;">
                        {{ auth()->user()->user_name ?? 'User' }}
                    </h3>
                    <p class="muted">{{ auth()->user()->user_email }}</p>
                    @if(auth()->user()->user_verified)
                        <span style="display: inline-block; margin-top: 0.5rem; padding: 0.25rem 0.75rem; background: var(--color-success, #10b981); color: white; border-radius: 9999px; font-size: 0.875rem;">
                            ✓ Verified
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="{{ route('runs.mine') }}" class="btn btn-primary">My Runs</a>
            <a href="{{ route('stats.show', auth()->user()->user_id) }}" class="btn btn-primary">My History</a>
            <a href="{{ route('map') }}" class="btn btn-secondary">Create New Run</a>
        </div>

        <div class="card" style="margin-top: 2rem;">
            <h3 style="font-weight: 600; margin-bottom: 1rem;">Account Actions</h3>
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                     <a href="{{ route('profile.edit') }}" class="btn btn-secondary">
                    Edit Profile
                     </a>
                     <a href="{{ route('password.change.form') }}" class="btn btn-secondary">
                    Change Password
                     </a>
            </div>
        </div>

        <div class="card" style="margin-top: 2rem; border: 1px solid var(--color-danger, #ef4444);">
            <h3 style="font-weight: 600; margin-bottom: 0.5rem; color: var(--color-danger, #ef4444);">Danger Zone</h3>
            <p class="muted" style="margin-bottom: 1rem;">Permanently delete your account and all associated data.</p>
            <button class="btn" style="background: var(--color-danger, #ef4444); color: white;" 
                    onclick="if(confirm('Are you sure you want to delete your account? This action cannot be undone.')) { document.getElementById('delete-form').submit(); }">
                Delete Account
            </button>
            <form id="delete-form" method="POST" action="{{ route('delete.account') }}" style="display: none;">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>
@endsection
