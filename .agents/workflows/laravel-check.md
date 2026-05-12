---
description: ตรวจสอบโค้ด Laravel ที่สร้างหรือแก้ไขล่าสุดว่าตรงตาม conventions ใน SKILL.md
---

ตรวจสอบไฟล์ PHP ที่เพิ่งสร้างหรือแก้ไขโดยใช้ checklist ต่อไปนี้:

## Checklist

### Basics
- [ ] มี `declare(strict_types=1);` บรรทัดแรก
- [ ] ทุก method มี return type
- [ ] ทุก parameter มี type hint
- [ ] import `use` statements ครบทุกตัวที่ใช้

### Architecture
- [ ] Controller ไม่มี DB query โดยตรง (ใช้ Repository)
- [ ] Validation อยู่ใน Form Request (ไม่ใช่ Controller)
- [ ] JSON response ใช้ API Resource

### Security
- [ ] ไม่มี hardcode credentials หรือ keys
- [ ] มี authorization check (Policy หรือ authorize())

### Database
- [ ] มี eager loading (`with()`) ก่อน loop
- [ ] DB writes อยู่ใน `DB::transaction()`

### Broadcasting (ถ้ามี)
- [ ] Event implement `ShouldBroadcast`
- [ ] Frontend ใช้ `import.meta.env` ไม่ใช่ hardcode
- [ ] `.listen()` มี dot นำหน้าชื่อ event

## รายงานผล

แสดงผลในรูปแบบ:
✅ ผ่าน: [รายการที่ผ่าน]
❌ ต้องแก้: [รายการที่ไม่ผ่าน พร้อมโค้ดที่แก้แล้ว]
