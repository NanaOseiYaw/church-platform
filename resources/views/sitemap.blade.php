<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    {{-- Static pages --}}
    @foreach($staticPages as $page)
    <url>
        <loc>{{ $page['loc'] }}</loc>
        <changefreq>{{ $page['changefreq'] }}</changefreq>
        <priority>{{ $page['priority'] }}</priority>
    </url>
    @endforeach

    {{-- Individual sermon pages --}}
    @foreach($sermons as $sermon)
    <url>
        <loc>{{ $baseUrl }}/sermons/{{ $sermon->slug }}</loc>
        @if($sermon->preached_at)
        <lastmod>{{ $sermon->preached_at->toAtomString() }}</lastmod>
        @endif
        <changefreq>yearly</changefreq>
        <priority>0.6</priority>
    </url>
    @endforeach

    {{-- Sermon series pages --}}
    @foreach($seriesList as $series)
    @if($series->slug)
    <url>
        <loc>{{ $baseUrl }}/series/{{ $series->slug }}</loc>
        @if($series->started_at)
        <lastmod>{{ $series->started_at->toAtomString() }}</lastmod>
        @endif
        <changefreq>monthly</changefreq>
        <priority>0.5</priority>
    </url>
    @endif
    @endforeach

</urlset>
