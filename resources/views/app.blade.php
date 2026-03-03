<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $viteManifestExists = file_exists(public_path('build/manifest.json'));
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Vocation Finder') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Literata:ital,opsz,wght@0,7..72,400;0,7..72,500;0,7..72,600;0,7..72,700;1,7..72,400;1,7..72,500&display=swap" rel="stylesheet">
    <link href="https://api.fontshare.com/v2/css?f[]=satoshi@400,500,700&display=swap" rel="stylesheet">
    @if (app()->environment('local'))
        @viteReactRefresh
    @endif
    @if ($viteManifestExists)
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @endif
    @inertiaHead
</head>
<body>
    @if ($viteManifestExists)
        @inertia
    @else
        <main style="max-width: 40rem; margin: 4rem auto; padding: 0 1.25rem; font-family: 'Satoshi', system-ui, sans-serif;">
            <h1 style="margin: 0 0 0.75rem; font-size: 1.5rem;">Frontend assets are not built</h1>
            <p style="margin: 0; color: #4b5563; line-height: 1.6;">
                This deployment is missing <code>public/build/manifest.json</code>. Run
                <code>npm ci && npm run build</code> during the Laravel Cloud build step, then redeploy.
            </p>
        </main>
    @endif
</body>
</html>
