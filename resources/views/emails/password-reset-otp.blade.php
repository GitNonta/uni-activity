<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Sarabun', sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 12px; }
        .header { text-align: center; margin-bottom: 30px; }
        .otp-code { 
            display: block; 
            width: fit-content; 
            margin: 20px auto; 
            padding: 15px 40px; 
            background: #f8fafc; 
            border: 2px dashed #4f46e5; 
            border-radius: 8px; 
            font-size: 32px; 
            font-weight: 700; 
            color: #4f46e5; 
            letter-spacing: 5px;
        }
        .footer { font-size: 12px; color: #64748b; margin-top: 40px; text-align: center; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="color: #4f46e5; margin: 0;">🎓 ระบบกิจกรรม</h1>
            <p style="margin-top: 10px; font-weight: 500;">รหัสยืนยันการรีเซ็ตรหัสผ่าน</p>
        </div>

        <p>สวัสดีครับ/ค่ะ,</p>
        <p>คุณได้รับอีเมลนี้เนื่องจากมีการร้องขอรีเซ็ตรหัสผ่านสำหรับบัญชีของคุณ กรุณาใช้รหัส OTP ด้านล่างเพื่อยืนยันตัวตนบนเว็บไซต์:</p>

        <div class="otp-code">{{ $otp }}</div>

        <p style="text-align: center; color: #ef4444; font-size: 14px;">
            รหัสนี้จะหมดอายุภายใน <strong>{{ $expiryMinutes }} นาที</strong>
        </p>

        <p>หากคุณไม่ได้เป็นผู้ร้องขอ กรุณาละเว้นอีเมลฉบับนี้</p>

        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}</p>
            <p>นี่คืออีเมลอัตโนมัติ กรุณาอย่าตอบกลับ</p>
        </div>
    </div>
</body>
</html>
