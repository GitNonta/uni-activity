<?php

return array (
  // ── แจ้งเตือนระบบ (Notification) ──
  'registered_title' => 'ลงทะเบียนสำเร็จ',
  'registered_msg' => 'คุณลงทะเบียนกิจกรรม ":activity" เรียบร้อยแล้ว',
  'waitlisted_title' => 'อยู่ใน Waitlist',
  'waitlisted_msg' => 'กิจกรรม ":activity" เต็มแล้ว คุณถูกจัดให้อยู่ใน Waitlist',
  'notify_cancel_title' => 'ยกเลิกการลงทะเบียน',
  'notify_cancel_msg' => 'คุณยกเลิกการลงทะเบียนกิจกรรม ":activity"',
  'waitlist_promoted_title' => 'เลื่อนคิวสำเร็จ (Waitlist)',
  'waitlist_promoted_msg' => 'คุณได้รับการเลื่อนคิวและลงทะเบียนกิจกรรม ":activity" สำเร็จแล้ว',
  'registration_approved_title' => 'การลงทะเบียนได้รับการอนุมัติ',
  'registration_approved_msg' => 'การขอเข้าร่วมกิจกรรม ":activity" ได้รับการอนุมัติแล้ว',
  'registration_rejected_title' => 'การลงทะเบียนถูกปฏิเสธ',
  'registration_rejected_msg' => 'การขอเข้าร่วมกิจกรรม ":activity" ไม่ได้รับการอนุมัติ',
  'attendance_ok_title' => 'บันทึกกิจกรรมสำเร็จ',
  'attendance_ok_msg' => 'บันทึกการเข้าร่วมกิจกรรม ":activity" ได้รับการอนุมัติแล้ว คุณได้รับ :hours ชม.',
  'attendance_no_title' => 'บันทึกกิจกรรมไม่สำเร็จ',
  'attendance_no_msg' => 'บันทึกการเข้าร่วมกิจกรรม ":activity" ถูกปฏิเสธ กรุณาติดต่อผู้จัด',

  // ── ข้อความแจ้งผลบนหน้า (flash) ──
  'flash_reg_success' => 'ลงทะเบียนกิจกรรมสำเร็จ!',
  'flash_reg_cancel' => 'ยกเลิกการลงทะเบียนสำเร็จ',
  'flash_cancel_too_late' => 'ไม่สามารถยกเลิกได้ กิจกรรมเริ่มแล้ว',
  'flash_approve_ok' => 'อนุมัติการลงทะเบียนสำเร็จ',
  'flash_reject_ok' => 'ปฏิเสธการลงทะเบียนสำเร็จ',
  'flash_att_ok' => 'อนุมัติการเข้าร่วมสำเร็จ',
  'flash_att_no' => 'ปฏิเสธการเข้าร่วมสำเร็จ',

  // ── ข้อความผิดพลาดจากเงื่อนไขการลงทะเบียน ──
  'err_not_in_window' => 'ไม่อยู่ในช่วงเวลาลงทะเบียน',
  'err_already_registered' => 'คุณลงทะเบียนกิจกรรมนี้แล้ว',
  'err_time_conflict' => 'คุณมีกิจกรรมอื่นในช่วงเวลานี้แล้ว ไม่สามารถลงทะเบียนเวลาชนกันได้',
  'err_student_not_found' => 'ไม่พบรหัสนักศึกษา',
  'err_student_not_registered' => 'นักศึกษาไม่ได้ลงทะเบียนกิจกรรมนี้',
  'err_already_checked_in' => 'นักศึกษาเช็คอินไปแล้ว',
);
