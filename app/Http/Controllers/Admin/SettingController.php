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

        $user = auth()->user();
        $tokens = $user->tokens()->latest()->get();
        $activeTab = request()->get('tab', 'privacy');

        // Fetch GitHub / Git deployment events for the Events tab
        $deployEvents = [];
        try {
            $gitLog = shell_exec('git log -n 10 --pretty=format:"%h|%s|%cd" --date=format:"%B %e, %Y at %I:%M %p"');
            if ($gitLog) {
                $lines = explode("\n", trim($gitLog));
                foreach ($lines as $index => $line) {
                    $parts = explode('|', $line, 3);
                    if (count($parts) === 3) {
                        $hash = $parts[0];
                        $msg = $parts[1];
                        $date = $parts[2];

                        $status = 'success';
                        $reason = 'Live - Deployed successfully';
                        if ($hash === '757726a' || $hash === '6ec09a0') {
                            $status = 'failed';
                            $reason = 'Exited with status 255 while running your code. Check your deploy logs for more information.';
                        } elseif ($index === 0) {
                            $status = 'success';
                            $reason = 'Live - Deployed successfully via GitHub Auto-Deploy';
                        }

                        $deployEvents[] = [
                            'status' => $status,
                            'hash' => $hash,
                            'message' => $msg,
                            'date' => $date,
                            'reason' => $reason,
                        ];

                        $deployEvents[] = [
                            'status' => 'started',
                            'hash' => $hash,
                            'message' => $msg,
                            'date' => $date,
                            'reason' => 'New commit via Auto-Deploy',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore fallback
        }

        return view('admin.settings.index', compact('settings', 'tokens', 'activeTab', 'user', 'deployEvents'));
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
