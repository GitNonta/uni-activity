<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (request()->get('tab') === 'api-keys') {
            return redirect()->route('admin.api-keys.index');
        }

        $settings = [
            'student_email_prefix' => Setting::get('student_email_prefix', 's'),
            'student_email_domain' => Setting::get('student_email_domain', '@pkru.ac.th'),
        ];

        $user = auth()->user();
        $activeTab = (string) request()->get('tab', 'general');

        return view('admin.settings.index', compact('settings', 'activeTab', 'user'));
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'student_email_prefix' => 'nullable|string|max:10',
            'student_email_domain' => 'required|string|starts_with:@|max:50',
        ]);

        Setting::set('student_email_prefix', (string) $request->input('student_email_prefix', ''));
        Setting::set('student_email_domain', (string) $request->input('student_email_domain'));

        return back()->with('success', 'บันทึกการตั้งค่าระบบเรียบร้อยแล้ว');
    }
}
