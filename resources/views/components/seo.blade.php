{{-- SEO Component: include in layout or pages --}}
@props([
    'title' => 'UNI Activity',
    'description' => 'ระบบศูนย์รวมกิจกรรมมหาวิทยาลัย - ค้นหา ลงทะเบียน และติดตามกิจกรรมของคุณ',
    'image' => null,
    'url' => null,
    'type' => 'website',
    'keywords' => 'กิจกรรม, มหาวิทยาลัย, ลงทะเบียน, รายงาน, UNI Activity',
    'author' => 'UNI Activity',
    'jsonLd' => null,
])

@php
    $baseUrl = config('app.url', 'https://uni-activity.example.com');
    $currentUrl = $url ?? request()->fullUrl();
    $ogImage = $image ?? asset('logo.svg');
    $siteName = 'UNI Activity';
@endphp

{{-- Primary Meta Tags --}}
<title>{{ $title }} | {{ $siteName }}</title>
<meta name=description content={{ $description }}>
<meta name=keywords content={{ $keywords }}>
<meta name=author content={{ $author }}>
<link rel=canonical href={{ $currentUrl }}>

{{-- Open Graph / Facebook --}}
<meta property=og:type content={{ $type }}>
<meta property=og:url content={{ $currentUrl }}>
<meta property=og:title content={{ $title }}>
<meta property=og:description content={{ $description }}>
<meta property=og:image content={{ $ogImage }}>
<meta property=og:site_name content={{ $siteName }}>
<meta property=og:locale content=th_TH>

{{-- Twitter --}}
<meta name=twitter:card content=summary_large_image>
<meta name=twitter:url content={{ $currentUrl }}>
<meta name=twitter:title content={{ $title }}>
<meta name=twitter:description content={{ $description }}>
<meta name=twitter:image content={{ $ogImage }}>
<meta name=twitter:site content=@uniactivity>

{{-- Structured Data --}}
@if($jsonLd)
<script type=application/ld+json>
{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}
</script>
@endif
