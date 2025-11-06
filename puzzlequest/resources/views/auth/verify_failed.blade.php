@extends('layouts.app')

@section('title', 'Verification failed')

@section('content')
    <div class="max-w-2xl mx-auto py-12 px-6">
        <h1 class="text-2xl font-semibold mb-4">Verification error</h1>
        <p class="mb-4">{{ $message ?? 'The verification link is invalid or has expired.' }}</p>
        <p>
            <a href="{{ route('register') }}" class="px-4 py-2 bg-green-600 text-white rounded">Register</a>
            <a href="{{ route('login') }}" class="px-4 py-2 border ml-2">Log in</a>
        </p>
    </div>
@endsection
