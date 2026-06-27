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
            <div class="logo"><svg style="width:24px;height:24px;display:inline-block;vertical-align:-4px;" fill="currentColor" viewBox="0 0 20 20"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg></div>
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
                <p><strong><svg style="width:16px;height:16px;display:inline;vertical-align:-2px;margin-right:4px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg> ความปลอดภัย:</strong></p
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
