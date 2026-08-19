# Security Re-Scan Report — Live Verification

**Project:** uni-activity  
**Target:** `192.168.1.222`  
**ตรวจเมื่อ:** 2026-08-19  
**วิธีตรวจ:** ตรวจ source/config ใน Git และทดสอบจากเครื่องภายนอกผ่าน SSH, HTTP และ TCP  
**ขอบเขต:** รายงานนี้ไม่ใช่การรับรองความปลอดภัยทั้งหมด และไม่แสดงค่าความลับจากระบบ

## สรุปผล

| หมวด | ผลตรวจจริง |
|---|---|
| Laravel routes / rate limiting | ยืนยันจาก source แล้ว |
| Redis authentication | ผ่าน live test |
| Redis เปิดจาก LAN | ไม่เปิด, connection refused |
| PostgreSQL เปิดจาก LAN | ไม่เปิด, port 5432 ไม่รับจาก LAN |
| SSH hardening | ผ่าน: key-only, root login ปิด, MaxAuthTries 2 |
| Firewall | ยังยืนยันไม่ได้ เพราะ SSH user ไม่มีสิทธิ์อ่าน iptables |
| PHP version hiding | ไม่ผ่าน: live response ยังมี `X-Powered-By: PHP/8.5.9` |
| Admin login | ผิดปกติ: live response เป็น HTTP 500 |
| API unauthenticated behavior | มี auth แต่ตอบ 302 redirect ไป `/login` ไม่ใช่ 401 |
| Public service exposure | ไม่ผ่าน: 8001, 8082, 9999 และพอร์ตช่วง 500xx เปิดจาก LAN |

## ผลตรวจที่ผ่าน

### Redis

ตรวจจากเซิร์ฟเวอร์จริง:

- `127.0.0.1:6379` และ `127.0.0.1:6380` listen เฉพาะ localhost
- ขอ `PING` โดยไม่ authenticate ได้ `NOAUTH Authentication required.`
- authenticate แล้วได้ `PONG`
- จากเครื่องภายนอกเชื่อมต่อ `192.168.1.222:6379` ไม่ได้: `Connection refused`

**สถานะ: ผ่าน live verification**

### PostgreSQL

ตรวจจากเซิร์ฟเวอร์จริงและเครื่องภายนอก:

- server รายงาน `127.0.0.1:5432` เป็น listener
- `pg_isready -h 127.0.0.1 -p 5432` รายงาน `accepting connections`
- จากเครื่องภายนอก TCP ไปยัง `192.168.1.222:5432` ไม่สำเร็จ

**สถานะ: ผ่านเรื่องการไม่เปิด PostgreSQL สู่ LAN**

### SSH

ค่า runtime จาก `sshd -T`:

```text
MaxAuthTries 2
PermitRootLogin no
PasswordAuthentication no
maxstartups 3:30:10
persourcemaxstartups 4
```

**สถานะ: ผ่านตามค่าที่ตรวจได้**

### Laravel application controls

Source ยืนยันว่ามีมาตรการต่อไปนี้:

- `/api/map/locations` ใช้ `auth:sanctum`
- student login ใช้ `throttle:student-login`
- staff login ใช้ `throttle:staff-login`
- API group ใช้ `auth:sanctum` และ `throttle:api-general`
- admin login ใช้ `protect-admin`
- CSP ไม่มี `unsafe-inline` และ `unsafe-eval` ใน source
- มี HSTS, `X-Frame-Options`, `X-Content-Type-Options` และ Referrer-Policy ใน middleware
- Nginx มี `deny all` สำหรับ `/storage`
- `robots.txt` มี Disallow rules สำหรับ admin, API และ path สำคัญ

**สถานะ: ยืนยันได้จาก source; ยังไม่ถือว่าเป็น end-to-end pass ทุกข้อ**

## สิ่งที่ไม่ผ่านหรือยังต้องแก้

### 1. PHP version ยังรั่วจาก live service

HTTP response จาก `http://192.168.1.222:8000/` มี:

```text
Server: FrankenPHP Caddy
X-Powered-By: PHP/8.5.9
```

แม้ Dockerfile ใน Git จะเขียน `expose_php = Off` แต่ runtime ที่กำลังรันรายงาน `expose_php => On => On` และ live header ยังเปิดเผยเวอร์ชัน PHP อยู่ แสดงว่า deployment ปัจจุบันไม่ได้ใช้ image/config ล่าสุด หรือมี runtime configuration อื่น override ค่า

**ระดับ:** Medium  
**สถานะ:** ไม่ผ่าน live verification  
**แก้ไข:** ตรวจ image/container ที่ใช้งานจริง, `php --ini`, ค่า `expose_php` ใน runtime และ restart/rebuild service หลังแก้

### 2. Admin login ตอบ HTTP 500

จากเครื่องภายนอก:

```text
GET /admin/login -> 500 Internal Server Error
```

จึงยังยืนยันไม่ได้ว่า IP whitelist ทำงานถูกต้องหรือควรตอบ 403/200 ตาม configuration การตอบ 500 เป็น availability/configuration defect และควรตรวจ log ของ application ทันที

**ระดับ:** High (กระทบ admin access และทำให้ security control ตรวจไม่ได้)  
**สถานะ:** ไม่ผ่าน  
**แก้ไข:** ตรวจ `storage/logs/laravel.log`, exception ล่าสุด, view `errors.403`, environment ที่ deploy และค่า `ADMIN_IP_WHITELIST`

### 3. API unauthenticated request ตอบ 302 แทน 401

จากเครื่องภายนอก:

