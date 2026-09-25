<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View|RedirectResponse
    {
        if (request()->get('tab') === 'api-keys') {
            return redirect()->route('admin.api-keys.index');
        }

        $settings = [
            'student_email_prefix'               => (string) Setting::get('student_email_prefix', 's'),
            'student_email_domain'               => (string) Setting::get('student_email_domain', '@pkru.ac.th'),
            'auto_approve_enabled'               => in_array(Setting::get('auto_approve_enabled', '1'), [true, 1, '1', 'true', 'yes'], true),
            'auto_approve_min_face_score'        => (float) Setting::get('auto_approve_min_face_score', '80.0'),
            'auto_approve_max_distance'          => (float) Setting::get('auto_approve_max_distance', '50.0'),
            'auto_approve_require_liveness'      => in_array(Setting::get('auto_approve_require_liveness', '1'), [true, 1, '1', 'true', 'yes'], true),
            'auto_approve_prevent_shared_device' => in_array(Setting::get('auto_approve_prevent_shared_device', '1'), [true, 1, '1', 'true', 'yes'], true),
        ];

        $user = auth()->user();
        $activeTab = (string) request()->get('tab', 'general');

        return view('admin.settings.index', compact('settings', 'activeTab', 'user'));
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if (array_key_exists('student_email_prefix', $validated)) {
            Setting::set('student_email_prefix', (string) ($validated['student_email_prefix'] ?? ''));
        }
        if (!empty($validated['student_email_domain'])) {
            Setting::set('student_email_domain', (string) $validated['student_email_domain']);
        }

        if ($request->has('settings_section') && $request->input('settings_section') === 'auto_approval') {
            Setting::set('auto_approve_enabled', $request->boolean('auto_approve_enabled') ? '1' : '0');
            Setting::set('auto_approve_min_face_score', (string) ($validated['auto_approve_min_face_score'] ?? '80.0'));
            Setting::set('auto_approve_max_distance', (string) ($validated['auto_approve_max_distance'] ?? '50.0'));
            Setting::set('auto_approve_require_liveness', $request->boolean('auto_approve_require_liveness') ? '1' : '0');
            Setting::set('auto_approve_prevent_shared_device', $request->boolean('auto_approve_prevent_shared_device') ? '1' : '0');

            return back()->with('success', 'บันทึกกฎการอนุมัติอัตโนมัติ (Smart Auto-Approval Rules) เรียบร้อยแล้ว');
        }

        if ($request->has('auto_approve_min_face_score')) {
            Setting::set('auto_approve_enabled', $request->boolean('auto_approve_enabled') ? '1' : '0');
            Setting::set('auto_approve_min_face_score', (string) ($validated['auto_approve_min_face_score'] ?? '80.0'));
            Setting::set('auto_approve_max_distance', (string) ($validated['auto_approve_max_distance'] ?? '50.0'));
            Setting::set('auto_approve_require_liveness', $request->boolean('auto_approve_require_liveness') ? '1' : '0');
            Setting::set('auto_approve_prevent_shared_device', $request->boolean('auto_approve_prevent_shared_device') ? '1' : '0');
        }

        return back()->with('success', 'บันทึกการตั้งค่าระบบเรียบร้อยแล้ว');
    }
}
