<nav class="site-nav" aria-label="Primary">
    @if(auth()->check())
    <a href="{{ route('map') }}">Map</a>
    {{-- Public runs listing --}}
    <a href="{{ route('runs.index') }}">Runs</a>
    {{-- Authenticated user's runs --}}
    <a href="{{ route('runs.mine') }}">My Runs</a>
    {{-- Link to the current user's histories --}}
    <a href="{{ route('stats.show', auth()->user()->user_id) }}">My History</a>
        <span>Welcome, {{ auth()->user()->user_name ?? auth()->user()->name ?? 'User' }}</span>
        <form method="POST" action="{{ route('logout') }}" style="display:inline">
            @csrf
            <button type="submit">Logout</button>
        </form>
    @elseif(session()->has('guest'))
        <span>{{ session('guest.name') }}</span>
        <a href="{{ route('upgrade') }}">Upgrade</a>
        <form method="POST" action="{{ route('guest.end') }}" style="display:inline">
            @csrf
            <button type="submit">End Guest</button>
        </form>
    @else
        <a href="{{ route('login') }}">Login</a>
        <a href="{{ route('register') }}">Register</a>
        <a href="{{ route('guest') }}">Guest</a>
    @endif
</nav>
