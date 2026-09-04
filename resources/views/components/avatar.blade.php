@props([
    'user'   => null,
    'gender' => null,
    'size'   => 38,
    'class'  => '',
    'style'  => '',
])

@php
    $resolvedUser = $user ?? auth()->user();
    $photoUrl = $resolvedUser?->profile_photo ? asset('storage/' . $resolvedUser->profile_photo) : null;
    $targetGender = $gender ?? ($resolvedUser?->gender ?? 'neutral');
    $uid = uniqid('av_');
    $sizePx = is_numeric($size) ? $size . 'px' : $size;
@endphp

@if($photoUrl)
    <img src="{{ $photoUrl }}"
         alt="{{ $resolvedUser?->full_name ?? 'โปรไฟล์' }}"
         class="{{ $class }}"
         style="width: {{ $sizePx }}; height: {{ $sizePx }}; border-radius: 50%; object-fit: cover; flex-shrink: 0; display: inline-block; {{ $style }}">
@elseif($targetGender === 'male')
    {{-- Male SVG Avatar --}}
    <svg width="{{ $sizePx }}" height="{{ $sizePx }}" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" style="display:inline-block; border-radius:50%; flex-shrink:0; vertical-align:middle; {{ $style }}" role="img" aria-label="อวตารเพศชาย">
        <defs>
            <linearGradient id="mBg_{{ $uid }}" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#2563eb"/>
                <stop offset="100%" stop-color="#1d4ed8"/>
            </linearGradient>
            <linearGradient id="mShirt_{{ $uid }}" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#1e293b"/>
                <stop offset="100%" stop-color="#0f172a"/>
            </linearGradient>
            <clipPath id="mClip_{{ $uid }}">
                <circle cx="50" cy="50" r="50"/>
            </clipPath>
        </defs>
        <g clip-path="url(#mClip_{{ $uid }})">
            <!-- Background circle -->
            <circle cx="50" cy="50" r="50" fill="url(#mBg_{{ $uid }})"/>
            
            <!-- Body / Suit -->
            <path d="M50 63 L26 72 L10 100 L90 100 L74 72 Z" fill="url(#mShirt_{{ $uid }})"/>
            <!-- Shirt Collar / Inner Shirt -->
            <path d="M50 72 L41 62 L59 62 Z" fill="#ffffff"/>
            <path d="M50 68 L47 84 L53 84 Z" fill="#3b82f6"/> <!-- Tie -->
            <!-- Coat Lapels -->
            <path d="M41 62 L32 76 L44 86 L48 68 Z" fill="#334155"/>
            <path d="M59 62 L68 76 L56 86 L52 68 Z" fill="#334155"/>

            <!-- Neck -->
            <rect x="43" y="50" width="14" height="16" rx="3" fill="#fed7aa"/>
            <path d="M43 56 Q50 60 57 56 L57 60 Q50 64 43 60 Z" fill="#fbcfe8" opacity="0.4"/>

            <!-- Ears -->
            <circle cx="28" cy="44" r="5" fill="#fed7aa"/>
            <circle cx="72" cy="44" r="5" fill="#fed7aa"/>

            <!-- Head / Face -->
            <path d="M29 38 C29 24 71 24 71 38 C71 53 62 60 50 60 C38 60 29 53 29 38 Z" fill="#fed7aa"/>

            <!-- Facial Features -->
            <!-- Eyes -->
            <ellipse cx="41" cy="42" rx="2.5" ry="3" fill="#1e293b"/>
            <ellipse cx="59" cy="42" rx="2.5" ry="3" fill="#1e293b"/>
            <circle cx="42" cy="41" r="0.8" fill="#ffffff"/>
            <circle cx="60" cy="41" r="0.8" fill="#ffffff"/>
            <!-- Eyebrows -->
            <path d="M37 36 Q42 34 46 36" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/>
            <path d="M54 36 Q58 34 63 36" stroke="#0f172a" stroke-width="2" stroke-linecap="round"/>
            <!-- Nose -->
            <path d="M50 43 L48 48 L52 48" stroke="#fba779" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
            <!-- Smile -->
            <path d="M44 52 Q50 56 56 52" stroke="#ea580c" stroke-width="1.8" stroke-linecap="round"/>

            <!-- Modern Hair Style -->
            <path d="M27 36 C26 22 36 14 50 14 C65 14 74 20 74 34 C74 37 72 39 70 38 C68 33 66 26 50 25 C37 25 32 30 29 38 C28 39 27 38 27 36 Z" fill="#0f172a"/>
            <!-- Hair Volume / Quiff -->
            <path d="M32 23 C38 15 54 13 65 17 C68 18 72 23 71 25 C63 21 48 20 32 23 Z" fill="#1e293b"/>
        </g>
    </svg>
