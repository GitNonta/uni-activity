# -*- coding: utf-8 -*-
"""Progressive scroll reveal สำหรับ /about:
- ทุก element ค่าเริ่มต้นมองเห็น (ไม่มีสถานะซ่อนนิ่ง) — JS ขับ opacity/transform ด้วยระยะเลื่อนจริง:
  panel: opacity .15→1 + scale .985→1 ตามสัดส่วนที่เข้าจอ (easeOut)
  children: cascade ทีละชิ้น (num→icon→title→desc) translateY 22px→0 ตาม progress ประจำชิ้น
  visual: โผล่ที่หลัง (หลังข้อความโหลดครบ ~60%)
- ผู้ใช้เลื่อนเท่าไร โหลดเท่านั้น — เลื่อนช้าโหลดนุ่ม เลื่อนเร็วหนังสือก็ตามมาทัน
- ถ้าไม่มี JS / reduced-motion / IO ไม่มี: แสดงผลปกติทั้งหมด (กันจอขาวตามสถาปัตยกรรมเดิม)
Patch SRC -> copy DST
"""
import io, shutil

P = "/root/uni-activity/resources/views/about.blade.php"
D = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"

CSS_OLD = (
    "    /* ค่าเริ่มต้นคือ \"มองเห็น\" ทุกอย่าง — ไม่มีสถานะซ่อนนิ่ง จอจะขาวไม่ได้อีกต่อไปโดยโครงสร้าง */\n"
    "    .keynote-panel.in { animation: panel-in .85s cubic-bezier(.22,1,.36,1) both; }\n"
    "    @keyframes panel-in { from { opacity: 0; transform: scale(.97); } to { opacity: 1; transform: none; } }\n"
    "    /* cascade: ยิงครั้งเดียวเมื่อ .in (ทั้ง copy children) */\n"
    "    .keynote-panel.in .kp-copy > * { animation: copy-in .7s var(--k-anim) both; }\n"
    "    .keynote-panel.in .kp-num  { animation-delay: .05s }\n"
    "    .keynote-panel.in .kp-icon { animation-name: icon-in; animation-delay: .12s }\n"
    "    .keynote-panel.in .kp-title { animation-delay: .2s }\n"
    "    .keynote-panel.in .kp-desc { animation-delay: .28s }\n"
    "    @keyframes copy-in { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: none; } }\n"
    "    @keyframes icon-in { from { opacity: 0; transform: scale(.55) rotate(-8deg); } to { opacity: 1; transform: none; } }\n"
    "    .keynote-panel.in .kp-visual { animation: vis-in .9s var(--k-anim) .3s both; }\n"
    "    @keyframes vis-in { from { opacity: 0; } to { opacity: 1; } }"
)
CSS_NEW = (
    "    /* ── Progressive reveal: JS ขับ opacity/transform ตามระยะเลื่อน \"ค่อย ๆ โหลดเมื่อเลื่อนถึง\" ──\n"
    "       (ค่าเริ่มต้นมองเห็นทุกอย่าง — ไม่มีสถานะซ่อนนิ่ง, JS/IO พังก็แสดงผลปกติ) ── */\n"
    "    .kp-copy > *, .kp-visual { will-change: opacity, transform; }"
)

