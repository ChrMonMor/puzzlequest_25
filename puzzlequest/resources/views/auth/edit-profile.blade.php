@extends('layouts.app')

@section('title', 'Edit Profile')

@section('content')
    <div style="max-width: 600px; margin: 0 auto;">
        <h2 class="text-xl font-semibold">Edit Profile</h2>

        <style>
            .profile-image-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(90px, 1fr)); gap: 0.75rem; }
            .profile-image-grid input[type="radio"] { display: none; }
            .profile-image-grid .img-card { border: 2px solid var(--color-border, #e5e7eb); border-radius: 0.5rem; padding: 4px; transition: border-color .15s ease, box-shadow .15s ease; }
            .profile-image-grid label:hover .img-card, .profile-image-grid label:focus-within .img-card { border-color: var(--color-muted, #9ca3af); }
            .profile-image-grid input[type="radio"]:checked + .img-card { border-color: var(--color-primary, #3b82f6); box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2) inset; }
        </style>

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
            <form method="POST" action="{{ route('profile.update', [], true) }}">
                @csrf
                @method('PUT')

                <div style="margin-bottom: 1.5rem;">
                    <label for="username" style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        Username <span style="color: var(--color-danger, #ef4444);">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="{{ old('username', auth()->user()->user_name) }}"
                        required
                        style="width: 100%; padding: 0.75rem; border: 1px solid var(--color-border, #e5e7eb); border-radius: 0.5rem; background: var(--color-surface); color: var(--color-text);">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-weight: 600; margin-bottom: 0.5rem;">
                        Choose Profile Image
                    </label>
                    @if(!empty($profileImages))
                        <div class="profile-image-grid">
                            @foreach($profileImages as $img)
                                @php
                                    $isSelected = old('user_img', auth()->user()->user_img) === $img;
                                @endphp
                                <label style="cursor: pointer; display:block;">
                                    <input type="radio" name="user_img" value="{{ $img }}" {{ $isSelected ? 'checked' : '' }}>
                                    <div class="img-card">
                                        <img src="{{ asset('images/profiles/' . $img) }}" alt="{{ $img }}" style="width: 100%; height: 80px; object-fit: cover; border-radius: 0.25rem;">
                                    </div>
                                    <div class="muted" style="font-size: 0.75rem; margin-top: 0.25rem; text-align: center;">
                                        {{ $img }}
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="muted">No images found in images/profiles.</p>
                    @endif
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        Save Changes
                    </button>
                    <a href="{{ route('profile', [], false) }}" class="btn btn-secondary">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
@endsection
