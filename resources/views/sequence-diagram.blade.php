@extends('layouts.app')
@section('title', 'ลำดับการใช้งานระบบกิจกรรมนักศึกษา')

@section('content')
<style>
    .sequence-page { max-width: 1180px; margin: 0 auto; }
    .sequence-intro { margin-bottom: 1.25rem; }
    .sequence-intro h1 { margin-bottom: .35rem; }
    .sequence-board {
        overflow-x: auto; padding: 1.25rem; border: 1px solid #fed7aa;
        border-radius: 16px; background: linear-gradient(135deg, #fff7ed, #fff);
    }
    .sequence-grid {
        min-width: 860px; display: grid;
        grid-template-columns: 170px repeat(4, minmax(150px, 1fr));
        grid-template-rows: auto 1fr;
    }
    .sequence-corner { min-height: 76px; }
    .sequence-actor {
        min-height: 76px; display: flex; align-items: center; justify-content: center;
        padding: .7rem; text-align: center; font-weight: 700; color: #7c2d12;
        border-bottom: 2px solid #fdba74;
    }
    .sequence-actor small { display: block; margin-top: .2rem; font-weight: 500; color: #9a3412; }
    .sequence-lane {
        position: relative; min-height: 620px; border-left: 1px dashed #fdba74;
        background: repeating-linear-gradient(to bottom, transparent 0, transparent 61px, #ffedd5 62px);
    }
    .sequence-lane:last-child { border-right: 1px dashed #fdba74; }
    .lifeline {
        position: absolute; top: 0; bottom: 0; left: 50%; border-left: 2px dashed #fb923c;
    }
    .message {
        position: absolute; left: 5%; right: 5%; display: flex; align-items: center;
        min-height: 34px; z-index: 1;
    }
    .message-line { flex: 1; border-top: 2px solid #c2410c; }
    .message-line.dashed { border-top-style: dashed; }
    .message-head { width: 0; height: 0; border-top: 6px solid transparent; border-bottom: 6px solid transparent; }
    .message-head.right { border-left: 9px solid #c2410c; }
    .message-head.left { border-right: 9px solid #c2410c; }
    .message-label {
        position: absolute; left: 50%; transform: translateX(-50%); top: -1.25rem;
        width: max-content; max-width: 190px; padding: .2rem .45rem; border-radius: 6px;
        background: #fff; color: #7c2d12; font-size: .73rem; line-height: 1.25;
        text-align: center; box-shadow: 0 1px 3px #fed7aa;
    }
    .note {
        position: absolute; left: 8%; right: 8%; padding: .45rem .6rem; border-radius: 8px;
        background: #fef3c7; border: 1px solid #f59e0b; color: #78350f; font-size: .72rem;
        line-height: 1.35; text-align: center; z-index: 2;
    }
    .phase {
        position: absolute; left: 4px; right: 4px; padding: .3rem .5rem; border-radius: 6px;
        background: #ffedd5; color: #9a3412; font-size: .7rem; font-weight: 700; text-align: center;
        z-index: 2;
    }
    .legend { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; margin-top: 1rem; color: #57534e; font-size: .8rem; }
    .legend span { display: inline-flex; align-items: center; gap: .4rem; }
    .legend-line { width: 28px; border-top: 2px solid #c2410c; }
    .legend-dashed { border-top-style: dashed; }
    @media (max-width: 700px) { .sequence-board { padding: .75rem; } }
</style>

<div class="sequence-page">
    <div class="sequence-intro">
        <h1 class="font-bold" style="font-size:1.5rem;">Sequence Diagram: Student Activity Flow</h1>
        <p class="text-muted">ลำดับการใช้งานตั้งแต่ดูและเข้าร่วมกิจกรรม ไปจนถึงการสมัครล่วงหน้าและเข้าสู่หน้าล็อกอิน</p>
    </div>

    <div class="card" style="margin-bottom:1rem;">
        <div class="card-body" style="padding:1rem 1.25rem;">
            <strong>ภาพรวม</strong>
            <span class="text-muted text-sm"> นักศึกษาสามารถดูรายการกิจกรรมแบบสาธารณะได้ก่อนเข้าสู่ระบบ แต่ต้องล็อกอินก่อนยืนยันการมีส่วนร่วม หรือดำเนินการพรีรีจิสเตอร์ต่อ</span>
        </div>
    </div>

    <div class="sequence-board" role="img" aria-label="แผนภาพลำดับการใช้งานของนักศึกษา">
        <div class="sequence-grid">
            <div class="sequence-corner"></div>
            <div class="sequence-actor">Student<small>นักศึกษา</small></div>
            <div class="sequence-actor">Activity Pages<small>หน้ากิจกรรม</small></div>
            <div class="sequence-actor">Participation<small>การเข้าร่วมกิจกรรม</small></div>
            <div class="sequence-actor">Login Page<small>หน้าล็อกอิน</small></div>

            <div style="position:relative; min-height:620px;">
                <div class="phase" style="top:18px;">1. Discover</div>
                <div class="phase" style="top:190px;">2. Participate</div>
                <div class="phase" style="top:370px;">3. Pre-register</div>
                <div class="phase" style="top:535px;">4. Authenticate</div>
            </div>

            <div class="sequence-lane">
                <div class="lifeline"></div>
                <div class="message" style="top:58px;"><span class="message-line"></span><span class="message-head right"></span><span class="message-label">เปิดดูรายการกิจกรรม</span></div>
                <div class="message" style="top:112px;"><span class="message-head left"></span><span class="message-line dashed"></span><span class="message-label">แสดงรายละเอียดกิจกรรม</span></div>
                <div class="message" style="top:228px;"><span class="message-line"></span><span class="message-head right"></span><span class="message-label">เลือกเข้าร่วมกิจกรรม</span></div>
                <div class="message" style="top:410px;"><span class="message-line"></span><span class="message-head right"></span><span class="message-label">ขอพรีรีจิสเตอร์</span></div>
                <div class="message" style="top:490px;"><span class="message-head left"></span><span class="message-line dashed"></span><span class="message-label">แจ้งว่าต้องเข้าสู่ระบบ</span></div>
                <div class="note" style="top:290px;">กิจกรรมและข้อมูลการเข้าร่วมถูกแสดงให้ผู้ใช้ตรวจสอบก่อนยืนยัน</div>
            </div>

            <div class="sequence-lane">
                <div class="lifeline"></div>
                <div class="message" style="top:76px;"><span class="message-head left"></span><span class="message-line dashed"></span><span class="message-label">ส่งข้อมูลกิจกรรม</span></div>
                <div class="message" style="top:246px;"><span class="message-head left"></span><span class="message-line dashed"></span><span class="message-label">แสดงสถานะ/เงื่อนไข</span></div>
                <div class="message" style="top:428px;"><span class="message-head left"></span><span class="message-line dashed"></span><span class="message-label">เก็บคำขอไว้ชั่วคราว</span></div>
                <div class="note" style="top:465px;">ยังไม่ถือว่าเข้าร่วมสำเร็จจนกว่าจะยืนยันตัวตน</div>
            </div>

            <div class="sequence-lane">
                <div class="lifeline"></div>
                <div class="message" style="top:262px;"><span class="message-line"></span><span class="message-head right"></span><span class="message-label">ตรวจสอบสิทธิ์เข้าร่วม</span></div>
                <div class="message" style="top:318px;"><span class="message-head left"></span><span class="message-line dashed"></span><span class="message-label">พร้อมให้ยืนยัน</span></div>
                <div class="message" style="top:442px;"><span class="message-line"></span><span class="message-head right"></span><span class="message-label">ส่งต่อไปล็อกอิน</span></div>
            </div>

            <div class="sequence-lane">
                <div class="lifeline"></div>
                <div class="message" style="top:558px;"><span class="message-head left"></span><span class="message-line dashed"></span><span class="message-label">แสดงฟอร์มรหัสนักศึกษา</span></div>
                <div class="note" style="top:585px;">ผู้ใช้ยังไม่ได้ล็อกอิน</div>
            </div>
        </div>

        <div class="legend" aria-label="คำอธิบายสัญลักษณ์">
            <span><i class="legend-line"></i> คำขอ / การกระทำ</span>
            <span><i class="legend-line legend-dashed"></i> ผลลัพธ์ / การตอบกลับ</span>
            <span>▱ หมายเหตุหรือเงื่อนไขของระบบ</span>
        </div>
    </div>
</div>
@endsection
