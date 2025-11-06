@extends('layouts.app')

@section('title', $title ?? 'Runs')

@section('content')
    <h2 class="text-xl font-semibold mb-3">{{ $title ?? 'Runs' }}</h2>
    @include('runs._list')

@endsection
