@php
    // Three themes (design-system/README.md): paper (default), lamplight (dark), print.
    // The print route always uses the print theme; otherwise the browser's saved choice.
    $saved = request()->cookie('theme');
    $theme = request()->routeIs('print.sheet') ? 'print' : (in_array($saved, ['paper', 'lamplight'], true) ? $saved : 'paper');
@endphp
<!DOCTYPE html>
<html lang="en" data-theme="{{ $theme }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>Clan Family Tree</title>
    {{-- Everything is bundled: no CDN, no remote fonts (the app must work offline). --}}
    @viteReactRefresh
    @vite('resources/js/app.jsx')
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
