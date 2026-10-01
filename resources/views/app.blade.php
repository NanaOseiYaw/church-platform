<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Dynamic title & SEO --}}
    <title inertia>{{ config('church.name') }}</title>

    {{-- Favicon — uses church logo if set, falls back to public/favicon.ico --}}
    @php $faviconUrl = app('church')?->logo ?? null; @endphp
    @if($faviconUrl)
        <link rel="icon" type="image/png" href="{{ $faviconUrl }}">
        <link rel="apple-touch-icon" href="{{ $faviconUrl }}">
    @else
        <link rel="icon" href="/favicon.ico">
    @endif

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,300..800&display=swap" rel="stylesheet">

    {{-- Ziggy — injects window.Ziggy so route() works in Vue --}}
    @routes

    {{-- Vite assets --}}
    @vite('resources/js/app.ts')

    {{-- Inertia head --}}
    @inertiaHead

    {{-- Google Analytics (GA4) — only injected when church admin has configured a Measurement ID --}}
    @php $gaId = app('church')?->settings['seo']['google_analytics_id'] ?? null; @endphp
    @if($gaId)
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '{{ $gaId }}');
        </script>
    @endif

    {{-- Microsoft Clarity — only injected when church admin has configured a Project ID --}}
    @php $clarityId = app('church')?->settings['seo']['clarity_id'] ?? null; @endphp
    @if($clarityId)
        <script>
            (function(c,l,a,r,i,t,y){
                c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y)
            })(window,document,"clarity","script","{{ $clarityId }}");
        </script>
    @endif

    {{-- JSON-LD — Church / Organization schema for Google Knowledge Panel and local search --}}
    @php
        $ldChurch = app('church');
        $ldPrivate = $ldChurch?->settings['website']['privacy_mode'] ?? false;
    @endphp
    @if($ldChurch && !$ldPrivate)
    @php
        $ldSocials  = $ldChurch->settings['social']  ?? [];
        $ldBaseUrl  = rtrim(config('app.url'), '/');
        $ldSameAs   = collect([
            $ldSocials['facebook']  ?? null,
            $ldSocials['instagram'] ?? null,
            $ldSocials['twitter']   ?? null,
            $ldSocials['youtube']   ?? null,
        ])->filter()->values()->all();

        $ldSchema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Church',
            'name'     => $ldChurch->name,
            'url'      => $ldBaseUrl,
        ];

        if ($ldChurch->settings['seo']['meta_description'] ?? null)
            $ldSchema['description'] = $ldChurch->settings['seo']['meta_description'];
        if ($ldChurch->logo)
            $ldSchema['logo'] = $ldChurch->logo;
        if ($ldChurch->phone)
            $ldSchema['telephone'] = $ldChurch->phone;
        if ($ldChurch->email)
            $ldSchema['email'] = $ldChurch->email;
        if ($ldChurch->address)
            $ldSchema['address'] = ['@type' => 'PostalAddress', 'streetAddress' => $ldChurch->address];
        if (!empty($ldSameAs))
            $ldSchema['sameAs'] = $ldSameAs;
    @endphp
    <script type="application/ld+json">{!! json_encode($ldSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
</head>
<body class="antialiased bg-white text-neutral-900 font-sans">
    @inertia
</body>
</html>
