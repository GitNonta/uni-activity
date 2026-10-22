# -*- coding: utf-8 -*-
"""หน้า /about: ส้มเด่น + คู่สีตัดกัน + ตัวอักษรเด่นจัด
- hero ชื่อเรื่องดำจริง/ขาวจริง, ประโยคตัวเดินอ่านง่าย (opacity เพิ่มใกล้ 1)
- icon chip = สี accent เต็ม ตัวไอคอนขาว
- kp-num/kp-mark ยังเด่นด้วย accent
- accent คู่ประจำสไลด์: ส้มนำ + ตัดกัน (น้ำเงิน 1d4ed8 / teal 0d9488 / ม่วง 7c3aed)
- radial glow เพิ่มความเข้ม (16% -> 24%, opacity .5 -> .55)
Atomic replaces with asserts; patched SRC แล้ว copy ไป DST
"""
import io
import shutil

SRC = "/root/uni-activity/resources/views/about.blade.php"
DST = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"

REPL = [
    # ── Hero: ตัวอักษรใหญ่ชัดจัด ──
    ('.kh-title { font-size: clamp(2.9rem, 9vw, 6.2rem); line-height: 1.05; font-weight: 800; letter-spacing: -0.025em; margin: 0; color: #1d1d1f; }',
     '.kh-title { font-size: clamp(3rem, 9.4vw, 6.6rem); line-height: 1.04; font-weight: 800; letter-spacing: -0.028em; margin: 0; color: #0f0f10; }'),
    ('.kh-sub { font-size: clamp(1.25rem, 3.4vw, 2rem); font-weight: 600; margin: 0; letter-spacing: -0.01em; }',
     '.kh-sub { font-size: clamp(1.35rem, 3.7vw, 2.2rem); font-weight: 700; margin: 0; letter-spacing: -0.012em; color: #222226; }'),
    ('background: linear-gradient(92deg, #ea580c 8%, #f59e0b 45%, #2563eb 96%);',
     'background: linear-gradient(92deg, #ea580c 6%, #f97316 42%, #1d4ed8 100%);'),
    # dark theme hero ขาวจัด
    ('html[data-theme="dark"] .kh-title, html.dark .kh-title { color: #f5f5f7; }',
     'html[data-theme="dark"] .kh-title, html.dark .kh-title { color: #ffffff; }'),
    ('html[data-theme="dark"] .kh-sub, html.dark .kh-sub { color: #d1d1d6; }',
     'html[data-theme="dark"] .kh-sub, html.dark .kh-sub { color: #f5f5f7; font-weight: 700; }'),
    # ── Panel text: ลดจางลงเหลือน้อยที่สุด + ใหญ่ขึ้น ──
    ('.kp-title { font-size: clamp(1.85rem, 4.6vw, 2.95rem); font-weight: 800; letter-spacing: -0.022em; margin: 0; line-height: 1.12; }',
     '.kp-title { font-size: clamp(1.95rem, 5vw, 3.25rem); font-weight: 800; letter-spacing: -0.024em; margin: 0; line-height: 1.1; }'),
    ('.kp-desc { font-size: clamp(1.02rem, 2.4vw, 1.28rem); line-height: 1.68; margin: 0; }',
     '.kp-desc { font-size: clamp(1.08rem, 2.6vw, 1.36rem); line-height: 1.66; margin: 0; font-weight: 500; }'),
    ('.keynote-light .kp-desc { color: rgba(29,29,31,.72); }',
     '.keynote-light .kp-desc { color: rgba(15,15,16,.92); }'),
    ('.keynote-dark  .kp-desc { color: rgba(245,245,247,.72); }',
     '.keynote-dark  .kp-desc { color: rgba(255,255,255,.94); }'),
    # ── icon chip เต็มสี accent ตัว glyph ขาว (ทั้งธีมขาว/ดำ) ──
    ('.keynote-light .kp-icon { background: color-mix(in srgb, var(--acc, #ea580c) 11%, transparent); color: var(--acc, #ea580c); }',
     '.keynote-light .kp-icon { background: var(--acc, #ea580c); color: #ffffff; }'),
    ('.keynote-dark  .kp-icon { background: color-mix(in srgb, var(--acc, #2997ff) 16%, transparent); color: var(--acc, #2997ff); }',
     '.keynote-dark  .kp-icon { background: var(--acc, #f97316); color: #ffffff; }'),
    # kp-num เด่นขึ้น
    ('.kp-num { font-size: .85rem; letter-spacing: .18em; font-weight: 700; color: var(--acc, #ea580c); margin: 0; opacity: .85; }',
     '.kp-num { font-size: .9rem; letter-spacing: .2em; font-weight: 800; color: var(--acc, #ea580c); margin: 0; }'),
    # ── glow ส้มเด่นขึ้น ──
    ('background: radial-gradient(circle at 50% 50%, color-mix(in srgb, var(--acc, #ea580c) 16%, transparent), transparent 65%);',
     'background: radial-gradient(circle at 50% 50%, color-mix(in srgb, var(--acc, #ea580c) 24%, transparent), transparent 66%);'),
    ('pointer-events: none; opacity: .5;', 'pointer-events: none; opacity: .55;'),
    # ── accent คู่ประจำสไลด์: ส้มนำ + ตัดกัน ──
    ("'num' => '01', 'accent' => '#ea580c', 'accent2' => '#f59e0b',",
     "'num' => '01', 'accent' => '#ea580c', 'accent2' => '#1d4ed8',"),
    ("'num' => '02', 'accent' => '#2997ff', 'accent2' => '#7c3aed',",
     "'num' => '02', 'accent' => '#f97316', 'accent2' => '#2563eb',"),
    ("'num' => '03', 'accent' => '#7c3aed', 'accent2' => '#ec4899',",
     "'num' => '03', 'accent' => '#ea580c', 'accent2' => '#0d9488',"),
    ("'num' => '04', 'accent' => '#10b981', 'accent2' => '#2997ff',",
     "'num' => '04', 'accent' => '#ea580c', 'accent2' => '#0d9488',"),
    ("'num' => '05', 'accent' => '#f59e0b', 'accent2' => '#ea580c',",
     "'num' => '05', 'accent' => '#f97316', 'accent2' => '#1d4ed8',"),
    ("'num' => '06', 'accent' => '#ec4899', 'accent2' => '#7c3aed',",
     "'num' => '06', 'accent' => '#f97316', 'accent2' => '#7c3aed',"),
]

with io.open(SRC, encoding="utf-8") as f:
    s = f.read()
for old, new in REPL:
    assert old in s, "NOT FOUND: %r" % old[:64]
    s = s.replace(old, new, 1)
with io.open(SRC, "w", encoding="utf-8") as f:
    f.write(s)
print("src patched:", SRC)

shutil.copyfile(SRC, DST)
print("synced ->", DST)
print("ALL OK")
