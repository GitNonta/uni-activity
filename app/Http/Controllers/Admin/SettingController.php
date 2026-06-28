<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'student_email_prefix' => Setting::get('student_email_prefix', 's'),
            'student_email_domain' => Setting::get('student_email_domain', '@pkru.ac.th'),
        ];
        
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'student_email_prefix' => 'nullable|string|max:10',
            'student_email_domain' => 'required|string|starts_with:@|max:50',
        ]);

        Setting::set('student_email_prefix', $request->input('student_email_prefix', ''));
        Setting::set('student_email_domain', $request->input('student_email_domain'));

        return back()->with('success', 'บันทึกการตั้งค่าระบบเรียบร้อยแล้ว');
    }
}