JS_OLD = (
    "    var io = new IntersectionObserver(function(entries) {\n"
    "        entries.forEach(function(en) {\n"
    "            if (en.isIntersecting) en.target.classList.add('in');\n"
    "        });\n"
    "    }, { threshold: 0.16 });\n"
    "    panels.forEach(function(p) {\n"
    "        var r = p.getBoundingClientRect();\n"
    "        if (r.top < window.innerHeight && r.bottom > 0) { p.classList.add('in'); io.unobserve(p); }\n"
    "        else io.observe(p);\n"
    "    });\n"
    "    // fail-safe กันจอขาว (ไม่แย่งอนิเมชัน): บังคับเฉพาะ panel ที่ 'อยู่ในจอ' —\n"
    "    // IO ปกติยิงก่อนเสมอ ผู้ใช้เลื่อนถึงแผงไหนก็ได้อนิเมชัน ณ จุดนั้นทันที\n"
    "    setInterval(function() {\n"
    "        panels.forEach(function(p) {\n"
    "            if (p.classList.contains('in')) return;\n"
    "            var r = p.getBoundingClientRect();\n"
    "            if (r.top < window.innerHeight && r.bottom > 0) p.classList.add('in');\n"
    "        });\n"
    "    }, 1600);\n"
)
JS_NEW = (
    "    /* ── Progressive reveal: ค่อย ๆ โหลดตามระยะเลื่อนมาถึง (ไม่ใช้คลาส .in ทีเดียววันตายตัวอีก) ── */\n"
    "    if (reduce) return;   /* ผู้ใช้ขอลด motion = แสดงผลคงที่ทั้งหมด */\n"
    "    var infos = panels.map(function(p) {\n"
    "        return {\n"
    "            p: p,\n"
    "            kids: Array.prototype.slice.call(p.querySelectorAll('.kp-num, .kp-icon, .kp-title, .kp-desc')),\n"
    "            vis: p.querySelector('.kp-visual')\n"
    "        };\n"
    "    });\n"
    "    function clamp01(t) { return Math.max(0, Math.min(1, t)); }\n"
    "    function easeOut(t) { return 1 - Math.pow(1 - t, 2.4); }\n"
    "    function update() {\n"
    "        var vh = window.innerHeight;\n"
    "        infos.forEach(function(info) {\n"
    "            var r = info.p.getBoundingClientRect();\n"
    "            if (r.bottom <= 0 || r.top >= vh) return;   /* ยังไม่ถึง / ผ่านไปแล้ว → เก็บสภาพล่าสุด */\n"
    "            var pr = easeOut(clamp01((vh - r.top) / (vh * 0.6)));       /* 0 = เพิ่งขอบล่าง, 1 = ขึ้นบนกลางจอ */\n"
    "            info.p.style.opacity = (0.15 + 0.85 * pr).toFixed(3);\n"
    "            info.p.style.transform = 'scale(' + (0.985 + 0.015 * pr).toFixed(4) + ')';\n"
    "            info.kids.forEach(function(k, i) {\n"
    "                var kp = easeOut(clamp01((pr - 0.06 - i * 0.13) * 1.6)); /* cascade ทีละชิ้น */\n"
    "                k.style.opacity = kp.toFixed(3);\n"
    "                k.style.transform = 'translateY(' + ((1 - kp) * 22).toFixed(1) + 'px)';\n"
    "            });\n"
    "            if (info.vis) info.vis.style.opacity = clamp01((pr - 0.2) * 1.45).toFixed(3);  /* ภาพประกอบโหลดทีหลัง */\n"
    "        });\n"
    "    }\n"
    "    var ticking = false;\n"
    "    function onScroll() {\n"
    "        if (ticking) return;\n"
    "        ticking = true;\n"
    "        requestAnimationFrame(function() { update(); ticking = false; });\n"
    "    }\n"
    "    window.addEventListener('scroll', onScroll, { passive: true });\n"
    "    window.addEventListener('resize', onScroll, { passive: true });\n"
    "    [300, 900].forEach(function(ms) { setTimeout(function() { update(); }, ms); });\n"
    "    update();\n"
)

with io.open(P, encoding="utf-8") as f:
    s = f.read()
for old, new in ((CSS_OLD, CSS_NEW), (JS_OLD, JS_NEW)):
    assert old in s, "NOT FOUND: %r" % old[:70]
    s = s.replace(old, new, 1)
with io.open(P, "w", encoding="utf-8") as f:
    f.write(s)
shutil.copyfile(P, D)
print("patched + synced")
print("ALL OK")
