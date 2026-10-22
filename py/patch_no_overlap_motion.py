# -*- coding: utf-8 -*-
"""ปิดท้าย: เอาอนิเมชันที่ "ลากตำแหน่ง" ออก เหลือแต่ zoom+fade (ไม่มีวันทับเนื้อหา)
1) layouts/app.blade.php (SRC+DST): reveal-up keyframes translateY(18px) -> scale(.975)
2) FX script: parallax clamp ±14px, progress bar z-index 60 -> 30
3) about.blade.php (SRC->copy DST): panel เข้าจอ scale-only, kh-cue pointer-events none,
   dotnav ซ่อน <=899px, cue bottom ปรับจอเล็ก
"""
import io, shutil

SRC_L = "/root/uni-activity/resources/views/layouts/app.blade.php"
DST_L = "/data/data/com.termux/files/home/uni-activity/resources/views/layouts/app.blade.php"
SRC_A = "/root/uni-activity/resources/views/about.blade.php"
DST_A = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"


def patch(path, edits, guard):
    with io.open(path, encoding="utf-8") as f:
        s = f.read()
    if guard in s and guard == "zoom(.975)":
        print("SKIP (already patched):", path)
        return s
    for old, new in edits:
        assert old in s, "NOT FOUND in %s: %r" % (path, old[:70])
        s = s.replace(old, new, 1)
    with io.open(path, "w", encoding="utf-8") as f:
        f.write(s)
    print("patched:", path)
    return s


REVEAL_EDITS = [
    (
        "@keyframes reveal-up {\n"
        "            from { opacity: 0; transform: translateY(18px); }\n"
        "            to   { opacity: 1; transform: translateY(0); }\n"
        "        }",
        "@keyframes reveal-up {\n"
        "            from { opacity: 0; transform: scale(.975); }  /* zoom+fade เท่านั้น ไม่ลากตำแหน่ง กันทับกล่องข้างเคียง */\n"
        "            to   { opacity: 1; transform: none; }\n"
        "        }",
    ),
    (
        "            background-image:linear-gradient(90deg,#ea580c,#f97316 55%,#2563eb);\n"
        "            transform:scaleX(0); transform-origin:0 50%; will-change:transform; opacity:.92;\n"
        "            box-shadow:0 0 8px rgba(234,88,12,.35);\n"
        "    ",
        "            background-image:linear-gradient(90deg,#ea580c,#f97316 55%,#2563eb);\n"
        "            transform:scaleX(0); transform-origin:0 50%; will-change:transform; opacity:.92;\n"
        "            box-shadow:0 0 8px rgba(234,88,12,.35);\n"
        "    ",
    ),
    (
        "            position:fixed; top:0; left:0; right:0; height:3px; z-index:60; pointer-events:none;",
        "            position:fixed; top:0; left:0; right:0; height:3px; z-index:30; pointer-events:none;",
    ),
    (
        "                        var off = (r.top + r.height / 2 - window.innerHeight / 2) * -p.speed;\n"
        "                        p.el.style.transform = 'translateY(' + off.toFixed(1) + 'px)';",
        "                        var off = (r.top + r.height / 2 - window.innerHeight / 2) * -p.speed;\n"
        "                        off = Math.max(-14, Math.min(14, off)); /* clamp กันลอยไปทับข้อความใกล้เคียง */\n"
        "                        p.el.style.transform = 'translateY(' + off.toFixed(1) + 'px)';",
    ),
]

ABOUT_EDITS = [
    (
        "        opacity: 0; transform: translateY(34px) scale(.985);\n"
        "        transition: opacity .8s cubic-bezier(.22,1,.36,1), transform .8s cubic-bezier(.22,1,.36,1);",
        "        opacity: 0; transform: scale(.97);   /* zoom เข้าจอเท่านั้น — ไม่มี translateY ไม่ทับ panel ข้างเคียง */\n"
        "        transition: opacity .8s cubic-bezier(.22,1,.36,1), transform .8s cubic-bezier(.22,1,.36,1);",
    ),
    (
        '<svg class="kh-cue" width="22" height="22"',
        '<svg class="kh-cue" pointer-events="none" style="pointer-events:none;" width="22" height="22"',
    ),
    (
        "@media (max-width: 700px) { .about-dotnav { display: none; } }",
        "@media (max-width: 899px) { .about-dotnav { display: none; } } /* ซ่อนลงจอเล็ก-กลาง กันทับ mock/ข้อความ */",
    ),
    (
        "    @media (max-width: 899px) {\n        .keynote-panel { min-height: 80vh; padding: 4.5rem 1.25rem 5.5rem; }",
        "    @media (max-width: 899px) {\n        .keynote-panel { min-height: 80vh; padding: 4.5rem 1.25rem 5.5rem; }\n        .kh-cue { bottom: 1.1rem; }",
    ),
]

GUARD_SRC_TOKEN = "zoom+fade เท่านั้น"

# layouts: ทั้ง SRC และ DST (แทน 3 ก้อน, ข้าม if already)
for p in (SRC_L, DST_L):
    with io.open(p, encoding="utf-8") as f:
        s = f.read()
    if GUARD_SRC_TOKEN in s:
        print("SKIP layout (already):", p)
        continue
    for old, new in REVEAL_EDITS:
        assert old in s, "layout NOT FOUND %s: %r" % (p, old[:60])
        s = s.replace(old, new, 1)
    with io.open(p, "w", encoding="utf-8") as f:
        f.write(s)
    print("layout patched:", p)

# about: SRC แล้ว copy DST
with io.open(SRC_A, encoding="utf-8") as f:
    s = f.read()
if "ไม่มี translateY ไม่ทับ panel" in s:
    print("SKIP about (already)")
else:
    for old, new in ABOUT_EDITS:
        assert old in s, "about NOT FOUND: %r" % old[:60]
        s = s.replace(old, new, 1)
    with io.open(SRC_A, "w", encoding="utf-8") as f:
        f.write(s)
    shutil.copyfile(SRC_A, DST_A)
    print("about patched + synced")
print("ALL OK")
