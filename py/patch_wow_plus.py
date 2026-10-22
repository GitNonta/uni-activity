# -*- coding: utf-8 -*-
"""Wow++ package บน /about (ทั้งหมดผูกกับ progressive scroll driver เดิม):
A) Hero parallax: เนื้อหา hero ลอยขึ้นช้า (translate property — ไม่ชน animation kh-in) ตาม scrollY
   + ลูกศร cue จางหายเมื่อเลื่อนพ้น 30%
B) Mock layers: ชิ้นประกอบในแต่ละ visual (.vk) โผล่ทีละเลเยอร์ ด้วย translate(+opacity) ตามลำดับชั้น
   (translate property ไม่แตะ transform ของ CSS class เช่น rotate/ตำแหน่งเดิม)
C) Thai-aware typewriter: แยกหน่วยพิมพ์เป็น "พยางค์จริง" — สระลอย/วรรณยุกต์ (U+0E31,0E34-0E3A,0E47-0E4E)
   ติดไปกับตัวอักษรหลักของมัน ไม่มีการพิมพ์เดี่ยวแล้วลอยค้าง
Patch SRC -> copy DST
"""
import io, shutil

P = "/root/uni-activity/resources/views/about.blade.php"
D = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"

EDITS = [
    # ── A) CSS: cue fade เมื่อ hero.cue-gone (ท้ายก้อน hero เดิม: หลัง .kh-grad ก่อน .kh-cue) ──
    (
        "    .kh-cue { position: absolute; bottom: 2.4rem; animation: kh-bounce 1.8s ease-in-out infinite, kh-in 1s cubic-bezier(.2,.85,.28,1) .5s both; }",
        "    .kh-cue { position: absolute; bottom: 2.4rem; animation: kh-bounce 1.8s ease-in-out infinite, kh-in 1s cubic-bezier(.2,.85,.28,1) .5s both; transition: opacity .45s ease; }\n"
        "    .key-hero.cue-gone .kh-cue { opacity: 0 !important; }",
    ),
    # ── B/C helpers + hero setup: ต่อจาก easeOut ──
    (
        "    function easeOut(t) { return 1 - Math.pow(1 - t, 2.4); }",
        "    function easeOut(t) { return 1 - Math.pow(1 - t, 2.4); }\n"
        "    /* (C) แยกหน่วยพิมพ์เป็นพยางค์: สระลอย/วรรณยุกต์ไทยเกาะตัวหลัก */\n"
        "    var CMB = /[\\u0E31\\u0E34-\\u0E3A\\u0E47-\\u0E4E]/;\n"
        "    function thaiClusters(str) {\n"
        "        var out = [], i = 0;\n"
        "        while (i < str.length) {\n"
        "            var ch = str[i]; i++;\n"
        "            while (i < str.length && CMB.test(str[i])) { ch += str[i]; i++; }\n"
        "            out.push(ch);\n"
        "        }\n"
        "        return out;\n"
        "    }\n"
        "    /* (A) hero parallax: ขับด้วย translate property — ไม่ชน animation kh-in (transform) */\n"
        "    var hero = document.querySelector('.key-hero');\n"
        "    var heroKids = hero ? Array.prototype.slice.call(hero.querySelectorAll('.kh-logo-wrap, .kh-eyebrow, .kh-title, .kh-sub')) : [];",
    ),
    # ── infos: เพิ่ม visKids ──
    (
        "            vis: p.querySelector('.kp-visual') };",
        "            vis: p.querySelector('.kp-visual') };\n"
        "    });\n"
        "    infos.forEach(function(info) {\n"
        "        info.visKids = info.vis ? Array.prototype.slice.call(info.vis.querySelectorAll('.vk')) : [];\n"
        "        info.units = info.full ? thaiClusters(info.full) : [];",
    ),
    # ── driver: hero parallax + cue + mock layers (แทรกต้น update) ──
    (
        "    function update() {\n"
        "        var vh = window.innerHeight;",
        "    function update() {\n"
        "        var vh = window.innerHeight;\n"
        "        /* (A) hero parallax + cue fade */\n"
        "        var hp = easeOut(clamp01((window.scrollY || 0) / (vh * 0.7)));\n"
        "        heroKids.forEach(function(el) { el.style.translate = '0 ' + (-(hp * 46)).toFixed(1) + 'px'; });\n"
        "        if (hero) hero.classList.toggle('cue-gone', hp > 0.3);",
    ),
    # ── driver: แทรก mock layers หลังบรรทัด vis opacity ──
    (
        "            if (info.vis) info.vis.style.opacity = clamp01((pr - 0.2) * 1.45).toFixed(3);  /* ภาพประกอบโหลดทีหลัง */",
        "            if (info.vis) info.vis.style.opacity = clamp01((pr - 0.2) * 1.45).toFixed(3);  /* ภาพประกอบโหลดทีหลัง */\n"
        "            /* (B) mock เลเยอร์ทีละชั้น: translate property ไม่แตะ transform เดิมของแต่ละชิ้น */\n"
        "            var vp = easeOut(clamp01((pr - 0.2) * 1.45));\n"
        "            info.visKids.forEach(function(v, i) {\n"
        "                var q = easeOut(clamp01((vp - i * 0.15) * 1.55));\n"
        "                v.style.opacity = (0.25 + 0.75 * q).toFixed(3);\n"
        "                v.style.translate = '0 ' + ((1 - q) * 16).toFixed(1) + 'px';\n"
        "            });",
    ),
    # ── driver: typewriter แบบพยางค์ (แทน slice raw) ──
    (
        "                var n = Math.round(clamp01((pr - 0.02) * 1.2) * info.full.length);",
        "                var n = Math.round(clamp01((pr - 0.02) * 1.2) * info.units.length);",
    ),
    (
        "                    info.typeEl.textContent = info.full.slice(0, n);",
        "                    info.typeEl.textContent = info.units.slice(0, n).join('');",
    ),
]

with io.open(P, encoding="utf-8") as f:
    s = f.read()
if "hero parallax: ขับด้วย translate property" in s:
    print("SKIP (already patched)")
else:
    for old, new in EDITS:
        assert old in s, "NOT FOUND: %r" % old[:70]
        s = s.replace(old, new, 1)
    with io.open(P, "w", encoding="utf-8") as f:
        f.write(s)
shutil.copyfile(P, D)
print("patched + synced")
print("ALL OK")
