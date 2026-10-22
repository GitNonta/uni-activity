# -*- coding: utf-8 -*-
"""ลบวงกลม glow สีส้มหลังแต่ละแผง (keynote-panel::after radial accent) — ของแต่ละอื่นยังคงอยู่:
- โลโก้ (kh-logo::after shine, kh-logo-glow) — ยังอยู่ (ถ้ารุกรานเจอรุ่นนี้คือที่โลโก้แจ้งต่างบอกได้)
- tk-dash::after จุดเจาะบัตร QR — ของภายใน mock ยังคง
Patch SRC -> copy DST
"""
import io, shutil

P = "/root/uni-activity/resources/views/about.blade.php"
D = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"

EDITS = [
    (
        "    .keynote-light::after, .keynote-dark::after {\n"
        "        content: ''; position: absolute; width: 560px; height: 560px; border-radius: 999px;\n"
        "        pointer-events: none; opacity: .55;\n"
        "        background: radial-gradient(circle at 50% 50%, color-mix(in srgb, var(--acc, #ea580c) 24%, transparent), transparent 66%);\n"
        "        top: 50%; left: 50%; transform: translate(-50%, -50%); filter: blur(10px);\n"
        "    }\n"
        "    .kp-inner {",
        "    .kp-inner {",
    ),
    (
        "        .kp-visual .vk-float-badge, .kp-visual .vk-pin { right: 2%; }\n"
        "        .keynote-panel::after { width: 420px; height: 420px; }\n"
        "    }",
        "        .kp-visual .vk-float-badge, .kp-visual .vk-pin { right: 2%; }\n"
        "    }",
    ),
]

with io.open(P, encoding="utf-8") as f:
    s = f.read()
if ".keynote-light::after" not in s:
    print("SKIP (already removed)")
else:
    for old, new in EDITS:
        assert old in s, "NOT FOUND: %r" % old[:80]
        s = s.replace(old, new, 1)
    with io.open(P, "w", encoding="utf-8") as f:
        f.write(s)
shutil.copyfile(P, D)
print("patched + synced")
print("ALL OK")
