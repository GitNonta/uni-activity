# -*- coding: utf-8 -*-
"""หน้า /about จะไม่มีสถานะซ่อนตั้งต้นอีก (ปรับสถาปัตยกรรม motion + กันแคช):
- panel/copy/visual: ค่าเริ่มต้น "มองเห็น" — .in เพิ่มคลาสแล้วยิง one-shot animation (both fill แล้วจบ)
- ลบ rule html.js-anim {...opacity:0...} ทั้งชุด และหยุดเพิ่มคลาส js-anim
- reduced-motion ครอบใหม่
- routes/web.php: Route::view('/about') -> closure + Cache-Control: no-store (กันแคช HTML เก่า)
Patch SRC -> copy DST ทั้ง blade และ routes
"""
import io, shutil

A_SRC = "/root/uni-activity/resources/views/about.blade.php"
A_DST = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"
R_SRC = "/root/uni-activity/routes/web.php"
R_DST = "/data/data/com.termux/files/home/uni-activity/routes/web.php"

A_EDITS = [
    # 1) ลบ hidden-state ชุดเก่า -> one-shot animation เท่านั้น
    (
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
        "        --k-anim: cubic-bezier(.2,.85,.28,1);\n"
        "    }\n"
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
        "    @keyframes vis-in { from { opacity: 0; } to { opacity: 1; } }",
    ),
    # 2) reduced-motion ปรับชุดใหม่
    (
        "    @media (prefers-reduced-motion: reduce) {\n"
        "        html.js-anim .keynote-panel, html.js-anim .kp-copy > *, html.js-anim .kp-visual { opacity: 1 !important; transform: none !important; transition: none !important; animation: none !important; }\n"
        "        .kh-cue, .kh-eyebrow, .kh-title, .kh-sub { animation: none !important; opacity: 1 !important; transform: none !important; }\n"
        "        .kp-visual { animation: none !important; }\n"
        "        .vk-caret { animation: none !important; }\n"
        "    }",
        "    @media (prefers-reduced-motion: reduce) {\n"
        "        .keynote-panel, .keynote-panel.in, .keynote-panel.in .kp-copy > *, .keynote-panel.in .kp-visual { animation: none !important; opacity: 1 !important; transform: none !important; }\n"
        "        .kh-cue, .kh-eyebrow, .kh-title, .kh-sub { animation: none !important; opacity: 1 !important; transform: none !important; }\n"
        "        .kp-visual { animation: none !important; }\n"
        "        .vk-caret { animation: none !important; }\n"
        "    }",
    ),
    # 3) script: ไม่ต้องเพิ่ม js-anim แล้ว (คงตรวจ IO เฉย ๆ ผ่าน guard เดิม)
    (
        "    if (!('IntersectionObserver' in window)) return;   /* ไม่มี IO = เนื้อหาปกติทั้งหมด */\n"
        "    document.documentElement.classList.add('js-anim');  /* ซ่อนเชิงอนิเมชันเฉพาะเมื่อ JS พร้อม */\n",
        "    if (!('IntersectionObserver' in window)) return;   /* ไม่มี IO = เนื้อหาปกติทั้งหมด (ค่าเริ่มต้นมองเห็นอยู่แล้ว) */\n",
    ),
]

R_DGRAM = "Route::view('/about', 'about')->name('about');"
R_NEW = (
    "// no-store กันแคช HTML เก่า (เช่น เวอร์ชันก่อน fix) ทั้ง browser และ CDN\n"
    "Route::get('/about', function () {\n"
    "    return response()->view('about')->header('Cache-Control', 'no-store, max-age=0');\n"
    "})->name('about');"
)

with io.open(A_SRC, encoding="utf-8") as f:
    a = f.read()
for old, new in A_EDITS:
    assert old in a, "about NOT FOUND: %r" % old[:70]
    a = a.replace(old, new, 1)
with io.open(A_SRC, "w", encoding="utf-8") as f:
    f.write(a)

with io.open(R_SRC, encoding="utf-8") as f:
    r = f.read()
if R_DGRAM in r:
    r = r.replace(R_DGRAM, R_NEW, 1)
    with io.open(R_SRC, "w", encoding="utf-8") as f:
        f.write(r)
elif "no-store, max-age=0" in r:
    print("routes already no-store")
else:
    raise AssertionError("about route needle not found")

shutil.copyfile(A_SRC, A_DST)
shutil.copyfile(R_SRC, R_DST)
print("patched + synced (about + routes)")
print("ALL OK")
