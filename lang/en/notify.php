<?php

return array (
  // ── System notifications ──
  'registered_title' => 'Registration Successful',
  'registered_msg' => 'You have successfully registered for the activity ":activity".',
  'waitlisted_title' => 'On Waitlist',
  'waitlisted_msg' => 'The activity ":activity" is full. You have been added to the waitlist.',
  'notify_cancel_title' => 'Registration Cancelled',
  'notify_cancel_msg' => 'You have cancelled your registration for the activity ":activity".',
  'waitlist_promoted_title' => 'Promoted from Waitlist',
  'waitlist_promoted_msg' => 'You have been promoted from the waitlist and successfully registered for the activity ":activity".',
  'registration_approved_title' => 'Registration Approved',
  'registration_approved_msg' => 'Your request to join the activity ":activity" has been approved.',
  'registration_rejected_title' => 'Registration Rejected',
  'registration_rejected_msg' => 'Your request to join the activity ":activity" was not approved.',
  'attendance_ok_title' => 'Activity Recorded Successfully',
  'attendance_ok_msg' => 'Your attendance for the activity ":activity" has been approved. You earned :hours hours.',
  'attendance_no_title' => 'Attendance Not Recorded',
  'attendance_no_msg' => 'Your attendance for the activity ":activity" was rejected. Please contact the organizer.',

  // ── Flash messages ──
  'flash_reg_success' => 'Successfully registered for the activity!',
  'flash_reg_cancel' => 'Registration cancelled successfully.',
  'flash_cancel_too_late' => 'Cannot cancel — the activity has already started.',
  'flash_approve_ok' => 'Registration approved successfully.',
  'flash_reject_ok' => 'Registration rejected successfully.',
  'flash_att_ok' => 'Attendance approved successfully.',
  'flash_att_no' => 'Attendance rejected successfully.',

  // ── Registration error messages ──
  'err_not_in_window' => 'Not within the registration period.',
  'err_already_registered' => 'You are already registered for this activity.',
  'err_time_conflict' => 'You already have another activity in this time slot. Overlapping activities cannot be registered.',
  'err_student_not_found' => 'Student code not found.',
  'err_student_not_registered' => 'This student is not registered for this activity.',
  'err_already_checked_in' => 'This student has already checked in.',
);
