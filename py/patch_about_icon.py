# -*- coding: utf-8 -*-
"""Patch icon info ข้างปุ่ม TH/EN ลงแอปที่ frankenphp serve จริง (DST)
และแก้บรรทัดที่ถูกยุบรวมใน SRC (`}        .navbar-right...a.active {` ให้แยกบรรทัด)
"""
import io

DST = "/data/data/com.termux/files/home/uni-activity"
SRC = "/root/uni-activity"


def patch(path, edits):
    with io.open(path, encoding="utf-8") as f:
        s = f.read()
    if "about-info-link" in s:
        print("SKIP (already patched):", path)
        return
    for old, new in edits:
        assert old in s, "NOT FOUND in %s: %r" % (path, old[:70])
        s = s.replace(old, new, 1)
    with io.open(path, "w", encoding="utf-8") as f:
        f.write(s)
    print("patched:", path)


ICON_NAV = (
    "\n"
    "                <a href=\"{{ route('about') }}\" class=\"about-info-link\"\n"
    "                   style=\"display:inline-flex; align-items:center; justify-content:center; width:1.6rem; height:1.6rem; margin-left:.25rem; border-radius:999px; color:#64748b; text-decoration:none; transition:background .2s, color .2s;\"\n"
    "                   title=\"{{ __('เกี่ยวกับ UNI Activity') }}\" aria-label=\"{{ __('เกี่ยวกับ UNI Activity') }}\">\n"
    "                    <svg width=\"17\" height=\"17\" style=\"width:17px;height:17px;\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M12 16v-4m0-4h.01\"/></svg>\n"
    "                </a>\n"
)

CSS_GUEST = (
    "    .navbar-right.lang-switcher .about-info-link {\n"
    "        width:1.55rem !important;\n"
    "        height:1.55rem !important;\n"
    "        margin-left:.15rem;\n"
    "    }\n"
    "    .navbar-right.lang-switcher .about-info-link:hover {\n"
    "        background:#ea580c;\n"
    "        color:#fff !important;\n"
    "    }\n"
)

ICON_SHEET = (
    "\n"
    "            <a href=\"{{ route('about') }}\" class=\"about-info-link\"\n"
    "               style=\"display:inline-flex; align-items:center; justify-content:center; width:1.7rem; height:1.7rem; margin-left:.3rem; border-radius:999px; color:#64748b; text-decoration:none; transition:background .2s, color .2s;\"\n"
    "               title=\"{{ __('เกี่ยวกับ UNI Activity') }}\" aria-label=\"{{ __('เกี่ยวกับ UNI Activity') }}\">\n"
    "                <svg width=\"18\" height=\"18\" style=\"width:18px;height:18px;\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M12 16v-4m0-4h.01\"/></svg>\n"
    "            </a>\n"
)

ICON_STAFF = (
    "\n"
    "        <a href=\"{{ route('about') }}\"\n"
    "           style=\"display:inline-flex; align-items:center; justify-content:center; width:1.55rem; height:1.55rem; margin-left:.25rem; border-radius:999px; background:transparent; color:#64748b; text-decoration:none; transition:background .2s, color .2s;\"\n"
    "           title=\"{{ __('เกี่ยวกับ UNI Activity') }}\" aria-label=\"{{ __('เกี่ยวกับ UNI Activity') }}\" onmouseover=\"this.style.background='#ea580c';this.style.color='#fff';\" onmouseout=\"this.style.background='transparent';this.style.color='#64748b';\">\n"
    "            <svg width=\"16\" height=\"16\" style=\"width:16px;height:16px;\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\" viewBox=\"0 0 24 24\"><circle cx=\"12\" cy=\"12\" r=\"10\"/><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M12 16v-4m0-4h.01\"/></svg>\n"
    "        </a>\n"
)

