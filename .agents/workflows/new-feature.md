---
description: สร้าง Laravel feature ใหม่ครบชุด (Migration, Model, Repository, Controller, Resource, Test) ตาม conventions
---

เมื่อผู้ใช้ระบุชื่อ feature และ requirements ให้ทำตามขั้นตอนนี้:

## ขั้นตอน

### 1. อ่าน Skill ก่อน
อ่าน `.agents/skills/laravel-php/SKILL.md` ทั้งหมด

### 2. สร้าง Implementation Plan
แสดง plan ต่อไปนี้และรอ approval:
- ไฟล์ที่จะสร้างทั้งหมด
- Database schema (columns, indexes, foreign keys)
- API endpoints (method, path, middleware)
- Broadcasting events (ถ้ามี)

### 3. สร้างไฟล์ตามลำดับ
สร้างแบบ parallel agents:

**Agent 1 — Database:**
- Migration (ตาม section 6 ของ SKILL.md)
- Model พร้อม relationships, casts, scopes
- Factory + Seeder

**Agent 2 — Business Logic:**
- Repository (implements Interface)
- Form Requests (Store + Update)
- API Resource

**Agent 3 — HTTP Layer:**
- Controller (บาง, ใช้ Repository)
- Routes (api.php + channels.php ถ้ามี broadcast)
- Policy

**Agent 4 — Tests:**
- Feature Test ครอบคลุมทุก endpoint
- ทดสอบทั้ง happy path และ error cases

### 4. รัน /laravel-check
ตรวจสอบทุกไฟล์ที่สร้างด้วย `/laravel-check`

### 5. รัน Tests
```bash
php artisan test --filter=[FeatureName]
```
แสดง output และแก้ไขถ้า test fail
