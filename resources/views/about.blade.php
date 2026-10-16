{{-- หน้า "เกี่ยวกับ UNI Activity" — keynote style เวอร์ชัน 2: สไลด์แนวตั้ง + visual mock CSS/SVG + ซิกแซกสลับข้าง + accent สีประจำสไลด์ --}}
@extends('layouts.app')
@section('title', __('เกี่ยวกับ UNI Activity'))

@php
    $isEn = app()->getLocale() === 'en';
    $slides = [
        // 1 — Welcome: การ์ดกิจกรรมลอยเอียง 3 ชิ้น
        [
            'num' => '01', 'accent' => '#ea580c', 'accent2' => '#1d4ed8', 'type' => $isEn ? 'Overview' : 'แนะนำระบบ',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 1 1 9-9c0 1.5-.5 2.5-1.5 2.5S18 13.6 18 12.5 18.9 10 20 10M9.5 10.9l-1.4 1.4a2.2 2.2 0 0 0 3.1 3.1l-.4-.4M14.5 10.9l-1.4 1.4a2.2 2.2 0 0 1-3.1 3.1l.4-.4"/>',
            'title' => $isEn ? 'Welcome to :app' : 'ยินดีต้อนรับสู่ :app',
            'mark'  => $isEn ? 'App' : 'ที่เดียว',
            'desc'  => $isEn
                ? 'All university activities, jobs and internships — gathered in one place, in both Thai and English.'
                : 'ศูนย์รวมกิจกรรม งาน และฝึกงานของมหาวิทยาลัย รองรับทั้งภาษาไทยและอังกฤษ เปลี่ยนภาษาได้จากปุ่ม TH / EN มุมขวาบน',
            'visual' => 'cards',
        ],
        // 2 — OTP: โทรศัพท์ + ข้อความรหัส
        [
            'num' => '02', 'accent' => '#f97316', 'accent2' => '#2563eb', 'type' => $isEn ? 'Sign-in' : 'การเข้าสู่ระบบ',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16v12H4zM4 7l8 6 8-6"/>',
            'title' => $isEn ? 'Log in with your student ID' : 'เข้าสู่ระบบด้วยรหัสนักศึกษา',
            'mark'  => $isEn ? 'No password.' : 'ไม่ต้องจำรหัสผ่าน',
            'desc'  => $isEn
                ? 'Just enter your student ID — we email you a one-time code (OTP). Staff and organizers log in with email and password.'
                : 'นักศึกษากรอกรหัสนักศึกษา แล้วรับรหัส OTP ทางอีเมล — อาจารย์/เจ้าหน้าที่จัดกิจกรรมเข้าด้วยอีเมลและรหัสผ่าน',
            'visual' => 'phone',
        ],
        // 3 — Search: แถบค้นหา + ชิปฟิลเตอร์
        [
            'num' => '03', 'accent' => '#ea580c', 'accent2' => '#0d9488', 'type' => $isEn ? 'Explore' : 'การค้นหา',
            'icon' => '<circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.3-4.3"/>',
            'title' => $isEn ? 'Find the right activities' : 'ค้นหากิจกรรมที่ใช่',
            'mark'  => $isEn ? 'in seconds.' : 'ในไม่กี่วินาที',
            'desc'  => $isEn
                ? 'Search and filter by category, faculty, year and open/closed status. Bookmark interesting ones to come back later.'
                : 'ค้นหาและกรองกิจกรรมตามหมวดหมู่ คณะ ชั้นปี และสถานะเปิด/ปิดรับ — บุ๊กมาร์กกิจกรรมที่สนใจไว้กลับมาดูภายหลัง',
            'visual' => 'search',
        ],
        // 4 — Register/QR: บัตรเข้างาน + QR
        [
            'num' => '04', 'accent' => '#ea580c', 'accent2' => '#0d9488', 'type' => $isEn ? 'Attendance' : 'การเข้าร่วม',
            'icon' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path stroke-linecap="round" d="M8 7h8M8 11h2m2 0h2m2 0h0M8 15h2m2 0h2m2 0h0"/>',
            'title' => $isEn ? 'Register, waitlist & check in' : 'สมัคร รอเข้าคิว และเช็คอิน',
            'mark'  => $isEn ? 'All in a few taps.' : 'แตะไม่กี่ครั้ง',
            'desc'  => $isEn
                ? 'Register in a few taps, join the waitlist when full, pay the fee online and check in at the venue with a QR code.'
                : 'ลงทะเบียนนัดเดียว ถ้าเต็มสามารถรอเข้าคิว (waitlist) ชำระค่าสมัครออนไลน์ และเช็คอินหน้างานด้วย QR code',
            'visual' => 'ticket',
        ],
        // 5 — Calendar/Map: กริดปฏิทิน + พินแผนที่
        [
            'num' => '05', 'accent' => '#f97316', 'accent2' => '#1d4ed8', 'type' => $isEn ? 'Schedule' : 'ตารางกิจกรรม',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.5-2.7V6.3L9 9m0 11l6-3m-6 3V9m6 8l5.5 2.7V6.3L15 9m0 8V9M9 9l6-3m0 3L9 6"/>',
            'title' => $isEn ? 'Your schedule on the map' : 'ตารางของคุณ บนแผนที่',
            'mark'  => $isEn ? 'Never miss a thing.' : 'ไม่พลาดแม้แต่เล่ห์',
            'desc'  => $isEn
                ? 'See your schedule in the calendar, find venues on the map, and browse job & internship postings.'
                : 'ดูตารางกิจกรรมในปฏิทิน หาสถานที่จัดผ่านแผนที่ และติดตามประกาศงาน/ฝึกงานที่เปิดรับใหม่',
            'visual' => 'calmap',
        ],
        // 6 — Progress: กราฟบาร์ + หมวกจบ
        [
            'num' => '06', 'accent' => '#f97316', 'accent2' => '#7c3aed', 'type' => $isEn ? 'Progress' : 'ความคืบหน้า',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 19h16M6 19V9m4 10V5m4 14v-7m4 7V8"/>',
            'title' => $isEn ? 'Track your progress' : 'สรุปกิจกรรมของคุณ',
            'mark'  => $isEn ? 'Up to graduation.' : 'จนถึงวันจบการศึกษา',
            'desc'  => $isEn
                ? 'Follow your activity hours and progress all the way to graduation — nothing gets lost.'
                : 'ติดตามชั่วโมงกิจกรรมและความคืบหน้าจนถึงวันสำเร็จการศึกษา — ไม่มีกิจกรรมไหนหายหรือถูกสรุปพลาด',
            'visual' => 'chart',
        ],
    ];
    $total = count($slides);
