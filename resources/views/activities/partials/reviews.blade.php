{{-- ส่วนแสดงคะแนน/รีวิวของกิจกรรม (ข้อมูลจาก View Composer: ActivityFeedbackComposer) --}}
<div class="card mt-2">
    <div class="card-body" style="font-size:.875rem;">
        <h2 class="font-bold" style="font-size:1rem; margin:0 0 .5rem;">⭐ {{ __('คะแนนประเมิน') }}</h2>

        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
            <span class="font-bold" style="font-size:1.2rem;">{{ number_format($avgRating, 1) }}</span>
            <span style="color:#f5a623; font-size:.95rem; letter-spacing:1px;">{{ str_repeat('⭐', min(5, max(0, (int) round($avgRating)))) }}</span>
            <span class="text-muted" style="font-size:.8rem;">({{ number_format($reviewsCount) }} {{ __('รีวิว') }})</span>
        </div>

        @if($canReview)
            <div class="mt-2">
                <a href="{{ route('feedback.create', $activity) }}"
                   class="btn btn-primary btn-block"
                   style="text-align:center; display:inline-block; text-decoration:none;">
                    {{ __('ให้คะแนนและรีวิวกิจกรรมนี้') }}
                </a>
            </div>
        @endif

        @if($latestReviews->isNotEmpty())
            <hr class="divider">
            <div style="display:flex; flex-direction:column; gap:.75rem;">
                @foreach($latestReviews as $review)
                    <div style="background:#f8fafc; border:1px solid #f1f5f9; border-radius:10px; padding:.75rem;">
                        <div style="display:flex; justify-content:space-between; align-items:center; gap:.5rem;">
                            <span class="font-semi" style="font-size:.85rem;">
                                @if($review->is_anonymous)
                                    {{ __('นักศึกษา (ไม่ระบุตัวตน)') }}
                                @else
                                    {{ $review->user->full_name ?? $review->user->username ?? __('นักศึกษา') }}
                                @endif
                            </span>
                            <span style="color:#f5a623; font-size:.85rem; letter-spacing:1px;">{{ str_repeat('⭐', max(0, (int) $review->rating)) }}</span>
                        </div>
                        @if($review->comment)
                            <p style="margin:.4rem 0 0; font-size:.85rem; color:#475569; white-space:pre-wrap;">{{ $review->comment }}</p>
                        @endif
                        <span class="text-muted" style="font-size:.72rem;">{{ $review->created_at->diffForHumans() }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
