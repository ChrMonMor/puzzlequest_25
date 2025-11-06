@extends('layouts.app')

@section('title', 'Email verified')

@section('content')
    <div class="max-w-2xl mx-auto py-12 px-6">
        <h1 class="text-2xl font-semibold mb-4">Email verified</h1>
        <p class="mb-4">{{ $message ?? 'Your email has been verified and your account is ready.' }}</p>
        <p>
            <a href="{{ route('login') }}" class="px-4 py-2 bg-blue-600 text-white rounded">Log in</a>
        </p>
    </div>
@endsection
