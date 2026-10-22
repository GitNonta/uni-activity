# -*- coding: utf-8 -*-
"""Scroll-FX system (ต่อยอดจาก scroll-reveal):
1) Custom scrollbar สวย ๆ rounded gradient (webkit + Firefox)
2) Top progress bar โชว์ความคืบหน้าการเลื่อน (gradient, scaleX ผ่าน rAF)
3) Navbar รับเงาเมื่อเลื่อนแล้ว > 8px
4) Parallax: [data-parallax="factor"] เลื่อนต่างความเร็ว (ใช้ในหน้า /about)
- เคารพ prefers-reduced-motion (ปิด parallax/progress แต่เปิด scrollbar)
- rAF + passive listener, CSP nonce, idempotent marker "scrollfx-system"
Patch: layouts/app.blade.php (SRC+DST) + about.blade.php (SRC+DST)
"""
import io

MARKFX = "scrollfx-system"

FX_BLOCK = (
    "{{-- ── Scroll FX System: scrollbar+progress+navbar shadow+parallax (scrollfx-system) ── --}}\n"
    "    <style>\n"
    "        html.js-scrollfx { scrollbar-color:#ea580c transparent; scrollbar-width:thin; }\n"
    "        html.js-scrollfx ::-webkit-scrollbar { width:10px; height:10px; }\n"
    "        html.js-scrollfx ::-webkit-scrollbar-track { background:transparent; }\n"
    "        html.js-scrollfx ::-webkit-scrollbar-thumb {\n"
    "            border-radius:999px; border:2px solid rgba(0,0,0,0); background-clip:content-box;\n"
    "            background-color:#ea580c;\n"
    "            background-image:linear-gradient(180deg,#fb923c,#ea580c);\n"
    "        }\n"
    "        html.js-scrollfx ::-webkit-scrollbar-thumb:hover { background-image:linear-gradient(180deg,#ea580c,#c2410c); }\n"
    "        html[data-theme=\"dark\"].js-scrollfx ::-webkit-scrollbar-thumb,\n"
    "        html.dark.js-scrollfx ::-webkit-scrollbar-thumb { background-image:linear-gradient(180deg,#75757f,#3f3f46); }\n"
    "        #scroll-progress-bar {\n"
    "            position:fixed; top:0; left:0; right:0; height:3px; z-index:60; pointer-events:none;\n"
    "            background-image:linear-gradient(90deg,#ea580c,#f97316 55%,#2563eb);\n"
    "            transform:scaleX(0); transform-origin:0 50%; will-change:transform; opacity:.92;\n"
    "            box-shadow:0 0 8px rgba(234,88,12,.35);\n"
    "        }\n"
    "        html.js-scrollfx .navbar.scrolled { box-shadow:0 4px 16px rgba(15,23,42,.08); }\n"
    "        html[data-theme=\"dark\"].js-scrollfx .navbar.scrolled, html.dark.js-scrollfx .navbar.scrolled { box-shadow:0 4px 16px rgba(0,0,0,.35); }\n"
    "        [data-parallax] { will-change:transform; }\n"
    "    </style>\n"
    "    <script nonce=\"{{ request()->attributes->get('csp_nonce') }}\">\n"
    "    (function() {\n"
    "        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;\n"
    "        var docEl = document.documentElement;\n"
    "        docEl.classList.add('js-scrollfx');\n"
    "        var bar = document.createElement('div');\n"
    "        bar.id = 'scroll-progress-bar';\n"
    "        if (!reduce) document.body.appendChild(bar);\n"
    "        var navbar = document.querySelector('.navbar');\n"
    "        var paras = [];\n"
    "        function collect() {\n"
    "            paras = [];\n"
    "            document.querySelectorAll('[data-parallax]').forEach(function(el) {\n"
    "                if (el.dataset.parallaxDone === '1') return;\n"
    "                el.dataset.parallaxDone = '1';\n"
    "                paras.push({ el: el, speed: parseFloat(el.dataset.parallax) || 0.12 });\n"
    "            });\n"
    "        }\n"
    "        var ticking = false;\n"
    "        function onScroll() {\n"
    "            if (ticking) return;\n"
    "            ticking = true;\n"
    "            requestAnimationFrame(function() {\n"
    "                var y = window.scrollY || 0;\n"
    "                var max = Math.max(1, docEl.scrollHeight - window.innerHeight);\n"
    "                if (bar) bar.style.transform = 'scaleX(' + Math.min(1, y / max).toFixed(4) + ')';\n"
    "                if (navbar) navbar.classList.toggle('scrolled', y > 8);\n"
    "                if (!reduce && paras.length) {\n"
    "                    for (var i = 0; i < paras.length; i++) {\n"
    "                        var p = paras[i];\n"
    "                        var r = p.el.getBoundingClientRect();\n"
    "                        var off = (r.top + r.height / 2 - window.innerHeight / 2) * -p.speed;\n"
    "                        p.el.style.transform = 'translateY(' + off.toFixed(1) + 'px)';\n"
    "                    }\n"
    "                }\n"
    "                ticking = false;\n"
    "            });\n"
    "        }\n"
    "        collect(); onScroll();\n"
    "        window.addEventListener('scroll', onScroll, { passive: true });\n"
    "        window.addEventListener('resize', function() { collect(); onScroll(); }, { passive: true });\n"
    "        [650, 1600, 3200].forEach(function(ms) { setTimeout(function() { collect(); onScroll(); }, ms); });\n"
    "    })();\n"
    "    </script>\n"
)

