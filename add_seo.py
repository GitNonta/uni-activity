import os

BASE = '/data/data/com.termux/files/home/uni-activity'

seo_data = {
    'resources/views/activities/index.blade.php': {
        'title': 'รายการกิจกรรมทั้งหมด',
        'description': 'ค้นหาและลงทะเบียนเข้าร่วมกิจกรรมมหาวิทยาลัย กิจกรรมบังคับ กิจกรรมสมัครใจ หางาน part-time',
        'keywords': 'กิจกรรม, มหาวิทยาลัย, ลงทะเบียน, กิจกรรมบังคับ, หางาน, part-time, UNI Activity',
    },
    'resources/views/activities/show.blade.php': {
        'title': 'รายละเอียดกิจกรรม',
        'description': 'ดูรายละเอียดกิจกรรม วันที่ สถานที่ เวลา ลงทะเบียนเข้าร่วม และติดตามสถานะการเข้าร่วม',
        'keywords': 'รายละเอียดกิจกรรม, ลงทะเบียน, สถานะการเข้าร่วม',
        'type': 'article',
    },
    'resources/views/jobs/index.blade.php': {
        'title': 'หางาน Part-time',
        'description': 'ค้นหางาน Part-time สำหรับนักศึกษา หางาน Tutor หางานช่วยสอน และงานอื่นๆ',
        'keywords': 'หางาน, part-time, tutor, ช่วยสอน, หางานนักศึกษา',
    },
    'resources/views/jobs/show.blade.php': {
        'title': 'รายละเอียดงาน',
        'description': 'ดูรายละเอียดงาน Part-time สมัครงาน และติดตามสถานะการสมัคร',
        'keywords': 'รายละเอียดงาน, สมัครงาน, part-time',
        'type': 'article',
    },
    'resources/views/map/index.blade.php': {
        'title': 'แผนที่กิจกรรม',
        'description': 'ดูแผนที่ตำแหน่งกิจกรรมทั้งหมดในมหาวิทยาลัย ค้นหากิจกรรมใกล้คุณ',
        'keywords': 'แผนที่, กิจกรรม, ตำแหน่ง, map',
    },
    'resources/views/auth/login.blade.php': {
        'title': 'เข้าสู่ระบบ',
        'description': 'เข้าสู่ระบบ UNI Activity เพื่อจัดการกิจกรรมของคุณ',
        'keywords': 'เข้าสู่ระบบ, login, UNI Activity',
    },
    'resources/views/auth/register.blade.php': {
        'title': 'สมัครสมาชิก',
        'description': 'สมัครสมาชิก UNI Activity เพื่อเริ่มใช้งานระบบจัดการกิจกรรม',
        'keywords': 'สมัครสมาชิก, register, UNI Activity',
    },
    'resources/views/welcome.blade.php': {
        'title': 'ยินดีต้อนรับ',
        'description': 'UNI Activity - ระบบศูนย์รวมกิจกรรมมหาวิทยาลัย ค้นหา ลงทะเบียน และติดตามกิจกรรม',
        'keywords': 'UNI Activity, ระบบจัดการกิจกรรม, มหาวิทยาลัย',
    },
    'resources/views/student/my-activities.blade.php': {
        'title': 'กิจกรรมของฉัน',
        'description': 'ดูกิจกรรมที่คุณลงทะเบียนแล้ว ติดตามสถานะการเข้าร่วม และดูประวัติ',
        'keywords': 'กิจกรรมของฉัน, สถานะการเข้าร่วม, ประวัติ',
    },
    'resources/views/student/certificates/index.blade.php': {
        'title': 'ใบรับรองของฉัน',
        'description': 'ดูและดาวน์โหลดใบรับรองการเข้าร่วมกิจกรรม',
        'keywords': 'ใบรับรอง, certificate, UNI Activity',
    },
    'resources/views/certificates/verify.blade.php': {
        'title': 'ตรวจสอบใบรับรอง',
        'description': 'ตรวจสอบความถูกต้องของใบรับรองการเข้าร่วมกิจกรรม UNI Activity',
        'keywords': 'ตรวจสอบใบรับรอง, verify certificate',
    },
    'resources/views/student/announcements/index.blade.php': {
        'title': 'ประกาศ',
        'description': 'อ่านประกาศล่าสุดจากมหาวิทยาลัย กิจกรรมที่กำลังจะมาถึง และข่าวสารสำคัญ',
        'keywords': 'ประกาศ, ข่าวสาร, กิจกรรม, UNI Activity',
    },
    'resources/views/student/history.blade.php': {
        'title': 'ประวัติกิจกรรม',
        'description': 'ดูประวัติการเข้าร่วมกิจกรรมทั้งหมดของคุณ',
        'keywords': 'ประวัติ, กิจกรรม, ประวัติการเข้าร่วม',
    },
    'resources/views/student/summary.blade.php': {
        'title': 'สรุปกิจกรรม',
        'description': 'สรุปชั่วโมงกิจกรรมทั้งหมดที่คุณสะสม',
        'keywords': 'สรุปกิจกรรม, ชั่วโมงกิจกรรม, UNI Activity',
    },
    'resources/views/student/calendar.blade.php': {
        'title': 'ปฏิทินกิจกรรม',
        'description': 'ดูปฏิทินกิจกรรมทั้งหมดในเดือนนี้',
        'keywords': 'ปฏิทิน, กิจกรรม, calendar, UNI Activity',
    },
}

for filepath, seo in seo_data.items():
    full_path = os.path.join(BASE, filepath)
    if not os.path.exists(full_path):
        print(f'SKIP: {filepath}')
        continue

    with open(full_path, 'r') as f:
        content = f.read()

    lines = content.split('\n')
    new_lines = []
    seo_added = False

    for line in lines:
        new_lines.append(line)
        if not seo_added and '@section(' in line and 'title' in line:
            desc = seo['description'].replace("'", "\\'")
            kw = seo['keywords'].replace("'", "\\'")
            new_lines.append(f"@section('description', '{desc}')")
            new_lines.append(f"@section('keywords', '{kw}')")
            if seo.get('type'):
                new_lines.append(f"@section('og_type', '{seo['type']}')")
            seo_added = True

    with open(full_path, 'w') as f:
        f.write('\n'.join(new_lines))

    print(f'Updated: {filepath}')

print('Done!')
