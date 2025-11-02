<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'PuzzleQuest')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/modern-normalize/modern-normalize.min.css">
    @yield('head')
    <style>
        body { font-family: Arial, Helvetica, sans-serif; padding: 1rem; background: #fff; color:#111 }
        .container { max-width: 960px; margin: 0 auto; }
        .flash { padding: .5rem; margin-bottom: .75rem; border-radius: 4px; }
        .flash-success { background:#e6ffed; border:1px solid #b7f0c9 }
        .flash-error { background:#ffecec; border:1px solid #f5c2c2 }

        /* header/footer */
        header.site-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding: .5rem 0 }
        header.site-header h1 { margin:0; font-size:1.25rem }
        nav.site-nav a { margin-left:.6rem; color: #0366d6; text-decoration:none }
        nav.site-nav form { display:inline }
        footer.site-footer { margin-top:2rem; padding-top:1rem; border-top:1px solid #eee; color:#666; font-size:.9rem }
        footer.site-footer .links a { margin-right: .6rem; color:#0366d6; text-decoration:none }
    </style>
</head>
<body>
    <div class="container">
        @include('partials.header')

        @if(session('success'))
            <div class="flash flash-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="flash flash-error">
                <ul>
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')

    </div>

    @include('partials.footer')
</body>
</html>
