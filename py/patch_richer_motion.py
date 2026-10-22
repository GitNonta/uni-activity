# -*- coding: utf-8 -*-
"""Motion v3 (richer, ยัง "ทับไม่ได้"):
- Cascade: kp-num -> icon pop (scale+rotate) -> title -> desc ทีละจังหวะ
- Icon pop: scale .55 + rotate -8deg -> ปรากฏด้วยความมันส์
- Hero bloom: eyebrow/title/sub เฟดบลูม scale .94 (ไม่แปลตำแหน่ง)
- Bob float: ทั้ง visual ลอยขึ้นลง ±5px ด้วย CSS `translate` property (ไม่ชน transform ของ parallax แต่อย่างใด)
- Dot nav: active วงแหวนนุ่ม
- Guard: html.js-anim ครอบทุกสถานะซ่อน เพื่อไม่ให้เนื้อหาหายถ้า JS/IO ไม่จบ
Patch SRC -> copy DST
"""
import io, shutil

P = "/root/uni-activity/resources/views/about.blade.php"
DST = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"

EDITS = [
    # 1) panel + cascade
    (
        "        opacity: 0; transform: scale(.97);   /* zoom เข้าจอเท่านั้น — ไม่มี translateY ไม่ทับ panel ข้างเคียง */\n"
        "        transition: opacity .8s cubic-bezier(.22,1,.36,1), transform .8s cubic-bezier(.22,1,.36,1);\n"
        "    }\n"
        "    .keynote-panel.in { opacity: 1; transform: translateY(0) scale(1); }",
        "        --k-anim: cubic-bezier(.2,.85,.28,1);\n"
        "        transition: opacity .8s cubic-bezier(.22,1,.36,1), transform .8s cubic-bezier(.22,1,.36,1);\n"
        "    }\n"
        "    html.js-anim .keynote-panel { opacity: 0; transform: scale(.97); }   /* zoom เท่านั้น — ไม่มี translateY */\n"
        "    .keynote-panel.in { opacity: 1; transform: none; }\n"
        "    /* cascade ตามชั้นความสำคัญ */\n"
        "    html.js-anim .kp-copy > * { opacity: 0; transform: translateY(20px); transition: opacity .7s var(--k-anim), transform .7s var(--k-anim); }\n"
        "    html.js-anim .kp-icon   { transform: scale(.55) rotate(-8deg); }   /* pop */\n"
        "    html.js-anim .kp-num    { transition-delay: .05s }\n"
        "    html.js-anim .kp-icon   { transition-delay: .12s }\n"
        "    html.js-anim .kp-title  { transition-delay: .2s }\n"
        "    html.js-anim .kp-desc   { transition-delay: .28s }\n"
        "    html.js-anim .keynote-panel.in .kp-copy > * { opacity: 1; transform: none; }\n"
        "    html.js-anim .kp-visual { opacity: 0; transition: opacity .9s var(--k-anim) .3s; }\n"
        "    html.js-anim .keynote-panel.in .kp-visual { opacity: 1; }",
    ),
    # 2) hero bloom (กลุ่มเดียว, เริ่มทันทีที่โหลด ไม่ต้องจ้อง IO)
    (
        "    .kh-cue { position: absolute; bottom: 2.4rem; animation: kh-bounce 1.8s ease-in-out infinite; }",
        "    .kh-cue { position: absolute; bottom: 2.4rem; animation: kh-bounce 1.8s ease-in-out infinite, kh-in 1s cubic-bezier(.2,.85,.28,1) .5s both; }\n"
        "    .kh-eyebrow, .kh-title, .kh-sub { animation: kh-in 1s cubic-bezier(.2,.85,.28,1) both; }\n"
        "    .kh-title { animation-delay: .12s; }\n"
        "    .kh-sub   { animation-delay: .26s; }\n"
        "    @keyframes kh-in { from { opacity: 0; transform: scale(.94); } to { opacity: 1; transform: none; } }",
    ),
    # 3) bob float มีชีวิต (translate property ไม่ชน transform ของ parallax)
    (
        "    /* ── Visual mocks (pure CSS/SVG) ── */",
        "    /* ── Visual mocks (pure CSS/SVG) ── */\n"
        "    @keyframes vk-bob { from { translate: 0 4px; } to { translate: 0 -5px; } }\n"
        "    .kp-visual { animation: vk-bob 6.5s ease-in-out infinite alternate; }",
    ),
    # 4) dot ring
    (
        "    .about-dnav.active { background: #ea580c; transform: scale(1.45); }",
        "    .about-dnav.active { background: #ea580c; transform: scale(1.45); box-shadow: 0 0 0 5px rgba(234,88,12,.16); }",
    ),
    # 5) reduced-motion: ครอบทุกอย่างใหม่
    (
        "    @media (prefers-reduced-motion: reduce) {\n"
        "        .keynote-panel { opacity: 1 !important; transform: none !important; transition: none !important; }\n"
        "        .kh-cue { animation: none !important; } .vk-caret { animation: none !important; }\n"
        "    }",
        "    @media (prefers-reduced-motion: reduce) {\n"
        "        html.js-anim .keynote-panel, html.js-anim .kp-copy > *, html.js-anim .kp-visual { opacity: 1 !important; transform: none !important; transition: none !important; animation: none !important; }\n"
        "        .kh-cue, .kh-eyebrow, .kh-title, .kh-sub { animation: none !important; opacity: 1 !important; transform: none !important; }\n"
        "        .kp-visual { animation: none !important; }\n"
        "        .vk-caret { animation: none !important; }\n"
        "    }",
    ),
    # 6) script: js-anim guard + IO presence
    (
        "    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;\n"
        "\n"
        "    var io = new IntersectionObserver(function(entries) {",
        "    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;\n"
        "    if (!('IntersectionObserver' in window)) return;   /* ไม่มี IO = เนื้อหาปกติทั้งหมด */\n"
        "    document.documentElement.classList.add('js-anim');  /* ซ่อนเชิงอนิเมชันเฉพาะเมื่อ JS พร้อม */\n"
        "\n"
        "    var io = new IntersectionObserver(function(entries) {",
    ),
]

with io.open(P, encoding="utf-8") as f:
    s = f.read()
if "html.js-anim .kp-icon   { transform: scale(.55) rotate(-8deg); }" in s:
    print("SKIP (already patched)")
else:
    for old, new in EDITS:
        assert old in s, "NOT FOUND: %r" % old[:70]
        s = s.replace(old, new, 1)
    with io.open(P, "w", encoding="utf-8") as f:
        f.write(s)
shutil.copyfile(P, DST)
print("patched + synced")
print("ALL OK")
