<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Sarabun', sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; }
        .header { text-align: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; margin-bottom: 20px; }
        .otp-code { 
            display: block; 
            width: fit-content; 
            margin: 25px auto; 
            padding: 15px 40px; 
            background: #eef2ff; 
            border: 2px solid #4f46e5; 
            border-radius: 12px; 
            font-size: 36px; 
            font-weight: 700; 
            color: #4f46e5; 
            letter-spacing: 8px;
            text-align: center;
        }
        .info-box { background: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0; font-size: 14px; border: 1px solid #edf2f7; }
        .info-item { margin-bottom: 8px; display: flex; }
        .info-label { font-weight: 600; width: 120px; color: #64748b; }
        .footer { font-size: 12px; color: #94a3b8; margin-top: 30px; text-align: center; border-top: 1px solid #f1f5f9; padding-top: 20px; }
        .warning { color: #e53e3e; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #4f46e5; margin: 0; font-size: 24px; display:flex; align-items:center; gap:8px;"><svg style="width:28px;height:28px;" fill="currentColor" viewBox="0 0 20 20"><path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg> ระบบกิจกรรม</h1>
            <p style="margin-top: 5px; color: #64748b;">การยืนยันตัวตนเพื่อเข้าสู่ระบบ</p>
        </div>

        <p>สวัสดีคุณ <strong>{{ $fullName }}</strong>,</p>
        <p>มีการพยายามเข้าสู่ระบบบัญชีของคุณ กรุณาใช้รหัส OTP ด้านล่างเพื่อดำเนินการต่อ:</p>

        <div class="otp-code">{{ $otp }}</div>

        <p style="text-align: center; color: #64748b; font-size: 13px;">
            รหัสนี้จะหมดอายุใน <strong>{{ $expiryMinutes }} นาที</strong>
        </p>

        <div class="info-box">
            <h4 style="margin-top: 0; margin-bottom: 10px; color: #1e293b;">รายละเอียดการขอเข้าสู่ระบบ:</h4>
            <div class="info-item">
                <span class="info-label">ที่อยู่ IP:</span>
                <span>{{ $ipAddress }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">สถานที่ใกล้เคียง:</span>
                <span>{{ $location }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">เวลา:</span>
                <span>{{ now()->format('d/m/Y H:i:s') }}</span>
            </div>
        </div>

        <p class="warning">หากนี่ไม่ใช่การกระทำของคุณ โปรดระวัง! บัญชีของคุณอาจตกอยู่ในอันตราย</p>
        <p>แนะนำให้คุณรีบเปลี่ยนรหัสผ่านทันทีหากคุณไม่ได้เป็นผู้ขอรหัสนี้</p>

        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}</p>
            <p>นี่คืออีเมลอัตโนมัติเพื่อความปลอดภัย กรุณาอย่าตอบกลับ</p>
        </div>
    </div>
</body>
</html>