@elseif($targetGender === 'female')
    {{-- Female SVG Avatar --}}
    <svg width="{{ $sizePx }}" height="{{ $sizePx }}" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" style="display:inline-block; border-radius:50%; flex-shrink:0; vertical-align:middle; {{ $style }}" role="img" aria-label="อวตารเพศหญิง">
        <defs>
            <linearGradient id="fBg_{{ $uid }}" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#ec4899"/>
                <stop offset="100%" stop-color="#be185d"/>
            </linearGradient>
            <linearGradient id="fDress_{{ $uid }}" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#831843"/>
                <stop offset="100%" stop-color="#500724"/>
            </linearGradient>
            <clipPath id="fClip_{{ $uid }}">
                <circle cx="50" cy="50" r="50"/>
            </clipPath>
        </defs>
        <g clip-path="url(#fClip_{{ $uid }})">
            <!-- Background circle -->
            <circle cx="50" cy="50" r="50" fill="url(#fBg_{{ $uid }})"/>

            <!-- Back Hair -->
            <path d="M22 38 C22 22 34 14 50 14 C66 14 78 22 78 38 C78 58 74 76 72 84 C68 86 64 70 64 60 C36 60 36 70 32 84 C30 76 22 58 22 38 Z" fill="#1e1b4b"/>

            <!-- Body / Attire -->
            <path d="M50 65 L26 74 L10 100 L90 100 L74 74 Z" fill="url(#fDress_{{ $uid }})"/>
            <path d="M50 67 L38 80 L62 80 Z" fill="#f43f5e" opacity="0.3"/>
            <!-- Necklace -->
            <path d="M43 65 Q50 71 57 65" stroke="#fcd34d" stroke-width="2" fill="none" stroke-linecap="round"/>
            <circle cx="50" cy="69" r="2" fill="#fbbf24"/>

            <!-- Neck -->
            <rect x="44" y="50" width="12" height="16" rx="3" fill="#fed7aa"/>

            <!-- Ears & Earrings -->
            <circle cx="29" cy="45" r="4.5" fill="#fed7aa"/>
            <circle cx="71" cy="45" r="4.5" fill="#fed7aa"/>
            <circle cx="28" cy="50" r="2" fill="#fcd34d"/>
            <circle cx="72" cy="50" r="2" fill="#fcd34d"/>

            <!-- Head / Face -->
            <path d="M30 38 C30 24 70 24 70 38 C70 53 61 60 50 60 C39 60 30 53 30 38 Z" fill="#fed7aa"/>

            <!-- Blush on Cheeks -->
            <ellipse cx="37" cy="48" rx="3.5" ry="2" fill="#f43f5e" opacity="0.35"/>
            <ellipse cx="63" cy="48" rx="3.5" ry="2" fill="#f43f5e" opacity="0.35"/>

            <!-- Eyes with Lashes -->
            <ellipse cx="41" cy="42" rx="2.5" ry="3" fill="#1e1b4b"/>
            <ellipse cx="59" cy="42" rx="2.5" ry="3" fill="#1e1b4b"/>
            <circle cx="42" cy="41" r="0.8" fill="#ffffff"/>
            <circle cx="60" cy="41" r="0.8" fill="#ffffff"/>
            <!-- Eyelashes -->
            <path d="M37 40 L35 38" stroke="#1e1b4b" stroke-width="1.4" stroke-linecap="round"/>
            <path d="M63 40 L65 38" stroke="#1e1b4b" stroke-width="1.4" stroke-linecap="round"/>
            <!-- Eyebrows (Curved) -->
            <path d="M36 36 Q41 33 46 36" stroke="#312e81" stroke-width="1.6" stroke-linecap="round"/>
            <path d="M54 36 Q59 33 64 36" stroke="#312e81" stroke-width="1.6" stroke-linecap="round"/>
            <!-- Nose -->
            <path d="M50 44 L49 48 L51 48" stroke="#fba779" stroke-width="1.4" stroke-linecap="round"/>
            <!-- Smile (Sweet Lips) -->
            <path d="M44 53 Q50 58 56 53" stroke="#e11d48" stroke-width="2" stroke-linecap="round"/>

            <!-- Stylish Front Hair & Bangs -->
            <path d="M28 34 C28 20 38 14 50 14 C64 14 72 20 72 32 C68 24 58 22 47 24 C38 25 32 30 28 34 Z" fill="#312e81"/>
            <path d="M28 34 C30 44 32 54 35 60 C33 54 31 42 30 36 Z" fill="#1e1b4b"/>
            <path d="M72 32 C70 44 68 54 65 60 C67 54 69 42 70 34 Z" fill="#1e1b4b"/>
        </g>
    </svg>
@else
    {{-- Neutral / Other SVG Avatar --}}
    <svg width="{{ $sizePx }}" height="{{ $sizePx }}" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" class="{{ $class }}" style="display:inline-block; border-radius:50%; flex-shrink:0; vertical-align:middle; {{ $style }}" role="img" aria-label="อวตารทั่วไป">
        <defs>
            <linearGradient id="nBg_{{ $uid }}" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="#ea580c"/>
                <stop offset="100%" stop-color="#f97316"/>
            </linearGradient>
            <clipPath id="nClip_{{ $uid }}">
                <circle cx="50" cy="50" r="50"/>
            </clipPath>
        </defs>
        <g clip-path="url(#nClip_{{ $uid }})">
            <!-- Background circle -->
            <circle cx="50" cy="50" r="50" fill="url(#nBg_{{ $uid }})"/>
            
            <!-- Body -->
            <path d="M50 63 C34 63 20 75 14 96 C24 100 37 100 50 100 C63 100 76 100 86 96 C80 75 66 63 50 63 Z" fill="#ffffff" opacity="0.95"/>
            <path d="M43 63 L50 72 L57 63 Z" fill="#fed7aa"/>
            
            <!-- Head -->
            <circle cx="50" cy="40" r="17" fill="#fed7aa"/>
            <path d="M33 38 C33 24 42 20 50 20 C58 20 67 24 67 38 C64 28 58 24 50 24 C42 24 36 28 33 38 Z" fill="#334155"/>
            
            <!-- Eyes & Smile -->
            <ellipse cx="44" cy="40" rx="2" ry="2.5" fill="#1e293b"/>
            <ellipse cx="56" cy="40" rx="2" ry="2.5" fill="#1e293b"/>
            <circle cx="45" cy="39" r="0.7" fill="#ffffff"/>
            <circle cx="57" cy="39" r="0.7" fill="#ffffff"/>
            <path d="M45 47 Q50 51 55 47" stroke="#ea580c" stroke-width="1.8" stroke-linecap="round"/>
        </g>
    </svg>
@endif
