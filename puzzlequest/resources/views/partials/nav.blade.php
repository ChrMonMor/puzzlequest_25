<nav class="site-nav" aria-label="Primary">
    @if(auth()->check())
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
