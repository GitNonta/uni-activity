# -*- coding: utf-8 -*-
"""Text flourish บน /about (ผูกกับ progressive scroll driver เดิม ไม่เพิ่มระบบใหม่):
1) Typewriter kicker (.kp-type) — ข้อความหมวดของแต่ละสไลด์พิมพ์ทีละตัวตามระยะเลื่อน + caret กะพริบ
   (no-JS / reduced-motion → โชว์เต็มทันที)
2) .kp-title แยกคำเป็น <span class="tw"> — แต่ละคำจาง/ลอยเข้าตาม stagger (~12px ในกรอบตัวเอง)
3) .kp-mark (คำ gradient สี accent) — ลากเปิดซ้าย→ขวาด้วย clip-path ตาม progress (contained)
Patch SRC -> copy DST
จุดแนบหมุด: ทั้งหมดยึดตามบรรทัดจริงในไฟล์ (ตรวจแล้วจากคอมมานด์ run ก่อนหน้า)
"""
import io, shutil

P = "/root/uni-activity/resources/views/about.blade.php"
D = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"

EDITS = [
    # ── 1) เพิ่ม field type ให้แต่ละสไลด์ (หมุด: บรรทัด num/accent/accent2 จริง) ──
    ("'num' => '01', 'accent' => '#ea580c', 'accent2' => '#1d4ed8',",
     "'num' => '01', 'accent' => '#ea580c', 'accent2' => '#1d4ed8', 'type' => $isEn ? 'Overview' : 'แนะนำระบบ',"),
    ("'num' => '02', 'accent' => '#f97316', 'accent2' => '#2563eb',",
     "'num' => '02', 'accent' => '#f97316', 'accent2' => '#2563eb', 'type' => $isEn ? 'Sign-in' : 'การเข้าสู่ระบบ',"),
    ("'num' => '03', 'accent' => '#ea580c', 'accent2' => '#0d9488',",
     "'num' => '03', 'accent' => '#ea580c', 'accent2' => '#0d9488', 'type' => $isEn ? 'Explore' : 'การค้นหา',"),
    ("'num' => '04', 'accent' => '#ea580c', 'accent2' => '#0d9488',",
     "'num' => '04', 'accent' => '#ea580c', 'accent2' => '#0d9488', 'type' => $isEn ? 'Attendance' : 'การเข้าร่วม',"),
    ("'num' => '05', 'accent' => '#f97316', 'accent2' => '#1d4ed8',",
     "'num' => '05', 'accent' => '#f97316', 'accent2' => '#1d4ed8', 'type' => $isEn ? 'Schedule' : 'ตารางกิจกรรม',"),
    ("'num' => '06', 'accent' => '#f97316', 'accent2' => '#7c3aed',",
     "'num' => '06', 'accent' => '#f97316', 'accent2' => '#7c3aed', 'type' => $isEn ? 'Progress' : 'ความคืบหน้า',"),
    # ── 2) markup: typewriter kicker หลัง kp-num ──
    ('<p class="kp-num">{{ $slide[\'num\'] }}</p>',
     '<p class="kp-num">{{ $slide[\'num\'] }}</p>\n'
     '                <p class="kp-type">{{ $slide[\'type\'] }}</p>'),
    # ── 3) CSS ก่อน visual mocks ──
    ("    /* ── Visual mocks (pure CSS/SVG) ── */",
     "    /* ── Text flourish: typewriter kicker + word cascade + gradient sweep ── */\n"
     "    .kp-type {\n"
     "        font-size: .78rem; letter-spacing: .18em; text-transform: uppercase;\n"
     "        font-weight: 700; margin: 0; min-height: 1.4em;\n"
     "        color: var(--acc2, #1d4ed8);\n"
     "    }\n"
     "    .kp-type::after {\n"
     "        content: '▍'; margin-left: 2px; opacity: 0;\n"
     "        animation: type-caret 1s steps(2) infinite;\n"
     "    }\n"
     "    .kp-type.typing::after { opacity: .75; }\n"
     "    @keyframes type-caret { 0%, 49% { opacity: .75; } 50%, 100% { opacity: .05; } }\n"
     "    .kp-title .tw { display: inline-block; }\n"
     "\n"
     "    /* ── Visual mocks (pure CSS/SVG) ── */"),
    # ── 4) JS: ขยาย infos (kids + type + title words + mark) ──
    (
        "    var infos = panels.map(function(p) {\n"
        "        return {\n"
        "            p: p,\n"
        "            kids: Array.prototype.slice.call(p.querySelectorAll('.kp-num, .kp-icon, .kp-title, .kp-desc')),\n"
        "            vis: p.querySelector('.kp-visual')\n"
        "        };\n"
        "    });",
        "    var infos = panels.map(function(p) {\n"
        "        var kids = Array.prototype.slice.call(p.querySelectorAll('.kp-num, .kp-type, .kp-icon, .kp-title, .kp-desc'));\n"
        "        // แยกหัวข้อหลักเป็นคำ (text node แรก) — คงแต่ละคำเป็น inline-block เพื่อ cascade\n"
        "        var title = p.querySelector('.kp-title');\n"
        "        var words = [];\n"
        "        if (title) {\n"
        "            var first = title.childNodes[0];\n"
        "            if (first && first.nodeType === 3) {\n"
        "                var frag = document.createDocumentFragment();\n"
        "                first.nodeValue.split(/(\\s+)/).forEach(function(seg) {\n"
        "                    if (!seg) return;\n"
        "                    if (/^\\s+$/.test(seg)) { frag.appendChild(document.createTextNode(seg)); }\n"
        "                    else {\n"
        "                        var sp = document.createElement('span');\n"
        "                        sp.className = 'tw'; sp.textContent = seg;\n"
        "                        frag.appendChild(sp); words.push(sp);\n"
        "                    }\n"
        "                });\n"
        "                title.replaceChild(frag, first);\n"
        "            }\n"
        "        }\n"
        "        var typeEl = p.querySelector('.kp-type');\n"
        "        return { p: p, kids: kids, words: words,\n"
        "            mark: p.querySelector('.kp-mark'),\n"
        "            typeEl: typeEl,\n"
        "            full: typeEl ? typeEl.textContent : '',\n"
        "            lastN: -1,\n"
        "            vis: p.querySelector('.kp-visual') };\n"
        "    });",
    ),
    # ── 5) driver: เพิ่มเอฟเฟกต์ใน update() ──
    (
        "            if (info.vis) info.vis.style.opacity = clamp01((pr - 0.2) * 1.45).toFixed(3);  /* ภาพประกอบโหลดทีหลัง */\n"
        "        });",
        "            if (info.vis) info.vis.style.opacity = clamp01((pr - 0.2) * 1.45).toFixed(3);  /* ภาพประกอบโหลดทีหลัง */\n"
        "            /* ── ลูกเล่นข้อความ ── */\n"
        "            // (a) หัวข้อแยกคำ cascade\n"
        "            info.words.forEach(function(w, i) {\n"
        "                var q = easeOut(clamp01((pr - 0.12 - i * 0.09) * 1.55));\n"
        "                w.style.opacity = q.toFixed(3);\n"
        "                w.style.transform = 'translateY(' + ((1 - q) * 12).toFixed(1) + 'px)';\n"
        "            });\n"
        "            // (b) คำ gradient ลากเปิดซ้าย→ขวา\n"
        "            if (info.mark) {\n"
        "                var mp = easeOut(clamp01((pr - 0.26) * 1.6));\n"
        "                info.mark.style.clipPath = 'inset(0 ' + ((1 - mp) * 100).toFixed(1) + '% 0 0)';\n"
        "            }\n"
        "            // (c) typewriter kicker: พิมพ์ทีละตัวตามการเลื่อน + caret วิ่งระหว่างพิมพ์\n"
        "            if (info.typeEl) {\n"
        "                var n = Math.round(clamp01((pr - 0.02) * 1.2) * info.full.length);\n"
        "                if (n !== info.lastN) {\n"
        "                    info.lastN = n;\n"
        "                    info.typeEl.textContent = info.full.slice(0, n);\n"
        "                    info.typeEl.classList.toggle('typing', n < info.full.length);\n"
        "                }\n"
        "            }\n"
        "        });",
    ),
    # ── 6) reduced-motion: ครอบชิ้นใหม่ (หมุดจากบรรทัดจริง: บรรทัดแรกในบล็อก + ท้ายเป็น .kh-cue) ──
    (
        "        .keynote-panel, .keynote-panel.in, .keynote-panel.in .kp-copy > *, .keynote-panel.in .kp-visual { animation: none !important; opacity: 1 !important; transform: none !important; }\n"
        "        .kh-cue, .kh-eyebrow, .kh-title, .kh-sub { animation: none !important; opacity: 1 !important; transform: none !important; }",
        "        .keynote-panel, .keynote-panel.in, .keynote-panel.in .kp-copy > *, .keynote-panel.in .kp-visual { animation: none !important; opacity: 1 !important; transform: none !important; }\n"
        "        .kp-title .tw { opacity: 1 !important; transform: none !important; }\n"
        "        .kp-mark { clip-path: none !important; }\n"
        "        .kp-type::after { animation: none !important; opacity: 0 !important; }\n"
        "        .kh-cue, .kh-eyebrow, .kh-title, .kh-sub { animation: none !important; opacity: 1 !important; transform: none !important; }",
    ),
]

with io.open(P, encoding="utf-8") as f:
    s = f.read()
if "Text flourish: typewriter kicker" in s:
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
