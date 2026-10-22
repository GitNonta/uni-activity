# -*- coding: utf-8 -*-
"""Fail-safe กันจอขาวถาวร (needle ตรงกับไฟล์จริง):
1) layouts (SRC+DST): scan() -> element ในจอ reveal-in ทันที + backstop 2s บังคับโชว์ทุก .reveal ที่ค้าง
2) about (SRC->copy DST): panel ในจอ 'in' ทันที + backstop 1.6s บังคับ 'in' ทุก panel
"""
import io, shutil

SRC_L = "/root/uni-activity/resources/views/layouts/app.blade.php"
DST_L = "/data/data/com.termux/files/home/uni-activity/resources/views/layouts/app.blade.php"
SRC_A = "/root/uni-activity/resources/views/about.blade.php"
DST_A = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"

L_EDITS = [
    (
        "        scan();\n"
        "        // rescan รับ content ที่ render หลัง (Alpine/fetch)\n"
        "        [420, 1100, 2600].forEach(function(ms) { setTimeout(scan, ms); });\n"
        "        window.addEventListener('load', scan);",
        "        scan();\n"
        "        // fail-safe: .reveal ที่ยังไม่โชว์ บังคับโชว์ใน 2 วิ กันจอว่างถาวร\n"
        "        setTimeout(function() {\n"
        "            document.querySelectorAll('.reveal:not(.reveal-in)').forEach(function(el) { el.classList.add('reveal-in'); });\n"
        "        }, 2000);\n"
        "        // rescan รับ content ที่ render หลัง (Alpine/fetch)\n"
        "        [420, 1100, 2600].forEach(function(ms) { setTimeout(scan, ms); });\n"
        "        window.addEventListener('load', scan);",
    ),
    (
        "            added.forEach(function(el) { io.observe(el); });\n"
        "        }",
        "            // element ที่อยู่ในจอแล้วตอน scan → โชว์ทันทีไม่ต้องรอ IO\n"
        "            added.forEach(function(el) {\n"
        "                var r = el.getBoundingClientRect();\n"
        "                if (r.top < window.innerHeight && r.bottom > 0) { el.classList.add('reveal-in'); io.unobserve(el); }\n"
        "                else io.observe(el);\n"
        "            });\n"
        "        }",
    ),
]

A_EDITS = [
    (
        "    panels.forEach(function(p) { io.observe(p); });",
        "    panels.forEach(function(p) {\n"
        "        var r = p.getBoundingClientRect();\n"
        "        if (r.top < window.innerHeight && r.bottom > 0) { p.classList.add('in'); io.unobserve(p); }\n"
        "        else io.observe(p);\n"
        "    });\n"
        "    // fail-safe: บังคับทุก panel โชว์เองใน 1.6 วิ กันจอว่างถาวร\n"
        "    setTimeout(function() {\n"
        "        panels.forEach(function(p) { p.classList.add('in'); });\n"
        "    }, 1600);",
    ),
]

for path, edits, guard_token in ((SRC_L, L_EDITS, "บังคับโชว์ใน 2 วิ"), (SRC_A, A_EDITS, "บังคับทุก panel โชว์เองใน 1.6 วิ")):
    with io.open(path, encoding="utf-8") as f:
        s = f.read()
    if guard_token in s:
        print("SKIP (already):", path)
        for _, new in edits:
            if new not in s:
                print("  !! partial state detected in", path)
        continue
    for old, new in edits:
        assert old in s, "NOT FOUND in %s: %r" % (path, old[:60])
        s = s.replace(old, new, 1)
    with io.open(path, "w", encoding="utf-8") as f:
        f.write(s)
    print("patched:", path)

shutil.copyfile(SRC_A, DST_A)
shutil.copyfile(SRC_L, DST_L)
print("synced both")
print("ALL OK")