NEEDLE = "\n    @yield('scripts')\n"

# about.blade.php: icon ของแต่ละสไลด์ให้ parallax เบา ๆ
ICON_OLD = '<span class="about-slide-icon" style="width:72px;'
ICON_NEW = '<span class="about-slide-icon" data-parallax="0.05" style="width:72px;'

DECOR_OLD = '<div class="about-card card"'
DECOR_NEW = (
    '<div aria-hidden="true" style="position:absolute; width:260px; height:260px; top:-70px; left:-90px; border-radius:999px; background:radial-gradient(circle at 40% 40%, rgba(234,88,12,.14), transparent 70%); filter:blur(2px); pointer-events:none;" data-parallax="0.16"></div>\n'
    '<div aria-hidden="true" style="position:absolute; width:220px; height:220px; bottom:-60px; right:-70px; border-radius:999px; background:radial-gradient(circle at 60% 60%, rgba(37,99,235,.12), transparent 70%); filter:blur(2px); pointer-events:none;" data-parallax="-0.10"></div>\n'
    '<div class="about-card card"'
)


def patch_layout(path):
    with io.open(path, encoding="utf-8") as f:
        s = f.read()
    if MARKFX in s:
        print("SKIP layout (already patched):", path)
        return
    assert s.count(NEEDLE) == 1, "needle count != 1 in %s (%d)" % (path, s.count(NEEDLE))
    s = s.replace(NEEDLE, "\n    " + FX_BLOCK + NEEDLE, 1)
    with io.open(path, "w", encoding="utf-8") as f:
        f.write(s)
    print("patched layout:", path)


def patch_about(path):
    with io.open(path, encoding="utf-8") as f:
        s = f.read()
    changed = []
    if ICON_OLD in s:
        s = s.replace(ICON_OLD, ICON_NEW, 1)
        changed.append("icon")
    if DECOR_OLD in s and '" about-card' not in s:
        s = s.replace(DECOR_OLD, DECOR_NEW, 1)
        changed.append("decor")
    if not changed:
        print("SKIP about (already patched or no match):", path)
        return
    with io.open(path, "w", encoding="utf-8") as f:
        f.write(s)
    print("patched about %s:" % ",".join(changed), path)


for p in (
    "/data/data/com.termux/files/home/uni-activity/resources/views/layouts/app.blade.php",
    "/root/uni-activity/resources/views/layouts/app.blade.php",
):
    patch_layout(p)

for p in (
    "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php",
    "/root/uni-activity/resources/views/about.blade.php",
):
    patch_about(p)

print("ALL OK")
