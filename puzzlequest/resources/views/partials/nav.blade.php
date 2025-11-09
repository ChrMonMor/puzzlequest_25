<nav class="site-nav" aria-label="Primary" style="display:flex;align-items:center;gap:.75rem">
    <div style="flex:1; display:flex; align-items:center; gap:.6rem">
        <a href="{{ route('map') }}">Create New Run</a>
        <a href="{{ route('runs.index') }}">Runs</a>
        @if(auth()->check())
            <a href="{{ route('runs.mine') }}">My Runs</a>
            <a href="{{ route('stats.show', auth()->user()->user_id) }}">My History</a>
        @endif
    </div>

    <div style="display:flex;align-items:center;gap:.6rem">
        @if(auth()->check())
            <span class="muted">Welcome, {{ auth()->user()->user_name ?? auth()->user()->name ?? 'User' }}</span>
            <form method="POST" action="{{ route('logout') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-secondary">Logout</button>
            </form>
        @elseif(session()->has('guest'))
            <span class="muted">{{ session('guest.name') }}</span>
            <a class="btn btn-secondary" href="{{ route('upgrade') }}">Upgrade</a>
            <form method="POST" action="{{ route('guest.end') }}" style="display:inline">
                @csrf
                <button type="submit" class="btn btn-secondary">End Guest</button>
            </form>
        @else
            <a class="btn btn-secondary" href="{{ route('login') }}">Login</a>
            <a class="btn btn-secondary" href="{{ route('register') }}">Register</a>
            <a class="btn btn-secondary" href="{{ route('guest') }}">Guest</a>
        @endif

        <button id="theme-toggle" class="theme-toggle" title="Toggle theme">🌞</button>
    </div>
</nav>
