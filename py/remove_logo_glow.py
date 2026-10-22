# -*- coding: utf-8 -*-
"""ลบวงกลมสีส้มที่เหลือ: .kh-logo-glow (วงแสงส้มหลังโลโก้)
- ลบ markup <span class="kh-logo-glow">, ลบ CSS .kh-logo-glow block, ลบอ้างอิง reduced-motion
- โลโก้ยังมี bloom/float/shine ครบ (แค่ไม่มีวงแสงส้มหลังฉากแล้ว)
Patch SRC -> copy DST
"""
import io, shutil

P = "/root/uni-activity/resources/views/about.blade.php"
D = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"

EDITS = [
    (
        '        <span class="kh-logo-glow"></span>\n',
        "",
    ),
    (
        "    .kh-logo-glow {\n"
        "        position: absolute; inset: -24%; border-radius: 999px; pointer-events: none;\n"
        "        background: radial-gradient(circle at 50% 45%, rgba(234,88,12,.34), rgba(249,115,22,.12) 55%, transparent 72%);\n"
        "        animation: logo-glow 3.8s ease-in-out infinite alternate; filter: blur(6px);\n"
        "    }\n"
        "    .kh-logo {",
        "    .kh-logo {",
    ),
    (
        "    @keyframes logo-shine { 0%, 58% { transform: translateX(-130%); } 86%, 100% { transform: translateX(130%); } }\n"
        "    @keyframes logo-glow { from { opacity: .55; } to { opacity: 1; } }",
        "    @keyframes logo-shine { 0%, 58% { transform: translateX(-130%); } 86%, 100% { transform: translateX(130%); } }",
    ),
    (
        "        .kh-logo-wrap, .kh-logo, .kh-logo-glow, .kh-logo::after { animation: none !important; opacity: 1 !important; transform: none !important; }",
        "        .kh-logo-wrap, .kh-logo, .kh-logo::after { animation: none !important; opacity: 1 !important; transform: none !important; }",
    ),
]

with io.open(P, encoding="utf-8") as f:
    s = f.read()
if 'class="kh-logo-glow"' in s or s.count(".kh-logo-glow") > 0:
    for old, new in EDITS:
        if old in s:
            s = s.replace(old, new, 1)
    # กันเหลือ (markup อาจถูกลบคนอื่นไปก่อน)
    s = s.replace(".kh-logo-glow", "") if False else s
    with io.open(P, "w", encoding="utf-8") as f:
        f.write(s)
    print("patched")
else:
    print("SKIP (no kh-logo-glow found)")
residual = "kh-logo-glow"
with io.open(P, encoding="utf-8") as f:
    s = f.read()
print("residual refs:", s.count(residual))
shutil.copyfile(P, D)
print("ALL OK")
