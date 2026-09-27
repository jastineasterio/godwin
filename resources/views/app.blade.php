<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="God-Win Daycare & Nursery School — Guiding Every Child in Goodness and Righteousness. Kisasani Medeli, Dodoma.">

    <title>{{ $title ?? 'God-Win Daycare & Nursery School' }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800|baloo-2:600,700,800" rel="stylesheet">

    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/js/app.jsx'])
    @else
        {{-- Production assets have not been compiled yet (npm run build).
             Inertia still server-renders the page, so the site stays usable
             and clearly tells the operator what to run. --}}
        <style>
            :root { --primary:#D81B60; --secondary:#0288D1; --accent:#FBC02D; --canvas:#F8F9FA; --ink:#1E293B; }
            * { box-sizing: border-box; }
            body { margin:0; font-family:'Plus Jakarta Sans',system-ui,sans-serif; color:var(--ink); background:var(--canvas); }
            a { color: var(--primary); }
            header, footer, section, main, div { max-width: 72rem; margin-inline: auto; padding-inline: 1rem; }
            .build-banner { background: var(--accent); color: var(--ink); font-weight: 700; font-size: .875rem; padding: .75rem 1rem; text-align: center; }
            h1 { font-size: 1.5rem; }
        </style>
    @endif

    @inertiaHead
</head>
<body class="font-sans antialiased">
    @unless (file_exists(public_path('build/manifest.json')))
        <div class="build-banner">
            Frontend assets are not compiled yet — run <code>npm install &amp;&amp; npm run build</code> (or <code>npm run dev</code>).
        </div>
    @endunless

    @inertia
</body>
</html>
