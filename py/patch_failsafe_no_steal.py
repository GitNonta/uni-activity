# -*- coding: utf-8 -*-
"""แก้ backstop "แย่งอนิเมชัน" — ให้บังคับเฉพาะ element ที่อยู่ในจอ (needle ตรงไฟล์จริง)"""
import io, shutil

SRC_L = "/root/uni-activity/resources/views/layouts/app.blade.php"
DST_L = "/data/data/com.termux/files/home/uni-activity/resources/views/layouts/app.blade.php"
SRC_A = "/root/uni-activity/resources/views/about.blade.php"
DST_A = "/data/data/com.termux/files/home/uni-activity/resources/views/about.blade.php"

L_GUARD = "ไม่แย่งอนิเมชัน): บังคับเฉพาะ .reveal"

with io.open(SRC_L, encoding="utf-8") as f:
    l = f.read()
if L_GUARD not in l:
    raise SystemExit("layout base not yet patched — run previous script first")
print("layout SRC already patched")

with io.open(SRC_A, encoding="utf-8") as f:
    a = f.read()
OLD = (
    "    // fail-safe: บังคับทุก panel โชว์เองใน 1.6 วิ กันจอว่างถาวร\n"
    "    setTimeout(function() {\n"
    "        panels.forEach(function(p) { p.classList.add('in'); });\n"
    "    }, 1600);"
)
NEW = (
    "    // fail-safe กันจอขาว (ไม่แย่งอนิเมชัน): บังคับเฉพาะ panel ที่ 'อยู่ในจอ' —\n"
    "    // IO ปกติยิงก่อนเสมอ ผู้ใช้เลื่อนถึงแผงไหนก็ได้อนิเมชัน ณ จุดนั้นทันที\n"
    "    setInterval(function() {\n"
    "        panels.forEach(function(p) {\n"
    "            if (p.classList.contains('in')) return;\n"
    "            var r = p.getBoundingClientRect();\n"
    "            if (r.top < window.innerHeight && r.bottom > 0) p.classList.add('in');\n"
    "        });\n"
    "    }, 1600);"
)
if "บังคับเฉพาะ panel ที่ 'อยู่ในจอ'" in a:
    print("about SRC already patched")
else:
    assert OLD in a, "about needle NOT FOUND"
    a = a.replace(OLD, NEW, 1)
    with io.open(SRC_A, "w", encoding="utf-8") as f:
        f.write(a)
    print("about patched")

shutil.copyfile(SRC_L, DST_L)
shutil.copyfile(SRC_A, DST_A)
print("synced both")
print("ALL OK")
