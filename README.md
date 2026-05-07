# 🎓 University Activity System (ระบบจัดการกิจกรรมมหาวิทยาลัย)

![Laravel](https://img.shields.io/badge/Laravel-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-00000F?style=for-the-badge&logo=mysql&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)

ระบบจัดการกิจกรรมนักศึกษามหาวิทยาลัย พัฒนาด้วย **Laravel** เพื่อช่วยในการจัดการกิจกรรม การเช็คชื่อผ่าน QR Code การออกใบประกาศนียบัตร และการจัดการข้อมูลนักศึกษาอย่างมีประสิทธิภาพ

## ✨ ฟีเจอร์หลัก (Key Features)

*   📱 **ระบบเช็คชื่อด้วย QR Code:** นักศึกษาสามารถสแกน QR Code เพื่อเข้าร่วมกิจกรรมได้อย่างรวดเร็ว
*   👥 **ระบบจัดการสิทธิ์ (Role & Permission):** แบ่งสิทธิ์การใช้งาน (ผู้ดูแลระบบ, เจ้าหน้าที่, นักศึกษา) อย่างชัดเจน
*   📄 **ออกเอกสาร PDF:** สามารถสร้างและดาวน์โหลดใบรายงานผลการเข้าร่วมกิจกรรมเป็น PDF ได้
*   📊 **นำเข้า/ส่งออก Excel:** จัดการข้อมูลนักศึกษาและข้อมูลกิจกรรมได้ง่ายๆ ผ่านไฟล์ Excel
*   🔔 **การแจ้งเตือนแบบ Real-time:** แจ้งเตือนข้อมูลและกิจกรรมใหม่ๆ ทันที
*   🎨 **หน้าเพจแจ้งเตือนข้อผิดพลาด (Custom Error Pages):** หน้า Error Pages (เช่น 404, 500) ที่ออกแบบมาอย่างสวยงามพร้อมคำแนะนำ

## 🛠️ เทคโนโลยีที่ใช้ (Tech Stack)

*   **Framework:** Laravel 12.x
*   **Language:** PHP 8.2+
*   **Database:** MySQL
*   **Styling:** Tailwind CSS
*   **Real-time:** Laravel Reverb
*   **Packages:**
    *   `spatie/laravel-permission` (จัดการสิทธิ์การใช้งาน)
    *   `simplesoftwareio/simple-qrcode` (สร้าง QR Code)
    *   `barryvdh/laravel-dompdf` (สร้างไฟล์ PDF)
    *   `maatwebsite/excel` (จัดการไฟล์ Excel)

## 🚀 การติดตั้งและใช้งาน (Installation)

1. **Clone โปรเจกต์:**
   ```bash
   git clone <repository-url>
   cd uni-activity
   ```

2. **ติดตั้ง Dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **ตั้งค่า Environment:**
   คัดลอกไฟล์ `.env.example` เป็น `.env` และตั้งค่าการเชื่อมต่อฐานข้อมูล:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **ตั้งค่าฐานข้อมูล (Database):**
   ```bash
   php artisan migrate --seed
   ```
   *(หมายเหตุ: หากพบปัญหา Database Connection สามารถดูวิธีแก้ได้ที่ [README_FIRST.md](./README_FIRST.md))*

5. **รันเซิร์ฟเวอร์:**
   ```bash
   npm run dev
   php artisan serve
   ```
   *หรือสามารถใช้สคริปต์ `START_APPLICATION.bat` ในการรันระบบ*

## 📚 เอกสารคู่มือ (Documentation & Guides)

โปรเจกต์นี้มีเอกสารคู่มือครอบคลุมการใช้งานและการแก้ไขปัญหาต่างๆ:

*   **[คู่มือการแก้ไขปัญหา Database 500 Error (README_FIRST.md)](./README_FIRST.md)** - วิธีแก้ปัญหาเชื่อมต่อฐานข้อมูลอย่างรวดเร็ว
*   **[คู่มือจัดการ Error Pages (BEAUTIFUL_ERROR_PAGES_README.md)](./BEAUTIFUL_ERROR_PAGES_README.md)** - รายละเอียดเกี่ยวกับหน้า Error 404, 500 ฯลฯ
*   **[คู่มือ API Testing (API_TESTING_GUIDE.md)](./API_TESTING_GUIDE.md)** - สำหรับทดสอบระบบ API
*   **[เอกสารสรุปการแก้ไขปัญหา (COMPLETE_SOLUTION_SUMMARY.md)](./COMPLETE_SOLUTION_SUMMARY.md)** - รายละเอียดเชิงเทคนิคและการแก้บั๊ก

## 👥 ผู้พัฒนา
* **University Activity System Team**

---
*Developed with ❤️ using Laravel*