@endphp

@section('content')
{{-- Top Hero — keynote opening --}}
<section class="key-hero" id="aboutTop">
    <div class="kh-logo-wrap" aria-hidden="true">
        <span class="kh-logo"><img src="{{ asset('logo.svg') }}" alt="" loading="eager"></span>
    </div>
    <p class="kh-eyebrow">{{ $isEn ? 'About' : 'รู้จักแอปของเรา' }}</p>
    <h1 class="kh-title">UNI Activity</h1>
    <p class="kh-sub">{{ $isEn ? 'Every activity. Every opportunity. ' : 'ทุกกิจกรรม ทุกโอกาส ' }}<span class="kh-grad">{{ $isEn ? 'One place.' : 'ในที่เดียว' }}</span></p>
    <svg class="kh-cue" pointer-events="none" style="pointer-events:none;" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
</section>

@foreach($slides as $i => $slide)
    <section class="keynote-panel {{ $i % 2 === 0 ? 'keynote-light' : 'keynote-dark' }} {{ $i % 2 === 1 ? 'kp-flip' : '' }}" data-dot="{{ $i }}" style="--acc: {{ $slide['accent'] }}; --acc2: {{ $slide['accent2'] }};">
        <div class="kp-inner">
            <div class="kp-copy">
                <p class="kp-num">{{ $slide['num'] }}</p>
                <p class="kp-type">{{ $slide['type'] }}</p>
                <span class="kp-icon">
                    <svg width="44" height="44" style="width:44px;height:44px;" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">{{ $slide['icon'] }}</svg>
                </span>
                <h2 class="kp-title">{{ __($slide['title'], ['app' => 'UNI Activity']) }} <span class="kp-mark">{{ __($slide['mark']) }}</span></h2>
                <p class="kp-desc">{{ $slide['desc'] }}</p>
            </div>

            <div class="kp-visual" data-parallax="0.045" aria-hidden="true">
                @if($slide['visual'] === 'cards')
                    <div class="vk vk-card-top"><span class="vk-strip"></span><span class="vk-line w70"></span><span class="vk-line w50"></span></div>
                    <div class="vk vk-card-mid"><span class="vk-strip vk-strip-b"></span><span class="vk-line w60"></span><span class="vk-line w45"></span><span class="vk-badge">{{ $isEn ? 'OPEN' : 'เปิดรับ' }}</span></div>
                    <div class="vk vk-card-far"><span class="vk-strip vk-strip-c"></span><span class="vk-line w65"></span><span class="vk-line w40"></span></div>
                @elseif($slide['visual'] === 'phone')
                    <div class="vk vk-phone">
                        <span class="vk-phone-notch"></span>
                        <div class="vk-bubble">
                            <p class="vk-bubble-head">{{ $isEn ? 'Verification code' : 'รหัสยืนยัน' }}</p>
                            <p class="vk-otp">5 8 2 9 1 4</p>
                            <p class="vk-bubble-tail">{{ $isEn ? 'Expires in 10 minutes' : 'หมดอายุใน 10 นาที' }}</p>
                        </div>
                        <span class="vk-line w50"></span><span class="vk-line w35"></span>
                    </div>
                @elseif($slide['visual'] === 'search')
                    <div class="vk vk-search">
                        <svg width="20" height="20" style="width:20px;height:20px;flex:none;" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="M21 21l-4.3-4.3"/></svg>
                        <span class="vk-caret"></span>
                    </div>
                    <div class="vk-chips">
                        <span class="vk-chip c1">{{ $isEn ? 'Activity' : 'กิจกรรม' }}</span>
                        <span class="vk-chip c2">{{ $isEn ? 'Jobs' : 'งาน/ฝึกงาน' }}</span>
                        <span class="vk-chip c3">{{ $isEn ? 'Faculty' : 'คณะ' }}</span>
                        <span class="vk-chip c4">{{ $isEn ? 'Open' : 'เปิดรับ' }}</span>
                    </div>
                    <div class="vk vk-hit"><span class="vk-dot"></span><span class="vk-line w75"></span><span class="vk-side">{{ $isEn ? '20 seats' : 'รับ 20 คน' }}</span></div>
                @elseif($slide['visual'] === 'ticket')
                    <div class="vk vk-ticket">
                        <div class="tk-head">{{ $isEn ? 'ENTRANCE PASS' : 'บัตรเข้างาน' }}</div>
                        <svg class="tk-qr" width="82" height="82" viewBox="0 0 32 32" style="width:82px;height:82px;"><rect x="2" y="2" width="10" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="2.6"/><rect x="20" y="2" width="10" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="2.6"/><rect x="2" y="20" width="10" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="2.6"/><path d="M20 20h3v3h-3zm5 0h5v3h-5zm-5 5h3v5h-3zm5 2h5v3h-5zm2-5h3v3h-3z" fill="currentColor"/></svg>
                        <div class="tk-dash"></div>
                        <div class="tk-foot">{{ $isEn ? 'STEM Fair 2026 · Hall 3' : 'งานสาธิตโรงเรียน STEM 2026 · หอประชุม 3' }}</div>
                    </div>
                    <span class="vk-float-badge">{{ $isEn ? 'Waitlist #3' : 'คิวรอ ลำดับ 3' }}</span>
                @elseif($slide['visual'] === 'calmap')
                    <div class="vk vk-cal">
                        @for($d = 0; $d < 28; $d++)
                            <span class="vk-cell {{ in_array($d, [9, 16, 23]) ? 'hot' : '' }} {{ $d === 16 ? 'now' : '' }}"></span>
                        @endfor
                    </div>
                    <div class="vk vk-pin"><svg width="30" height="30" style="width:30px;height:30px;" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"/><circle cx="12" cy="9.5" r="2.4"/></svg><span>{{ $isEn ? 'Hall 3 · Building B' : 'หอประชุม 3 · อาคาร B' }}</span></div>
                @else
                    <div class="vk vk-chart">
                        <span class="bar b1"></span><span class="bar b2"></span><span class="bar b3"></span><span class="bar b4"></span><span class="bar b5 hot"></span>
                    </div>
                    <span class="vk-float-badge">{{ $isEn ? '46 hrs · 82%' : '46 ชั่วโมง · 82%' }}</span>
                @endif
            </div>
        </div>
    </section>