# ── 1) DST layouts/app.blade.php: 3 จุดเดียวกับที่ทำใน SRC ──
APP_NAV_EN = (
    "                <a href=\"{{ route('lang.switcher', 'en') }}\"\n"
    "                   class=\"{{ app()->getLocale() === 'en' ? 'active' : '' }}\"\n"
    "                   style=\"text-decoration:none; {{ app()->getLocale() === 'en' ? 'font-weight:700; color:var(--primary, #2563eb);' : 'color:inherit;' }}\">EN</a>\n"
    "            </div>"
)
APP_NAV_NEW = ICON_NAV.join([
    "                <a href=\"{{ route('lang.switcher', 'en') }}\"\n"
    "                   class=\"{{ app()->getLocale() === 'en' ? 'active' : '' }}\"\n"
    "                   style=\"text-decoration:none; {{ app()->getLocale() === 'en' ? 'font-weight:700; color:var(--primary, #2563eb);' : 'color:inherit;' }}\">EN</a>\n",
    "            </div>",
])

GUEST_ACTIVE = (
    "    .navbar-right.lang-switcher a.active {\n"
    "        background:#ea580c !important;\n"
    "        color:#fff !important;\n"
    "        font-weight:700;\n"
    "    }\n"
    "    </style>"
)
GUEST_ACTIVE_NEW = (
    "        .navbar-right.lang-switcher a.active {\n"
    "        background:#ea580c !important;\n"
    "        color:#fff !important;\n"
    "        font-weight:700;\n"
    "    }\n"
    + CSS_GUEST
    + "    </style>"
)

SHEET_EN = (
    "            <a href=\"{{ route('lang.switcher', 'en') }}\"\n"
    "               class=\"{{ app()->getLocale() === 'en' ? 'active' : '' }}\"\n"
    "               style=\"text-decoration:none; {{ app()->getLocale() === 'en' ? 'font-weight:700; color:var(--primary, #2563eb);' : 'color:inherit;' }}\">EN</a>\n"
    "        </div>\n"
    "        @auth"
)
SHEET_EN_NEW = (
    "            <a href=\"{{ route('lang.switcher', 'en') }}\"\n"
    "               class=\"{{ app()->getLocale() === 'en' ? 'active' : '' }}\"\n"
    "               style=\"text-decoration:none; {{ app()->getLocale() === 'en' ? 'font-weight:700; color:var(--primary, #2563eb);' : 'color:inherit;' }}\">EN</a>\n"
    + ICON_SHEET
    + "        </div>\n"
    "        @auth"
)

patch(DST + "/resources/views/layouts/app.blade.php", [
    (APP_NAV_EN, APP_NAV_NEW),
    (GUEST_ACTIVE, GUEST_ACTIVE_NEW),
    (SHEET_EN, SHEET_EN_NEW),
])

# ── 2) DST staff-login.blade.php (รูปทรงของ DST ต่างจาก SRC ตรง attr ต่อท้าย EN) ──
STAFF_EN_DST = (
    "        <a href=\"{{ route('lang.switcher', 'en') }}\"\n"
    "           style=\"text-decoration:none; padding:.22rem .3rem; border-radius:999px; background:transparent; color:#64748b; font-weight:600; {{ app()->getLocale() === 'en' ? 'background:#ea580c !important; color:#fff !important;' : '' }}\">EN</a>\n"
    "    </div>"
)
STAFF_EN_DST_NEW = (
    "        <a href=\"{{ route('lang.switcher', 'en') }}\"\n"
    "           style=\"text-decoration:none; padding:.22rem .3rem; border-radius:999px; background:transparent; color:#64748b; font-weight:600; {{ app()->getLocale() === 'en' ? 'background:#ea580c !important; color:#fff !important;' : '' }}\">EN</a>\n"
    + ICON_STAFF
    + "    </div>"
)
patch(DST + "/resources/views/auth/staff-login.blade.php", [
    (STAFF_EN_DST, STAFF_EN_DST_NEW),
])

# ── 3) SRC: แก้บรรทัดถูกยุบที่เกิดจากการแก้ก่อนหน้า ──
JAMMED = "}        .navbar-right.lang-switcher a.active {"
FIXED = "}\n        .navbar-right.lang-switcher a.active {"
patch(SRC + "/resources/views/layouts/app.blade.php", [(JAMMED, FIXED)])

print("ALL OK")
