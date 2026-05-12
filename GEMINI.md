# GEMINI.md — Antigravity-Specific Rules
# ไฟล์นี้ override AGENTS.md สำหรับ Antigravity เท่านั้น

## Agent Behavior

- **วางแผนก่อนเสมอ** — ก่อนเขียนโค้ดทุกครั้ง สร้าง Implementation Plan ให้เห็นก่อน
- **อ่าน Skill ก่อนทำงาน** — ถ้างานเกี่ยวกับ Laravel ให้อ่าน `.agents/skills/laravel-php/SKILL.md` ก่อนเสมอ
- **บันทึก Knowledge Items** — เมื่อพบ pattern ใหม่หรือแก้ bug สำคัญ ให้บันทึกลง Knowledge Items
- **Parallel agents** — ใช้ parallel agents สำหรับงานที่แยกกันได้ เช่น สร้าง Controller + Test พร้อมกัน
- **ขอ approval** ก่อนรันคำสั่งที่ drop หรือ modify database

## Artifact Requirements

ทุก task ที่สร้างไฟล์ใหม่ต้องมี Artifact ประกอบด้วย:
1. รายการไฟล์ที่สร้าง/แก้ไข
2. เหตุผลที่เลือก pattern นั้น
3. สิ่งที่ต้อง test

## Workflow Integration

- เมื่อสร้างไฟล์ PHP ใหม่ → รัน `/laravel-check` workflow อัตโนมัติ
- เมื่อแก้ไข broadcasting → อ่าน `.agents/skills/laravel-php/references/reverb.md` ก่อน
- เมื่อสร้าง migration → ตรวจสอบ index ตาม SKILL.md section 6 ก่อน commit

## Knowledge Items to Save

บันทึก Knowledge Item เมื่อ:
- พบ bug ที่ซับซ้อนและวิธีแก้
- ตัดสินใจ architecture ที่สำคัญ
- เพิ่ม pattern ใหม่ที่ไม่อยู่ใน SKILL.md
