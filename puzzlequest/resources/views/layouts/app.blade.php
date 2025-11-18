<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'PuzzleQuest')</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/modern-normalize/modern-normalize.min.css">
    @yield('head')
    <style>
        /* Theme variables (light by default) */
        :root{
            --map-green: #66B26F; /* Map Green */
            --trail-blue: #3C90B8; /* Trail Blue */
            --puzzle-navy: #264C63; /* Puzzle Navy */
            --sun-yellow: #F4C74B; /* Sun Yellow */
            --accent-orange: #F06C34; /* Accent Orange */
            --sky-blue: #6BAFD6; /* Sky Blue */
            --error-red: #E25858; /* Error Red */
            --slate-gray: #5C6A71; /* Slate Gray */
            --canvas-white: #FFFFFF; /* Canvas White */

            --bg: var(--canvas-white);
            --surface: #FAFAFA;
            --text: #111827; /* near black */
            --muted: var(--slate-gray);
            --link: var(--trail-blue);
        }

        /* Dark mode variables (when .dark class is on <html>) */
        .dark {
            --map-green: #4A9E5A;
            --trail-blue: #358AAE;
            --puzzle-navy: #28526a;
            --sun-yellow: #DCA93E;
            --accent-orange: #E05F2D;
            --sky-blue: #588BAF;
            --error-red: #C64C4C;
            --slate-gray: #D3D7DA;
            --canvas-white: #121C22;

            --bg: var(--canvas-white);
            --surface: #0F1720;
            --text: var(--slate-gray);
            --muted: #9AA6AD;
            --link: var(--trail-blue);
        }

        body { font-family: Arial, Helvetica, sans-serif; padding: 1rem; background: var(--bg); color: var(--text); }
        .container { max-width: 960px; margin: 0 auto; }
        .flash { padding: .5rem; margin-bottom: .75rem; border-radius: 4px; }
        .flash-success { background: rgba(102,178,111,0.08); border:1px solid rgba(102,178,111,0.25); }
        .flash-error { background: rgba(226,88,88,0.08); border:1px solid rgba(226,88,88,0.25); }
        
        /* Links */
        a:visited { color: var(--link); }
        a:link { color: var(--link); }

        /* header/footer */
        header.site-header { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding: .5rem 0 }
        header.site-header h1 { margin:0; font-size:1.25rem; color:var(--puzzle-navy) }
        nav.site-nav a { margin-left:.6rem; color: var(--link); text-decoration:none }
        nav.site-nav form { display:inline }
        footer.site-footer { margin-top:2rem; padding-top:1rem; border-top:1px solid rgba(0,0,0,0.06); color:var(--muted); font-size:.9rem }
        footer.site-footer .links a { margin-right: .6rem; color:var(--link); text-decoration:none }

        /* theme toggle button */
        .theme-toggle { background: transparent; border: 1px solid rgba(0,0,0,0.06); padding: .25rem .5rem; border-radius: 6px; cursor: pointer }
        .dark .theme-toggle { border-color: rgba(255,255,255,0.06); }
        /* Buttons */
        .btn { display:inline-block; padding:.45rem .75rem; border-radius:6px; border:1px solid transparent; cursor:pointer; font-weight:600 }
        .btn-primary { background:var(--trail-blue); color:#fff !important; border-color:rgba(0,0,0,0.04) }
        .dark .btn-primary { background:var(--trail-blue); }
        .btn-secondary { background:transparent; color:var(--trail-blue); border:1px solid rgba(0,0,0,0.06) }
        .dark .btn-secondary { border-color: rgba(255,255,255,0.06); }
        .btn-danger { background:var(--error-red); color:#fff }

        /* Cards */
        .card { background:var(--surface); border-radius:8px; padding:1rem; box-shadow: 0 1px 0 rgba(0,0,0,0.03); border:1px solid rgba(0,0,0,0.04); margin-bottom:1rem }
        .dark .card { box-shadow: none; border-color: rgba(255,255,255,0.04); }

        /* Forms */
        input[type="text"], input[type="email"], input[type="password"], textarea, select {
            display:block; width:100%; padding:.5rem .6rem; border-radius:6px; border:1px solid rgba(0,0,0,0.08);
            background: var(--surface); color:var(--text);
        }
        /* For select dropdowns, ensure the popup list uses the same theme colors. */
        select { -webkit-appearance: none; -moz-appearance: none; appearance: none; }
        select option { background: var(--surface) !important; color: var(--text) !important; }
        .dark input[type="text"], .dark textarea, .dark select { border-color: rgba(255,255,255,0.06); }
        .dark select option { background: var(--surface) !important; color: var(--text) !important; }

        label { display:block; margin-bottom:.25rem; color:var(--muted); font-size:.95rem }

        /* Tables */
        table { width:100%; border-collapse:collapse; background:transparent }
        th, td { padding:.5rem .6rem; border-bottom:1px solid rgba(0,0,0,0.04); text-align:left }
        .dark th, .dark td { border-bottom-color: rgba(255,255,255,0.03); }

        /* Headings */
        h1,h2,h3,h4 { color:var(--puzzle-navy); }

        /* Small helpers */
        .muted { color:var(--muted) }
        .badge { display:inline-block; padding:.15rem .4rem; border-radius:999px; background:var(--sun-yellow); color:#0b1220; font-weight:600 }
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
    <script>
        (function(){
            const themeKey = 'pq_theme';
            function applyTheme(t){
                if (t === 'dark') document.documentElement.classList.add('dark'); else document.documentElement.classList.remove('dark');
                // update button icon if present
                const btn = document.getElementById('theme-toggle');
                if (btn) btn.textContent = (t === 'dark') ? '🌙' : '🌞';
            }

            // determine initial theme: localStorage > prefers-color-scheme
            const stored = localStorage.getItem(themeKey);
            if (stored) applyTheme(stored);
            else {
                const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                applyTheme(prefersDark ? 'dark' : 'light');
            }

            // attach handler
            document.addEventListener('DOMContentLoaded', function(){
                const tbtn = document.getElementById('theme-toggle');
                if (!tbtn) return;
                tbtn.addEventListener('click', function(){
                    const isDark = document.documentElement.classList.contains('dark');
                    const next = isDark ? 'light' : 'dark';
                    localStorage.setItem(themeKey, next);
                    applyTheme(next);
                });
            });
        })();
    </script>
    @yield('scripts')
</body>
</html>
