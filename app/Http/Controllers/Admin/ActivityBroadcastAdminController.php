<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendActivityBroadcastRequest;
use App\Models\Activity;
use App\Services\ActivityNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller สำหรับการบรอดแคสต์ข้อความด่วน และจัดการ Auto-Reminder ในกิจกรรม
 */
class ActivityBroadcastAdminController extends Controller
{
    public function __construct(
        private readonly ActivityNotificationService $notificationService
    ) {}

    /**
     * หน้าจัดการบรอดแคสต์และประวัติข้อความของกิจกรรม
     */
    public function index(Activity $activity): View
    {
        $stats = $this->notificationService->getActivityBroadcastStats($activity);

        return view('admin.activities.broadcast', [
            'activity' => $activity,
            'stats'    => $stats,
        ]);
    }

    /**
     * ส่งข้อความบรอดแคสต์ไปยังกลุ่มนักศึกษาที่ลงทะเบียน
     */
    public function send(SendActivityBroadcastRequest $request, Activity $activity): RedirectResponse
    {
        $broadcast = $this->notificationService->broadcastToActivity(
            activity: $activity,
            sender: $request->user(),
            data: $request->validated()
        );

        $lineMsg = $broadcast->line_sent_count > 0 ? "และ LINE {$broadcast->line_sent_count} บัญชี" : "";

        return redirect()
            ->route('admin.activities.broadcast', $activity)
            ->with('success', "ส่งข้อความบรอดแคสต์เรียบร้อยแล้ว (ส่งถึงนักศึกษา {$broadcast->recipients_count} คน {$lineMsg})");
    }

    /**
     * สั่งส่ง Auto-Reminder ด้วยตนเอง (24h หรือ 2h)
     */
    public function triggerReminder(Request $request, Activity $activity): RedirectResponse
    {
        $window = (string) $request->input('window', '24h');
        if (!in_array($window, ['24h', '2h'], true)) {
            $window = '24h';
        }

        $result = $this->notificationService->sendActivityReminder($activity, $window);
        $windowText = $window === '2h' ? 'ล่วงหน้า 2 ชั่วโมง' : 'ล่วงหน้า 1 วัน';

        $lineMsg = $result['line_sent_count'] > 0 ? "และ LINE {$result['line_sent_count']} บัญชี" : "";

        return redirect()
            ->route('admin.activities.broadcast', $activity)
            ->with('success', "ส่งแจ้งเตือน Auto-Reminder ({$windowText}) สำเร็จ (นักศึกษา {$result['recipients_count']} คน {$lineMsg})");
    }
}
