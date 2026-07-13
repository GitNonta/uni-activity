import time
import os

print("กำลังโหลดไลบรารี DeepFace... (ขั้นตอนนี้อาจใช้เวลาสักพัก)")
start_import = time.time()
try:
    from deepface import DeepFace
except ImportError:
    print("\n[Error] ไม่พบไลบรารี DeepFace กรุณาติดตั้งด้วยคำสั่ง: pip install deepface opencv-python-headless tf-keras")
    exit(1)
print(f"โหลดไลบรารีเสร็จสิ้น ใช้เวลา: {time.time() - start_import:.2f} วินาที\n")

# ตรวจสอบรูปภาพ
img1_path = "sample1.jpg"
img2_path = "sample2.jpg"

if not os.path.exists(img1_path) or not os.path.exists(img2_path):
    print(f"[Error] ไม่พบไฟล์รูปภาพทดสอบ ({img1_path} หรือ {img2_path})")
    print("กรุณานำรูปภาพที่มีใบหน้าชัดเจน 2 รูปมาใส่ในโฟลเดอร์นี้และตั้งชื่อเป็น sample1.jpg และ sample2.jpg")
    exit(1)

print("กำลังโหลดโมเดลและรันการเปรียบเทียบรูปภาพ... (รันครั้งแรกจะช้าเพราะต้องโหลดน้ำหนักโมเดล)")
start_verify = time.time()

try:
    # ทดสอบใช้ VGG-Face ก่อน (โมเดลมาตรฐานที่เร็วปานกลาง)
    # หากต้องการความแม่นยำสูงสุด ให้เปลี่ยน model_name="ArcFace" 
    # หากต้องการความเร็วสูงสุด ให้เปลี่ยน model_name="Facenet512" หรือ "OpenFace"
    model = "VGG-Face"
    print(f"Model: {model}")
    
    result = DeepFace.verify(
        img1_path=img1_path, 
        img2_path=img2_path, 
        model_name=model,
        enforce_detection=False # ป้องกัน error กรณีรูปไม่มีหน้า (ตอนทดสอบ)
    )
    
    verify_time = time.time() - start_verify
    
    print("\n" + "="*40)
    print("🎉 ผลลัพธ์การเปรียบเทียบ")
    print("="*40)
    print(f"ตรงกันหรือไม่ (Match) : {result['verified']}")
    print(f"ระยะห่าง (Distance) : {result['distance']:.4f}")
    print(f"ความคล้ายคลึง (Similarity): ประมาณ {(1 - result['distance']) * 100:.2f}%")
    print(f"เวลาที่ใช้ประมวลผล : {verify_time:.2f} วินาที")
    print("="*40)
    
    if verify_time > 3.0:
        print("\n⚠️ [ข้อสังเกต] การประมวลผลใช้เวลาเกิน 3 วินาที ถือว่าค่อนข้างช้าสำหรับระบบเรียลไทม์ (Kiosk)")
        print("คำแนะนำ: ลองทดสอบรันสคริปต์นี้อีก 1-2 ครั้ง เพราะครั้งแรกจะเสียเวลาโหลดโมเดลเข้า RAM เสมอ")
    else:
        print("\n✅ [ข้อสังเกต] ความเร็วอยู่ในระดับที่ยอมรับได้ สามารถนำไปประยุกต์ทำระบบ Kiosk ได้ครับ")

except Exception as e:
    print("\n[Error] เกิดข้อผิดพลาดระหว่างการประมวลผล:")
    print(str(e))