@endforeach

<nav class="about-dotnav" aria-label="{{ $isEn ? 'Slide navigation' : 'การนำทางสไลด์' }}">
    @foreach($slides as $i => $s)
        <button type="button" class="about-dnav {{ $i === 0 ? 'active' : '' }}" data-dotnav="{{ $i }}" aria-label="Slide {{ $i + 1 }}"></button>
    @endforeach
</nav>

<style>
    /* Full-bleed: ส่วน /about ลอยออกจาก .container (padding 24px + max-width 1200) ชิดขอบจอทุกความกว้าง */
    html, body { overflow-x: hidden; overflow-x: clip; }
    .key-hero, .keynote-panel {
        width: 100vw;
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);
    }
    .container { padding-left: 0; padding-right: 0; } /* บนหน้านี้เท่านั้น — ปัดโค้ง padding ให้แนบขอบ */
    /* กัน mock ลอยปาดเกินสองขอบจอเจใต้แต่ละคัน */
    .keynote-panel { overflow: hidden; }

    /* Hero (คงเดิม) */
    .key-hero {
        min-height: 88vh;
        display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: .85rem; text-align: center; padding: 3rem 1.25rem; position: relative;
    }
    .kh-eyebrow { font-size: .95rem; letter-spacing: .12em; text-transform: uppercase; color: #ea580c; font-weight: 600; margin: 0; }
    .kh-title { font-size: clamp(3rem, 9.4vw, 6.6rem); line-height: 1.04; font-weight: 800; letter-spacing: -0.028em; margin: 0; color: #0f0f10; }
    .kh-sub { font-size: clamp(1.35rem, 3.7vw, 2.2rem); font-weight: 700; margin: 0; letter-spacing: -0.012em; color: #222226; }
    .kh-grad {
        background: linear-gradient(92deg, #ea580c 6%, #f97316 42%, #1d4ed8 100%);
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: #ea580c;
    }
    .kh-cue { position: absolute; bottom: 2.4rem; animation: kh-bounce 1.8s ease-in-out infinite, kh-in 1s cubic-bezier(.2,.85,.28,1) .5s both; transition: opacity .45s ease; }
    .key-hero.cue-gone .kh-cue { opacity: 0 !important; }
    .kh-eyebrow, .kh-title, .kh-sub { animation: kh-in 1s cubic-bezier(.2,.85,.28,1) both; }
    .kh-title { animation-delay: .12s; }
    .kh-sub   { animation-delay: .26s; }
    @keyframes kh-in { from { opacity: 0; transform: scale(.94); } to { opacity: 1; transform: none; } }
    /* ── Animated logo showcase ── */
    .kh-logo-wrap { position: relative; width: 96px; height: 96px; margin-bottom: .5rem; animation: kh-in 1s cubic-bezier(.2,.85,.28,1) .05s both; }
    .kh-logo {
        position: relative; display: block; width: 100%; height: 100%; border-radius: 22%; overflow: hidden;
        box-shadow: 0 10px 28px rgba(15,23,42,.14);
        animation: logo-float 5.5s ease-in-out 1s infinite alternate;
    }
    .kh-logo img { display: block; width: 100%; height: 100%; }
    .kh-logo::after {
        content: ''; position: absolute; inset: -35%; pointer-events: none;
        background: linear-gradient(115deg, transparent 43%, rgba(255,255,255,.9) 50%, transparent 57%);
        transform: translateX(-130%) rotate(0.001deg);
        animation: logo-shine 4.6s ease-in-out 1.4s infinite;
    }
    @keyframes logo-float { from { translate: 0 3px; } to { translate: 0 -5px; } }
    @keyframes logo-shine { 0%, 58% { transform: translateX(-130%); } 86%, 100% { transform: translateX(130%); } }
    html[data-theme="dark"] .kh-logo { box-shadow: 0 12px 32px rgba(0,0,0,.5); }
    /* Panel: ซิกแซก 2 คอลัมน์ */
    .keynote-panel {
        min-height: 92vh;
        display: flex; align-items: center; justify-content: center;
        padding: 4.5rem 1.5rem; overflow: hidden;
        --k-anim: cubic-bezier(.2,.85,.28,1);
    }
    /* ── Progressive reveal: JS ขับ opacity/transform ตามระยะเลื่อน "ค่อย ๆ โหลดเมื่อเลื่อนถึง" ──
       (ค่าเริ่มต้นมองเห็นทุกอย่าง — ไม่มีสถานะซ่อนนิ่ง, JS/IO พังก็แสดงผลปกติ) ── */
    .kp-copy > *, .kp-visual { will-change: opacity, transform; }
    .keynote-light { background: #fbfbfd; color: #1d1d1f; position: relative; }
    .keynote-dark  { background: #0a0a0c; color: #f5f5f7; }
    .kp-inner {
        position: relative; z-index: 1;
        display: grid; grid-template-columns: 1fr; gap: 2.5rem;
        align-items: center; justify-items: center; max-width: 1060px; width: 100%;
        text-align: center;
    }
    @media (min-width: 900px) {
        .kp-inner { grid-template-columns: 1.02fr .98fr; text-align: left; gap: 4rem; }
        .kp-flip .kp-copy { order: 2; }
        .kp-flip .kp-visual { order: 1; }
    }
    .kp-copy { display: flex; flex-direction: column; align-items: inherit; gap: .9rem; max-width: 46ch; }
    .kp-num { font-size: .9rem; letter-spacing: .2em; font-weight: 800; color: var(--acc, #ea580c); margin: 0; }
    .kp-icon {
        width: 64px; height: 64px; border-radius: 999px; display: inline-flex; align-items: center; justify-content: center;
    }
    .keynote-light .kp-icon { background: var(--acc, #ea580c); color: #ffffff; }
    .keynote-dark  .kp-icon { background: var(--acc, #f97316); color: #ffffff; }
    .kp-title { font-size: clamp(1.95rem, 5vw, 3.25rem); font-weight: 800; letter-spacing: -0.024em; margin: 0; line-height: 1.1; }
    .kp-mark {
        display: block;
        margin-top: .35rem;
        background: linear-gradient(92deg, var(--acc, #ea580c), var(--acc2, #1d4ed8) 92%);
        -webkit-background-clip: text; background-clip: text;
        -webkit-text-fill-color: transparent; color: var(--acc, #ea580c);
        font-size: .92em;
    }
    .kp-desc { font-size: clamp(1.08rem, 2.6vw, 1.36rem); line-height: 1.66; margin: 0; font-weight: 500; }
    .keynote-light .kp-desc { color: rgba(15,15,16,.92); }
    .keynote-dark  .kp-desc { color: rgba(255,255,255,.94); }

    /* ── Text flourish: typewriter kicker + word cascade + gradient sweep ── */
    .kp-type {
        font-size: .78rem; letter-spacing: .18em; text-transform: uppercase;
        font-weight: 700; margin: 0; min-height: 1.4em;
        color: var(--acc2, #1d4ed8);
    }
    .kp-type::after {
        content: '▍'; margin-left: 2px; opacity: 0;
        animation: type-caret 1s steps(2) infinite;
    }
    .kp-type.typing::after { opacity: .75; }
    @keyframes type-caret { 0%, 49% { opacity: .75; } 50%, 100% { opacity: .05; } }
    .kp-title .tw { display: inline-block; }

    /* ── Visual mocks (pure CSS/SVG) ── */
    @keyframes vk-bob { from { translate: 0 4px; } to { translate: 0 -5px; } }
    .kp-visual { animation: vk-bob 6.5s ease-in-out infinite alternate; }
    .kp-visual { position: relative; width: 100%; max-width: 430px; min-height: 330px;
        display: flex; align-items: center; justify-content: center; }
    .vk { position: absolute; }
    .vk-line { display: block; height: 9px; border-radius: 999px; margin: 9px 0; background: currentColor; opacity: .16; }
    .w35 { width: 35%; } .w40 { width: 40%; } .w45 { width: 45%; } .w50 { width: 50%; } .w55 { width: 55%; } .w60 { width: 60%; } .w65 { width: 65%; } .w70 { width: 70%; } .w75 { width: 75%; }
    .keynote-light .vk-card-top, .keynote-light .vk-card-mid, .keynote-light .vk-card-far,
    .keynote-light .vk-phone, .keynote-light .vk-search, .keynote-light .vk-ticket,
    .keynote-light .vk-cal, .keynote-light .vk-pin, .keynote-light .vk-chart, .keynote-light .vk-hit { color: #4b4b52; background: #fff; }
    .keynote-dark .vk-card-top, .keynote-dark .vk-card-mid, .keynote-dark .vk-card-far,
    .keynote-dark .vk-phone, .keynote-dark .vk-search, .keynote-dark .vk-ticket,
    .keynote-dark .vk-cal, .keynote-dark .vk-pin, .keynote-dark .vk-chart, .keynote-dark .vk-hit { color: #d1d1d6; background: #17171a; }
    .keynote-light .vk-hit { background: #fff; }
    .keynote-dark .vk-hit { background: #17171a; }
    .vk-card-far, .vk-card-mid, .vk-card-top {
        width: 200px; border-radius: 18px; padding: 14px; box-shadow: 0 18px 44px rgba(0,0,0,.16);
    }
    .vk-card-far { transform: rotate(10deg) translate(-18px, -40px); z-index: 1; }
    .vk-card-top { transform: translate(72px, -6px) rotate(-7deg); z-index: 2; }
    .vk-card-mid { transform: translate(6px, 52px) rotate(2deg); z-index: 3; }
    .vk-strip { display: block; height: 46px; border-radius: 10px; margin-bottom: 10px;
        background: linear-gradient(120deg, var(--acc, #ea580c), var(--acc2, #1d4ed8)); }
    .vk-strip-b { background: linear-gradient(120deg, var(--acc2, #1d4ed8), var(--acc, #ea580c)); }
    .vk-strip-c { opacity: .55; background: linear-gradient(120deg, var(--acc, #ea580c), var(--acc2, #1d4ed8)); }
    .vk-badge {
        position: absolute; top: 52px; right: 12px; font-size: .68rem; font-weight: 800; letter-spacing: .08em;
        color: #fff; background: #10b981; padding: .18rem .5rem; border-radius: 999px;
    }
    /* phone */
    .vk-phone {
        width: 216px; height: 366px; border-radius: 38px; padding: 40px 16px 20px;
        box-shadow: 0 22px 60px rgba(0,0,0,.22); transform: rotate(-3deg);
        border: 7px solid rgba(120,120,128,.35); background: none; position: relative;
    }
    .keynote-dark .vk-phone { background: #17171a; }
    .vk-phone-notch { position: absolute; top: 14px; left: 50%; transform: translateX(-50%); width: 78px; height: 20px; border-radius: 999px; background: rgba(120,120,128,.3); }
    .vk-bubble { background: color-mix(in srgb, var(--acc, #ea580c) 12%, transparent); color: inherit; border-radius: 16px 16px 16px 4px; padding: 14px 16px; margin-bottom: 16px; text-align: center; }
    .keynote-light .vk-bubble { background: color-mix(in srgb, var(--acc, #ea580c) 10%, #fff); box-shadow: 0 10px 24px rgba(0,0,0,.07); }
    .keynote-dark .vk-bubble { background: color-mix(in srgb, var(--acc, #ea580c) 14%, #1c1c20); }
    .vk-bubble-head { margin: 0 0 8px; font-size: .8rem; opacity: .65; }
    .vk-otp { margin: 0; font-size: 1.5rem; font-weight: 800; letter-spacing: .24em; color: var(--acc, #ea580c); }
    .vk-bubble-tail { margin: 8px 0 0; font-size: .72rem; opacity: .6; }
    /* search */
    .vk-search {
        width: 250px; border-radius: 999px; padding: 15px 20px; display: flex; align-items: center; gap: 8px;
        box-shadow: 0 18px 44px rgba(0,0,0,.14); transform: translate(0, -64px); color: #86868b; z-index: 2;
    }
    .keynote-light .vk-search { background: #fff; }
    .vk-caret { width: 2px; height: 18px; background: var(--acc, #ea580c); animation: kh-caret 1.1s steps(2) infinite; }
    /* fix: steps animation */
    @keyframes kh-caret { 0%, 49% { opacity: 1; } 50%, 100% { opacity: 0; } }
    .vk-chips { position: absolute; top: 88px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 3; }
    .vk-chip { font-size: .72rem; font-weight: 700; padding: .3rem .7rem; border-radius: 999px; color: #fff; box-shadow: 0 10px 22px rgba(0,0,0,.16); }
    .vk-chip.c1 { background: var(--acc, #ea580c); } .vk-chip.c2 { background: var(--acc2, #1d4ed8); }
    .vk-chip.c3 { background: #2997ff; } .vk-chip.c4 { background: #10b981; }
    .vk-hit {
        width: 260px; border-radius: 16px; padding: 14px 16px; display: flex; align-items: center; gap: 12px;
        box-shadow: 0 16px 38px rgba(0,0,0,.12); transform: translate(0, 88px); z-index: 2;
    }
    .vk-dot { width: 34px; height: 34px; flex: none; border-radius: 10px; background: linear-gradient(120deg, var(--acc, #ea580c), var(--acc2, #1d4ed8)); }
    .vk-side { margin-left: auto; font-size: .7rem; font-weight: 700; border-radius: 999px; padding: .2rem .5rem; background: color-mix(in srgb, var(--acc, #ea580c) 14%, transparent); color: var(--acc, #ea580c); opacity: 1; }
    .keynote-dark .vk-side { color: var(--acc, #ea580c); }
    /* ticket */
    .vk-ticket {
        width: 250px; border-radius: 22px; padding: 20px 20px 16px; text-align: center; color: inherit;
        box-shadow: 0 22px 54px rgba(0,0,0,.20); transform: rotate(-4deg); z-index: 2;
    }
    .tk-head { font-size: .72rem; font-weight: 800; letter-spacing: .16em; color: var(--acc, #ea580c); margin-bottom: 14px; }
    .tk-qr { color: inherit; opacity: .85; }
    .tk-dash { border-top: 2px dashed rgba(120,120,128,.3); margin: 14px -20px 12px; position: relative; }
    .tk-dash::before, .tk-dash::after {
        content: ''; position: absolute; top: -11px; width: 22px; height: 22px; border-radius: 999px;
        background: unset;
    }
    .keynote-light .tk-dash::before, .keynote-light .tk-dash::after { background: #fbfbfd; }
    .keynote-dark .tk-dash::before, .keynote-dark .tk-dash::after { background: #0a0a0c; }
    .tk-dash::before { left: -11px; } .tk-dash::after { right: -11px; }
    .tk-foot { font-size: .78rem; opacity: .7; }
    .vk-float-badge {
        position: absolute; top: 10px; right: 2%; z-index: 3; font-size: .78rem; font-weight: 800; color: #fff;
        background: linear-gradient(120deg, var(--acc, #ea580c), var(--acc2, #1d4ed8));
        padding: .4rem .8rem; border-radius: 999px; box-shadow: 0 14px 30px rgba(0,0,0,.25);
        transform: rotate(6deg);
    }
    .kp-visual:not([data-parallax]) .vk-float-badge { top: 4px; }
    /* calendar + pin */
    .vk-cal {
        width: 240px; height: 218px; border-radius: 20px; padding: 18px;
        display: grid; grid-template-columns: repeat(7, 1fr); gap: 7px; align-content: center;
        box-shadow: 0 18px 44px rgba(0,0,0,.15); transform: rotate(2.5deg); z-index: 2;
    }
    .vk-cell { width: 14px; height: 14px; border-radius: 999px; background: currentColor; opacity: .12; justify-self: center; align-self: center; }
    .vk-cell.hot { opacity: .5; background: var(--acc, #ea580c); }
    .vk-cell.now { opacity: 1; background: var(--acc2, #1d4ed8); box-shadow: 0 0 0 5px color-mix(in srgb, var(--acc2, #1d4ed8) 25%, transparent); }
    .vk-pin {
        position: absolute; right: -4%; bottom: 6%; z-index: 3; display: flex; align-items: center; gap: 10px;
        border-radius: 999px; padding: 10px 16px 10px 12px; font-size: .8rem; font-weight: 700; color: inherit;
        box-shadow: 0 16px 36px rgba(0,0,0,.18); transform: rotate(-3deg);
    }
    .keynote-light .vk-pin { background: #fff; color: #ea580c; }
    .keynote-dark .vk-pin { background: #17171a; color: #2997ff; }
    /* chart */
    .vk-chart {
        width: 270px; height: 230px; border-radius: 20px; padding: 20px;
        display: flex; align-items: flex-end; justify-content: center; gap: 14px;
        box-shadow: 0 18px 44px rgba(0,0,0,.15); transform: rotate(-2deg); z-index: 2;
    }
    .bar { width: 30px; border-radius: 10px 10px 4px 4px; background: currentColor; opacity: .18; }
    .bar.b1 { height: 26%; } .bar.b2 { height: 40%; } .bar.b3 { height: 34%; } .bar.b4 { height: 62%; } .bar.b5 { height: 86%; opacity: 1; background: linear-gradient(180deg, var(--acc, #ea580c), var(--acc2, #1d4ed8)); }

    /* Dot navigation */
    .about-dotnav {
        position: fixed; right: 16px; top: 50%; transform: translateY(-50%);
        display: flex; flex-direction: column; gap: 10px; z-index: 40;
    }
    .about-dnav {
        width: 8px; height: 8px; border-radius: 999px; border: none; padding: 0; cursor: pointer;
        background: rgba(120,120,128,.32);
        transition: background .25s, transform .25s;
    }
    .about-dnav.active { background: #ea580c; transform: scale(1.45); box-shadow: 0 0 0 5px rgba(234,88,12,.16); animation: dot-pulse 2.6s ease-in-out infinite; }
    @keyframes dot-pulse { 0%, 100% { box-shadow: 0 0 0 4px rgba(234,88,12,.14); } 50% { box-shadow: 0 0 0 9px rgba(234,88,12,.05); } }
    @media (max-width: 899px) { .about-dotnav { display: none; } } /* ซ่อนลงจอเล็ก-กลาง กันทับ mock/ข้อความ */

    @media (max-width: 899px) {
        .keynote-panel { min-height: 80vh; padding: 4.5rem 1.25rem 5.5rem; }
        .kh-cue { bottom: 1.1rem; }
        /* บนมือถือ: ลำดับ lockstep — copy อยู่บนเสมอ แล้ว visual แยก margin กันทับกัน */
        .kp-inner { gap: 1.25rem; }
        .kp-copy, .kp-flip .kp-copy { order: 1; }
        .kp-visual, .kp-flip .kp-visual { order: 2; }
        .kp-visual {
            min-height: 350px; transform: scale(.84);
            margin: 3.2rem auto 1.2rem;   /* เผื่อน้ำหนัก mock ที่ลอยพ้นกรอบ (translate เข้มมือถือ) */
        }
        .vk-phone { height: 330px; width: 196px; padding: 34px 14px 18px; }
        .vk-card-far { transform: rotate(9deg) translate(-8px, -18px); }
        .vk-card-top { transform: translate(46px, -4px) rotate(-6deg); }
        .vk-card-mid { transform: translate(2px, 40px) rotate(2deg); }
        .vk-search { transform: translate(0, -30px); width: 220px; }
        .vk-chips { transform: translateX(-50%) scale(.92); top: 68px; }
        .vk-hit { transform: translate(0, 62px); width: 230px; }
        .vk-cal { width: 205px; height: 188px; }
        .vk-chart { width: 235px; height: 200px; }
        .vk-ticket { width: 224px; }
        .kp-visual .vk-float-badge, .kp-visual .vk-pin { right: 2%; }
    }
    @media (max-width: 420px) {
        .kp-visual { transform: scale(.74); min-height: 300px; margin: 3.8rem auto .8rem; }
        .keynote-panel { padding: 4rem 1rem 5.5rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        .keynote-panel, .keynote-panel.in, .keynote-panel.in .kp-copy > *, .keynote-panel.in .kp-visual { animation: none !important; opacity: 1 !important; transform: none !important; }
        .kp-title .tw { opacity: 1 !important; transform: none !important; }
        .kp-mark { clip-path: none !important; }
        .kp-type::after { animation: none !important; opacity: 0 !important; }
        .kh-cue, .kh-eyebrow, .kh-title, .kh-sub { animation: none !important; opacity: 1 !important; transform: none !important; }
        .kp-visual { animation: none !important; }
        .kh-logo-wrap, .kh-logo, .kh-logo::after { animation: none !important; opacity: 1 !important; transform: none !important; }
        .about-dnav.active { animation: none !important; }
        .vk-caret { animation: none !important; }
    }
    html[data-theme="dark"] .key-hero, html.dark .key-hero { color: #f5f5f7; }
    html[data-theme="dark"] .kh-title, html.dark .kh-title { color: #ffffff; }
    html[data-theme="dark"] .kh-sub, html.dark .kh-sub { color: #f5f5f7; font-weight: 700; }
    html[data-theme="dark"] .kh-sub .kh-grad, html.dark .kh-sub .kh-grad { -webkit-text-fill-color: transparent; }
</style>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(function() {
    var panels = Array.prototype.slice.call(document.querySelectorAll('.keynote-panel'));
    var dots = Array.prototype.slice.call(document.querySelectorAll('.about-dnav'));
    if (!panels.length) return;
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (!('IntersectionObserver' in window)) return;   /* ไม่มี IO = เนื้อหาปกติทั้งหมด (ค่าเริ่มต้นมองเห็นอยู่แล้ว) */

    /* ── Progressive reveal: ค่อย ๆ โหลดตามระยะเลื่อนมาถึง (ไม่ใช้คลาส .in ทีเดียววันตายตัวอีก) ── */
    if (reduce) return;   /* ผู้ใช้ขอลด motion = แสดงผลคงที่ทั้งหมด */
    var infos = panels.map(function(p) {
        var kids = Array.prototype.slice.call(p.querySelectorAll('.kp-num, .kp-type, .kp-icon, .kp-title, .kp-desc'));
        // แยกหัวข้อหลักเป็นคำ (text node แรก) — คงแต่ละคำเป็น inline-block เพื่อ cascade
        var title = p.querySelector('.kp-title');
        var words = [];
        if (title) {
            var first = title.childNodes[0];
            if (first && first.nodeType === 3) {
                var frag = document.createDocumentFragment();
                first.nodeValue.split(/(\s+)/).forEach(function(seg) {
                    if (!seg) return;
                    if (/^\s+$/.test(seg)) { frag.appendChild(document.createTextNode(seg)); }
                    else {
                        var sp = document.createElement('span');
                        sp.className = 'tw'; sp.textContent = seg;
                        frag.appendChild(sp); words.push(sp);
                    }
                });
                title.replaceChild(frag, first);
            }
        }
        var typeEl = p.querySelector('.kp-type');
        return { p: p, kids: kids, words: words,
            mark: p.querySelector('.kp-mark'),
            typeEl: typeEl,
            full: typeEl ? typeEl.textContent : '',
            lastN: -1,
            vis: p.querySelector('.kp-visual') };
    });
    infos.forEach(function(info) {
        info.visKids = info.vis ? Array.prototype.slice.call(info.vis.querySelectorAll('.vk')) : [];
        info.units = info.full ? thaiClusters(info.full) : [];
    });
    function clamp01(t) { return Math.max(0, Math.min(1, t)); }
    function easeOut(t) { return 1 - Math.pow(1 - t, 2.4); }
    /* (C) แยกหน่วยพิมพ์เป็นพยางค์: สระลอย/วรรณยุกต์ไทยเกาะตัวหลัก */
    var CMB = /[\u0E31\u0E34-\u0E3A\u0E47-\u0E4E]/;
    function thaiClusters(str) {
        var out = [], i = 0;
        while (i < str.length) {
            var ch = str[i]; i++;
            while (i < str.length && CMB.test(str[i])) { ch += str[i]; i++; }
            out.push(ch);
        }
        return out;
    }
    /* (A) hero parallax: ขับด้วย translate property — ไม่ชน animation kh-in (transform) */
    var hero = document.querySelector('.key-hero');
    var heroKids = hero ? Array.prototype.slice.call(hero.querySelectorAll('.kh-logo-wrap, .kh-eyebrow, .kh-title, .kh-sub')) : [];
    function update() {
        var vh = window.innerHeight;
        /* (A) hero parallax + cue fade */
        var hp = easeOut(clamp01((window.scrollY || 0) / (vh * 0.7)));
        heroKids.forEach(function(el) { el.style.translate = '0 ' + (-(hp * 46)).toFixed(1) + 'px'; });
        if (hero) hero.classList.toggle('cue-gone', hp > 0.3);
        infos.forEach(function(info) {
            var r = info.p.getBoundingClientRect();
            if (r.bottom <= 0 || r.top >= vh) return;   /* ยังไม่ถึง / ผ่านไปแล้ว → เก็บสภาพล่าสุด */
            var pr = easeOut(clamp01((vh - r.top) / (vh * 0.6)));       /* 0 = เพิ่งขอบล่าง, 1 = ขึ้นบนกลางจอ */
            info.p.style.opacity = (0.15 + 0.85 * pr).toFixed(3);
            info.p.style.transform = 'scale(' + (0.985 + 0.015 * pr).toFixed(4) + ')';
            info.kids.forEach(function(k, i) {
                var kp = easeOut(clamp01((pr - 0.06 - i * 0.13) * 1.6)); /* cascade ทีละชิ้น */
                k.style.opacity = kp.toFixed(3);
                k.style.transform = 'translateY(' + ((1 - kp) * 22).toFixed(1) + 'px)';
            });
            if (info.vis) info.vis.style.opacity = clamp01((pr - 0.2) * 1.45).toFixed(3);  /* ภาพประกอบโหลดทีหลัง */
            /* (B) mock เลเยอร์ทีละชั้น: translate property ไม่แตะ transform เดิมของแต่ละชิ้น */
            var vp = easeOut(clamp01((pr - 0.2) * 1.45));
            info.visKids.forEach(function(v, i) {
                var q = easeOut(clamp01((vp - i * 0.15) * 1.55));
                v.style.opacity = (0.25 + 0.75 * q).toFixed(3);
                v.style.translate = '0 ' + ((1 - q) * 16).toFixed(1) + 'px';
            });
            /* ── ลูกเล่นข้อความ ── */
            // (a) หัวข้อแยกคำ cascade
            info.words.forEach(function(w, i) {
                var q = easeOut(clamp01((pr - 0.12 - i * 0.09) * 1.55));
                w.style.opacity = q.toFixed(3);
                w.style.transform = 'translateY(' + ((1 - q) * 12).toFixed(1) + 'px)';
            });
            // (b) คำ gradient ลากเปิดซ้าย→ขวา
            if (info.mark) {
                var mp = easeOut(clamp01((pr - 0.26) * 1.6));
                info.mark.style.clipPath = 'inset(0 ' + ((1 - mp) * 100).toFixed(1) + '% 0 0)';
            }
            // (c) typewriter kicker: พิมพ์ทีละตัวตามการเลื่อน + caret วิ่งระหว่างพิมพ์
            if (info.typeEl) {
                var n = Math.round(clamp01((pr - 0.02) * 1.2) * info.units.length);
                if (n !== info.lastN) {
                    info.lastN = n;
                    info.typeEl.textContent = info.units.slice(0, n).join('');
                    info.typeEl.classList.toggle('typing', n < info.full.length);
                }
            }
        });
    }
    var ticking = false;
    function onScroll() {
        if (ticking) return;
        ticking = true;
        requestAnimationFrame(function() { update(); ticking = false; });
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    window.addEventListener('resize', onScroll, { passive: true });
    [300, 900].forEach(function(ms) { setTimeout(function() { update(); }, ms); });
    update();

    var tk = new IntersectionObserver(function(entries) {
        entries.forEach(function(en) {
            if (!en.isIntersecting) return;
            var i = parseInt(en.target.dataset.dot, 10) || 0;
            dots.forEach(function(d, j) { d.classList.toggle('active', j === i); });
        });
    }, { threshold: 0.5 });
    panels.forEach(function(p) { tk.observe(p); });

    dots.forEach(function(d) {
        d.addEventListener('click', function() {
            var p = panels[parseInt(d.dataset.dotnav, 10) || 0];
            if (p) p.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Home') {
            document.getElementById('aboutTop').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' });
        }
    });
})();
</script>
@endsection
