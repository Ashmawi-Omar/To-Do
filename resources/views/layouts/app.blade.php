<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <script>
        // Tell the server the browser's timezone so "today" is the user's today, not the server's.
        (function () {
            var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
            var saved = document.cookie.match(/(?:^|; )tz=([^;]*)/);
            if (tz && (!saved || decodeURIComponent(saved[1]) !== tz)) {
                document.cookie = 'tz=' + encodeURIComponent(tz) + '; path=/; max-age=31536000; samesite=lax';
                if (document.cookie.indexOf('tz=') !== -1) location.reload();
            }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script defer src="{{ asset('js/alpine.min.js') }}?v={{ filemtime(public_path('js/alpine.min.js')) }}"></script>
</head>
<body>
    <main class="app">
        <h1>{{ config('app.name') }}</h1>
        @yield('content')
    </main>
</body>
</html>
