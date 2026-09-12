{{-- Echoed rather than written literally: with short_open_tag on, the XML
     declaration is read as a PHP open tag and the sitemap fails to compile. --}}
{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
    <url>
        <loc>{{ $url['loc'] }}</loc>
@if (!empty($url['lastmod']))
        <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
        <changefreq>{{ $url['changefreq'] }}</changefreq>
        <priority>{{ $url['priority'] }}</priority>
    </url>
@endforeach
</urlset>