```text
GET /api/map/locations -> 302 Found
Location: https://192.168.1.222:8000/login
```

การ redirect แสดงว่า request ถูกบังคับผ่าน authentication แล้ว จึงไม่ใช่ public data leak แต่ API contract ไม่เหมาะกับ client เพราะ API ควรตอบ `401 Unauthorized` แทน redirect ไปหน้าเว็บ

**ระดับ:** Medium  
**สถานะ:** auth ทำงาน แต่ behavior ไม่ตรง API expectation  
**แก้ไข:** ตรวจ middleware group/exception handler ให้ unauthenticated request ที่เป็น API ตอบ JSON 401

### 4. PHP/AI/WebSocket และพอร์ตจำนวนมากเปิดจาก LAN

จากเครื่องภายนอก `Test-NetConnection` พบว่าเปิด:

```text
8000  8001  8022  8080  8082  9999
```

บน server ยังพบ listener ที่ bind `0.0.0.0` หรือ `::` สำหรับ:

- AI service: `8001`
- Reverb: `8082`
- monitor/บริการอื่น: `9999`
- พอร์ตช่วง `50010-50055`
- พอร์ตสุ่มอื่น เช่น `40332` และ `44701`

นี่ขัดกับข้อสรุปเดิมที่บอกว่าบริการเหล่านี้ถูก isolate หรือเปิดเฉพาะ localhost ต้องระบุเจ้าของแต่ละพอร์ตและปิดพอร์ตที่ไม่จำเป็น

**ระดับ:** High  
**สถานะ:** ไม่ผ่าน  
**แก้ไข:** bind service ที่ไม่ต้องรับ LAN เป็น `127.0.0.1`, ใช้ reverse proxy เฉพาะ endpoint ที่จำเป็น และใช้ firewall allowlist จาก root/admin

### 5. Firewall ยังตรวจไม่ได้

คำสั่ง `iptables -S` บน server ตอบ:

```text
Permission denied (you must be root)
```

จึงยังสรุปไม่ได้ว่ามี firewall rules ใดทำงานอยู่ การที่พอร์ตจำนวนมากเข้าถึงได้จาก LAN เป็นหลักฐานว่าต้องตรวจ firewall และ process ownership เพิ่มเติม แต่ไม่ใช่หลักฐานว่า iptables ว่างแน่นอน

**สถานะ:** Unknown  
**คำสั่งที่ต้องรันด้วย root:**

```bash
iptables -S
iptables -L -n -v
nft list ruleset
```

## หลักฐาน HTTP ที่ตรวจจริง

| การทดสอบ | ผลจริง | การตีความ |
|---|---:|---|
| `GET /api/map/locations` | HTTP 302 | มี auth แต่ redirect แบบ web |
| `GET /admin/login` | HTTP 500 | admin route/runtime ผิดปกติ |
| `GET /storage/` | 404 | ไม่พบ resource; ยังไม่ใช่หลักฐานว่า Nginx deny ทำงาน |
| response headers | มี CSP, HSTS, nosniff, frame options | headers หลักมีอยู่ |
| `X-Powered-By` | `PHP/8.5.9` | ยังเปิดเผย PHP version |

## คะแนนที่รายงานได้อย่างซื่อสัตย์

ไม่ใช้คะแนนรวมแบบ `10/10` หรือ `100%` เพราะผลมีทั้ง source verification, live verification และ unknown ที่คนละระดับกัน

- **ผ่าน live ชัดเจน:** Redis auth/binding, PostgreSQL LAN isolation, SSH hardening
- **ผ่านจาก source:** route protection, rate limiter definitions, CSP/header declarations, Nginx storage rule, robots rules
- **ไม่ผ่าน live:** PHP version hiding, Admin login availability, unnecessary public service exposure
- **ยังไม่ทราบ:** firewall rules และสาเหตุที่แท้จริงของพอร์ตสุ่มทั้งหมด

## ลำดับการแก้ไขที่แนะนำ

1. จำกัดหรือหยุดพอร์ต `8001`, `8082`, `9999`, `500xx` และพอร์ตสุ่มที่ไม่จำเป็นทันที
2. ตรวจ log เพื่อแก้ HTTP 500 ของ `/admin/login`
3. ปิด `X-Powered-By` ใน runtime ที่กำลังรันจริง แล้วทดสอบ header ใหม่
4. ปรับ unauthenticated API response จาก 302 เป็น JSON 401
5. รัน firewall inspection ด้วย root และสร้าง allowlist เหลือเฉพาะพอร์ตที่จำเป็น
6. หมุนเวียน credentials/tokens ทั้งหมดที่เคยอยู่ใน `.env` หากไฟล์หรือ Git history ถูกเผยแพร่

## ข้อสรุป

ระบบไม่ได้อยู่ในสถานะ “ปลอดภัยครบถ้วน” ตามรายงานก่อนหน้า ผลตรวจจริงยืนยันว่าฐานข้อมูล Redis/PostgreSQL และ SSH ดีขึ้นและป้องกันการเข้าถึงจาก LAN ได้จริง แต่ยังพบช่องโหว่ด้านการเปิด service, PHP version disclosure และ admin runtime error ขณะเดียวกัน firewall ยังไม่มีหลักฐานเพียงพอจากผู้ใช้ SSH ปัจจุบัน

รายงานนี้จึงควรใช้เป็น **ผลตรวจ live ณ วันที่ 2026-08-19** ไม่ใช่ใบรับรองความปลอดภัยถาวร เพราะสถานะอาจเปลี่ยนได้เมื่อมีการ restart, deploy image ใหม่ หรือแก้ configuration บนเครื่องจริง.
