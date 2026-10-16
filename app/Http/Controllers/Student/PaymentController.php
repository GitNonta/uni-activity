<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Payment;
use App\Services\ListCache;
use App\Services\PromptPay\PromptPayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function show(Request $request, Activity $activity): View
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $payment = Payment::firstOrCreate(
            ['user_id' => $user->id, 'activity_id' => $activity->id, 'amount' => (float) $activity->fee_amount],
            [
                'method' => 'promptpay',
                'promptpay_id' => (string) ($activity->promptpay_id ?: config('services.promptpay.fallback_id', '')),
                'ref' => 'PP'.strtoupper(Str::random(11)),
                'status' => 'pending',
            ],
        );

        $target = (string) ($activity->promptpay_id ?: config('services.promptpay.fallback_id', ''));
        $qrPayload = null;

        if ((float) $activity->fee_amount > 0 && $target !== '' && $payment->status === 'pending') {
            try {
                $qrPayload = (new PromptPayService)->payload($target, (float) $activity->fee_amount);
            } catch (\Throwable) {
                $qrPayload = null;
            }
        }

        return view('student.payment.qr', [
            'activity' => $activity,
            'payment' => $payment->fresh(),
            'qrPayload' => $qrPayload,
            'enablePay' => $payment->status === 'pending' && $qrPayload !== null,
        ]);
    }

    public function markPaid(Request $request, Activity $activity): RedirectResponse
    {
        $user = Auth::user();
        abort_unless($user !== null, 403);

        $payment = Payment::where('user_id', $user->id)
            ->where('activity_id', $activity->id)
            ->orderByDesc('id')
            ->firstOrFail();

        if ($payment->status === 'pending') {
            $payment->update(['status' => 'paid', 'paid_at' => now()]);
            ListCache::bump(ListCache::GROUP_ACTIVITIES);
        }

        return redirect()->route('activities.pay.show', $activity)
            ->with('success', __('ยืนยันการชำระเงิน :ref แล้ว (รอเจ้าหน้าที่ตรวจสอบ)', ['ref' => $payment->ref]));
    }
}
