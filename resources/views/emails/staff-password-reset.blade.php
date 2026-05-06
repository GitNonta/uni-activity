{{-- Email Template สำหรับส่งลิงก์รีเซ็ตรหัสผ่าน Staff --}}
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รีเซ็ตรหัสผ่าน</title>
    <style>
        body {
            font-family: 'Sarabun', 'Krub', sans-serif;
            background-color: #f3f4f6;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 30px 40px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .content {
            padding: 40px;
        }
        .content h2 {
            color: #1f2937;
            font-size: 20px;
            margin-bottom: 20px;
        }
        .content p {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .reset-button {
            display: inline-block;
            background: #3b82f6;
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin: 20px 0;
        }
        .reset-button:hover {
            background: #2563eb;
        }
        .security-notice {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .security-notice p {
            margin: 0;
            color: #92400e;
            font-size: 14px;
        }
        .footer {
            background: #f9fafb;
            padding: 20px 40px;
            text-align: center;
            color: #6b7280;
            font-size: 14px;
        }
        .logo {
            font-size: 28px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">🎓</div>
            <h1>ระบบจัดการกิจกรรมนักศึกษา</h1>
        </div>
        
        <div class="content">
            <h2>รีเซ็ตรหัสผ่านของคุณ</h2>
            
            <p>สวัสดีครับ/ค่ะ,</p>
            
            <p>เราได้รับคำขอรีเซ็ตรหัสผ่านสำหรับบัญชีเจ้าหน้าที่ของคุณในระบบจัดการกิจกรรมนักศึกษา</p>
            
            <p>กรุณาคลิกปุ่มด้านล่างเพื่อตั้งรหัสผ่านใหม่:</p>
            
            <div style="text-align: center;">
                <a href="{{ route('admin.password.reset', $token) }}?email={{ urlencode($email) }}" class="reset-button">
                    รีเซ็ตรหัสผ่าน
                </a>
            </div>
            
            <div class="security-notice">
                <p><strong>⚠️ ความปลอดภัย:</strong></p>
                <p>ลิงก์นี้จะหมดอายุภายใน {{ config('auth.passwords.staffs.expire', 60) }} นาที</p>
                <p>หากคุณไม่ได้เป็นผู้ร้องขอรีเซ็ตรหัสผ่าน กรุณาละเว้นอีเมลนี้</p>
            </div>
            
            <p>หากปุ่มข้างต้นไม่ทำงาน ให้คัดลอกลิงก์ด้านล่างและวางในเบราว์เซอร์:</p>
            <p style="word-break: break-all; background: #f3f4f6; padding: 10px; border-radius: 4px; font-size: 12px;">
                {{ route('admin.password.reset', $token) }}?email={{ urlencode($email) }}
            </p>
        </div>
        
        <div class="footer">
            <p>ระบบจัดการกิจกรรมนักศึกษา</p>
            <p style="font-size: 12px; margin-top: 5px;">
                อีเมลนี้ถูกส่งโดยระบบอัตโนมัติ กรุณาไม่ตอบกลับ
            </p>
        </div>
    </div>
</body>
</html>
