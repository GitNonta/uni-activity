# -*- coding: utf-8 -*-
"""Animated logo showcase บน hero ของ /about:
- โลโก้จริง (logo.svg) บลูมเข้ามาแล้วลอยนุ่ม ๆ (translate property)
- shine sweep แสงวาบแนวทแยงผ่านลอโก้ วนทุก ~4.5s (Apple style)
- glow pulse วงแสงส้มเบาหลังโลโก้ (คนละชั้น ไม่ถูก crop)
- ลูกเล่น: จุด dotnav active มี pulse วงแหวนหายใจ
- ครอบ reduced-motion  + ปลอดภัยจากการทับ (transform/opacity/translate ล้วน ในกรอบตัวเอง)
"""
import io, shutil

A_SRC = "/root/uni-activity/resources/views/about.blade.php"
A_DST = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"

EDITS = [
    # 1) ใส่โลโก้ก่อน eyebrow ใน hero
    (
        '<section class="key-hero" id="aboutTop">\n    <p class="kh-eyebrow">',
        '<section class="key-hero" id="aboutTop">\n'
        '    <div class="kh-logo-wrap" aria-hidden="true">\n'
        '        <span class="kh-logo-glow"></span>\n'
        '        <span class="kh-logo"><img src="{{ asset(\'logo.svg\') }}" alt="" loading="eager"></span>\n'
        '    </div>\n'
        '    <p class="kh-eyebrow">',
    ),
    # 2) CSS ของ logo showcase + keyframes (เติมต่อท้ายก้อน kh-in เดิม)
    (
        "    @keyframes kh-in { from { opacity: 0; transform: scale(.94); } to { opacity: 1; transform: none; } }",
        "    @keyframes kh-in { from { opacity: 0; transform: scale(.94); } to { opacity: 1; transform: none; } }\n"
        "    /* ── Animated logo showcase ── */\n"
        "    .kh-logo-wrap { position: relative; width: 96px; height: 96px; margin-bottom: .5rem; animation: kh-in 1s cubic-bezier(.2,.85,.28,1) .05s both; }\n"
        "    .kh-logo-glow {\n"
        "        position: absolute; inset: -24%; border-radius: 999px; pointer-events: none;\n"
        "        background: radial-gradient(circle at 50% 45%, rgba(234,88,12,.34), rgba(249,115,22,.12) 55%, transparent 72%);\n"
        "        animation: logo-glow 3.8s ease-in-out infinite alternate; filter: blur(6px);\n"
        "    }\n"
        "    .kh-logo {\n"
        "        position: relative; display: block; width: 100%; height: 100%; border-radius: 22%; overflow: hidden;\n"
        "        box-shadow: 0 10px 28px rgba(15,23,42,.14);\n"
        "        animation: logo-float 5.5s ease-in-out 1s infinite alternate;\n"
        "    }\n"
        "    .kh-logo img { display: block; width: 100%; height: 100%; }\n"
        "    .kh-logo::after {\n"
        "        content: ''; position: absolute; inset: -35%; pointer-events: none;\n"
        "        background: linear-gradient(115deg, transparent 43%, rgba(255,255,255,.9) 50%, transparent 57%);\n"
        "        transform: translateX(-130%) rotate(0.001deg);\n"
        "        animation: logo-shine 4.6s ease-in-out 1.4s infinite;\n"
        "    }\n"
        "    @keyframes logo-float { from { translate: 0 3px; } to { translate: 0 -5px; } }\n"
        "    @keyframes logo-shine { 0%, 58% { transform: translateX(-130%); } 86%, 100% { transform: translateX(130%); } }\n"
        "    @keyframes logo-glow { from { opacity: .55; } to { opacity: 1; } }\n"
        "    html[data-theme=\"dark\"] .kh-logo { box-shadow: 0 12px 32px rgba(0,0,0,.5); }",
    ),
    # 3) dot pulse
    (
        "    .about-dnav.active { background: #ea580c; transform: scale(1.45); box-shadow: 0 0 0 5px rgba(234,88,12,.16); }",
        "    .about-dnav.active { background: #ea580c; transform: scale(1.45); box-shadow: 0 0 0 5px rgba(234,88,12,.16); animation: dot-pulse 2.6s ease-in-out infinite; }\n"
        "    @keyframes dot-pulse { 0%, 100% { box-shadow: 0 0 0 4px rgba(234,88,12,.14); } 50% { box-shadow: 0 0 0 9px rgba(234,88,12,.05); } }",
    ),
    # 4) reduced-motion ครอบสาชิ้นใหม่
    (
        "        .kp-visual { animation: none !important; }\n"
        "        .vk-caret { animation: none !important; }\n"
        "    }",
        "        .kp-visual { animation: none !important; }\n"
        "        .kh-logo-wrap, .kh-logo, .kh-logo-glow, .kh-logo::after { animation: none !important; opacity: 1 !important; transform: none !important; }\n"
        "        .about-dnav.active { animation: none !important; }\n"
        "        .vk-caret { animation: none !important; }\n"
        "    }",
    ),
]

with io.open(A_SRC, encoding="utf-8") as f:
    s = f.read()
if "Animated logo showcase" in s:
    print("SKIP (already patched)")
else:
    for old, new in EDITS:
        assert old in s, "NOT FOUND: %r" % old[:70]
        s = s.replace(old, new, 1)
    with io.open(A_SRC, "w", encoding="utf-8") as f:
        f.write(s)
shutil.copyfile(A_SRC, A_DST)
print("patched + synced")
print("ALL OK")
