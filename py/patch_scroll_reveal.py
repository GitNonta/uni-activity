# -*- coding: utf-8 -*-
"""Scroll-reveal system: ก่อน @yield('scripts') ใน layouts/app.blade.php
- IntersectionObserver + class reveal/reveal-in
- stagger ต่อกลุ่มพ่อ-ลูก, เคารพ prefers-reduced-motion, fallback ถ้าไม่มี IO
- html.js-reveal guard ป้องกันเนื้อหาหายเมื่อ JS ผิดพลาด
Patch ทั้ง SRC (/root) และ DST (เครื่อง serve จริง) — idempotent ด้วย marker
"""
import io

SRC = "/root/uni-activity/resources/views/layouts/app.blade.php"
DST = "/data/data/com.termux/files/home/uni-activity/resources/views/layouts/app.blade.php"

MARK = "scroll-reveal-system"

BLOCK = (
    "{{-- ── Scroll Reveal System: การ์ด/บล็อกขึ้น-จางแบบนุ่มตอนเลื่อนถึง (MARK) ── --}}\n"
    "    <style>\n"
    "        html.js-reveal .reveal { opacity: 0; }\n"
    "        html.js-reveal .reveal.reveal-in {\n"
    "            animation: reveal-up .65s cubic-bezier(.22,1,.36,1) both;\n"
    "        }\n"
    "        @keyframes reveal-up {\n"
    "            from { opacity: 0; transform: translateY(18px); }\n"
    "            to   { opacity: 1; transform: translateY(0); }\n"
    "        }\n"
    "        @media (prefers-reduced-motion: reduce) {\n"
    "            html.js-reveal .reveal { opacity: 1 !important; transform: none !important; animation: none !important; }\n"
    "        }\n"
    "    </style>\n"
    "    <script nonce=\"{{ request()->attributes->get('csp_nonce') }}\">\n"
    "    (function() {\n"
    "        var docEl = document.documentElement;\n"
    "        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;\n"
    "        if (!('IntersectionObserver' in window)) return;\n"
    "        docEl.classList.add('js-reveal');\n"
    "        var SELECTOR = '.card, [data-reveal], .grid-3 > *, .grid-4 > *';\n"
    "        var io = new IntersectionObserver(function(entries) {\n"
    "            entries.forEach(function(en) {\n"
    "                if (!en.isIntersecting) return;\n"
    "                en.target.classList.add('reveal-in');\n"
    "                io.unobserve(en.target);\n"
    "            });\n"
    "        }, { rootMargin: '0px 0px -36px 0px', threshold: 0.06 });\n"
    "        function scan() {\n"
    "            var added = [];\n"
    "            document.querySelectorAll(SELECTOR).forEach(function(el) {\n"
    "                if (el.classList.contains('reveal')) return;\n"
    "                if (el.dataset.revealSkip === '1') return;\n"
    "                var st = getComputedStyle(el);\n"
    "                if (st.position === 'fixed' || st.position === 'sticky') return;\n"
    "                if (!el.offsetParent && st.display !== 'none') return; // อยู่ใต้ display:none ของพ่อ\n"
    "                el.classList.add('reveal');\n"
    "                added.push(el);\n"
    "            });\n"
    "            // stagger: ลูกที่ใช้พ่อคนเดียวกัน ทยอย delay 80ms/ชิ้น (จำกัด 6 จังหวะแรก)\n"
    "            var byParent = {};\n"
    "            added.forEach(function(el) {\n"
    "                var p = el.parentElement;\n"
    "                (byParent[p] = byParent[p] || []).push(el);\n"
    "            });\n"
    "            Object.keys(byParent).forEach(function(p) {\n"
    "                var group = byParent[p];\n"
    "                if (group.length < 2) return;\n"
    "                group.forEach(function(el, i) {\n"
    "                    el.style.animationDelay = (Math.min(i, 5) * 0.08) + 's';\n"
    "                });\n"
    "            });\n"
    "            added.forEach(function(el) { io.observe(el); });\n"
    "        }\n"
    "        scan();\n"
    "        // rescan รับ content ที่ render หลัง (Alpine/fetch)\n"
    "        [420, 1100, 2600].forEach(function(ms) { setTimeout(scan, ms); });\n"
    "        window.addEventListener('load', scan);\n"
    "    })();\n"
    "    </script>\n"
)

NEEDLE = "\n    @yield('scripts')\n"


def patch(path):
    with io.open(path, encoding="utf-8") as f:
        s = f.read()
    if MARK in s:
        print("SKIP (already patched):", path)
        return
    assert s.count(NEEDLE) == 1, "needle count != 1 in %s (%d)" % (path, s.count(NEEDLE))
    block = BLOCK.replace("(MARK)", "(" + MARK + ")")
    assert block != BLOCK, "marker not substituted"
    s = s.replace(NEEDLE, "\n    " + block + NEEDLE, 1)
    with io.open(path, "w", encoding="utf-8") as f:
        f.write(s)
    print("patched:", path)


for p in (DST, SRC):
    patch(p)
print("ALL OK")
