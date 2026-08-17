@extends('layouts.admin')
@section('title', 'สรุปผลการประเมิน: ' . $activity->title)

@section('content')
<div class="gform-container">

    {{-- ── 1. Top Navigation Bar ────────────────────────────────────────── --}}
    <div class="gform-nav-bar">
        <a href="{{ route('admin.feedbacks.index') }}" class="gform-back-btn" title="กลับหน้ารายการการประเมิน">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            <span>รายการประเมิน</span>
        </a>

        <div class="gform-actions">
            <button onclick="window.print()" class="gform-btn gform-btn-secondary" title="พิมพ์สรุปรายงานหรือบันทึกเป็น PDF">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                <span>พิมพ์รายงาน</span>
            </button>

            <a href="{{ route('admin.activities.show', $activity->id) }}" class="gform-btn gform-btn-secondary" title="ดูหน้ารายละเอียดกิจกรรม">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
                <span>ดูกิจกรรม</span>
            </a>
        </div>
    </div>

    {{-- ── 2. Google Forms Header Card ──────────────────────────────────── --}}
    <div class="gform-card gform-header-card">
        <div class="gform-header-accent"></div>
        <div class="gform-header-body">
            <div class="gform-badge-row">
                <span class="gform-category-badge">{{ $activity->category->name ?? 'กิจกรรมทั่วไป' }}</span>
                <span class="gform-status-badge">
                    <span class="gform-status-dot"></span>
                    รับคำตอบแล้ว {{ number_format($stats['total']) }} ชุด
                </span>
            </div>

            <h1 class="gform-title">{{ $activity->title }}</h1>
            <p class="gform-subtitle">
                แบบประเมินความพึงพอใจและผลสัมฤทธิ์ของกิจกรรม · จัดขึ้นเมื่อ {{ $activity->activity_date->translatedFormat('d F Y') }}
                @if($activity->location) · สถานที่: {{ $activity->location }} @endif
            </p>

            <div class="gform-meta-strip">
                <div class="gform-meta-item">
                    <span class="gform-meta-label">การตอบกลับทั้งหมด</span>
                    <span class="gform-meta-val primary">{{ number_format($stats['total']) }} <small>คน</small></span>
                </div>
                <div class="gform-meta-item">
                    <span class="gform-meta-label">คะแนนเฉลี่ยรวม</span>
                    <span class="gform-meta-val accent">
                        {{ number_format((float)$stats['average'], 2) }}
                        <small>/ 5.0</small>
                    </span>
                </div>
                <div class="gform-meta-item">
                    <span class="gform-meta-label">ค่ามัธยฐาน (Median)</span>
                    <span class="gform-meta-val">{{ number_format((float)$stats['median'], 1) }}</span>
                </div>
                <div class="gform-meta-item">
                    <span class="gform-meta-label">อัตราการตอบประเมิน</span>
                    <span class="gform-meta-val">{{ $stats['responseRate'] }}%</span>
                </div>
            </div>
        </div>

        {{-- Google Forms Tab Switcher --}}
        <div class="gform-tabs">
            <button class="gform-tab active" onclick="switchGformTab('summary', this)">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
                <span>ข้อมูลสรุป (Summary)</span>
            </button>

            <button class="gform-tab" onclick="switchGformTab('questions', this)">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span>คำถาม (Questions)</span>
            </button>

            <button class="gform-tab" onclick="switchGformTab('individual', this)">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                <span>แยกตามบุคคล (Individual)</span>
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 1: ข้อมูลสรุป (SUMMARY TAB)                                      --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div id="tab-summary" class="gform-tab-pane active">

        @if($stats['total'] === 0)
            <div class="gform-card" style="text-align: center; padding: 4rem 2rem;">
                <svg width="64" height="64" fill="none" stroke="#71717a" viewBox="0 0 24 24" style="margin: 0 auto 1rem;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <h3 style="font-size: 1.2rem; font-weight: 700; color: #f4f4f5; margin-bottom: 0.5rem;">ยังไม่มีการตอบกลับการประเมิน</h3>
                <p style="color: #a1a1aa; font-size: 0.9rem; margin: 0;">เมื่อนักศึกษาที่เข้าร่วมกิจกรรมส่งแบบประเมิน ข้อมูลและกราฟวิเคราะห์ผลจะปรากฏที่นี่โดยอัตโนมัติ</p>
            </div>
        @else

            {{-- 1. Question 1: คะแนนความพึงพอใจภาพรวม --}}
            <div class="gform-card">
                <div class="gform-q-header">
                    <div class="gform-q-number">ข้อที่ 1</div>
                    <div class="gform-q-title-wrap">
                        <h3 class="gform-q-title">ระดับความพึงพอใจภาพรวมต่อการจัดกิจกรรม</h3>
                        <span class="gform-q-count">{{ number_format($stats['total']) }} การตอบกลับ · เฉลี่ย {{ number_format((float)$stats['average'], 2) }} / 5.0</span>
                    </div>
                </div>

                {{-- Chart Area --}}
                <div class="gform-chart-box">
                    @php
                        $maxOverall = max($stats['rating_5'], $stats['rating_4'], $stats['rating_3'], $stats['rating_2'], $stats['rating_1'], 1);
                    @endphp

                    <div class="gform-bar-list">
                        @foreach([
                            ['score' => 5, 'label' => '5 (พึงพอใจมากที่สุด)', 'count' => $stats['rating_5'], 'color' => '#10b981'],
                            ['score' => 4, 'label' => '4 (พึงพอใจมาก)', 'count' => $stats['rating_4'], 'color' => '#84cc16'],
                            ['score' => 3, 'label' => '3 (พึงพอใจปานกลาง)', 'count' => $stats['rating_3'], 'color' => '#eab308'],
                            ['score' => 2, 'label' => '2 (พึงพอใจน้อย)', 'count' => $stats['rating_2'], 'color' => '#f97316'],
                            ['score' => 1, 'label' => '1 (พึงพอใจน้อยที่สุด)', 'count' => $stats['rating_1'], 'color' => '#ef4444'],
                        ] as $bar)
                            @php
                                $percent = $stats['total'] > 0 ? round(($bar['count'] / $stats['total']) * 100, 1) : 0;
                                $widthPercent = ($bar['count'] / $maxOverall) * 100;
                            @endphp
                            <div class="gform-bar-row">
                                <div class="gform-bar-label">{{ $bar['label'] }}</div>
                                <div class="gform-bar-track">
                                    <div class="gform-bar-fill" style="width: {{ $widthPercent }}%; background: {{ $bar['color'] }};"></div>
                                </div>
                                <div class="gform-bar-stats">
                                    <strong>{{ number_format($bar['count']) }}</strong>
                                    <span class="gform-bar-pct">({{ $percent }}%)</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- 2. Question 2 - 5: คะแนนความพึงพอใจจำแนกตามหัวข้อย่อย --}}
            @php $qIndex = 2; @endphp
            @foreach($topicStats as $key => $ts)
                <div class="gform-card">
                    <div class="gform-q-header">
                        <div class="gform-q-number">ข้อที่ {{ $qIndex++ }}</div>
                        <div class="gform-q-title-wrap">
                            <h3 class="gform-q-title">ระดับความพึงพอใจ: {{ $ts['label'] }}</h3>
                            <span class="gform-q-count">{{ number_format($ts['count']) }} การตอบกลับ · เฉลี่ย {{ number_format((float)$ts['average'], 2) }} / 5.0</span>
                        </div>
                    </div>

                    <div class="gform-chart-box">
                        @php
                            $maxTopic = max($ts['rating_5'], $ts['rating_4'], $ts['rating_3'], $ts['rating_2'], $ts['rating_1'], 1);
                        @endphp
                        <div class="gform-bar-list">
                            @foreach([
                                ['score' => 5, 'label' => '5 (มากที่สุด)', 'count' => $ts['rating_5'], 'color' => '#10b981'],
                                ['score' => 4, 'label' => '4 (มาก)', 'count' => $ts['rating_4'], 'color' => '#84cc16'],
                                ['score' => 3, 'label' => '3 (ปานกลาง)', 'count' => $ts['rating_3'], 'color' => '#eab308'],
                                ['score' => 2, 'label' => '2 (น้อย)', 'count' => $ts['rating_2'], 'color' => '#f97316'],
                                ['score' => 1, 'label' => '1 (น้อยที่สุด)', 'count' => $ts['rating_1'], 'color' => '#ef4444'],
                            ] as $bar)
                                @php
                                    $percent = $ts['count'] > 0 ? round(($bar['count'] / $ts['count']) * 100, 1) : 0;
                                    $widthPercent = ($bar['count'] / $maxTopic) * 100;
                                @endphp
                                <div class="gform-bar-row">
                                    <div class="gform-bar-label">{{ $bar['label'] }}</div>
                                    <div class="gform-bar-track">
                                        <div class="gform-bar-fill" style="width: {{ $widthPercent }}%; background: {{ $bar['color'] }};"></div>
                                    </div>
                                    <div class="gform-bar-stats">
                                        <strong>{{ number_format($bar['count']) }}</strong>
                                        <span class="gform-bar-pct">({{ $percent }}%)</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- 3. Question 6: สถานะการระบุตัวตน (Privacy Status) --}}
            <div class="gform-card">
                <div class="gform-q-header">
                    <div class="gform-q-number">ข้อที่ 6</div>
                    <div class="gform-q-title-wrap">
                        <h3 class="gform-q-title">สถานะการเปิดเผยตัวตนในการตอบแบบประเมิน</h3>
                        <span class="gform-q-count">{{ number_format($stats['total']) }} การตอบกลับ</span>
                    </div>
                </div>

                <div class="gform-chart-box">
                    @php
                        $idPct = $stats['total'] > 0 ? round(($stats['identified'] / $stats['total']) * 100, 1) : 0;
                        $anonPct = $stats['total'] > 0 ? round(($stats['anonymous'] / $stats['total']) * 100, 1) : 0;
                    @endphp
                    <div class="gform-bar-list">
                        <div class="gform-bar-row">
                            <div class="gform-bar-label" style="display: flex; align-items: center; gap: 6px;">
                                <span style="width: 10px; height: 10px; border-radius: 50%; background: #3b82f6; display: inline-block;"></span>
                                ระบุตัวตน (เปิดเผยชื่อ-สกุล)
                            </div>
                            <div class="gform-bar-track">
                                <div class="gform-bar-fill" style="width: {{ $idPct }}%; background: #3b82f6;"></div>
                            </div>
                            <div class="gform-bar-stats">
                                <strong>{{ number_format($stats['identified']) }}</strong>
                                <span class="gform-bar-pct">({{ $idPct }}%)</span>
                            </div>
                        </div>

                        <div class="gform-bar-row">
                            <div class="gform-bar-label" style="display: flex; align-items: center; gap: 6px;">
                                <span style="width: 10px; height: 10px; border-radius: 50%; background: #a855f7; display: inline-block;"></span>
                                ไม่ระบุตัวตน (Anonymous)
                            </div>
                            <div class="gform-bar-track">
                                <div class="gform-bar-fill" style="width: {{ $anonPct }}%; background: #a855f7;"></div>
                            </div>
                            <div class="gform-bar-stats">
                                <strong>{{ number_format($stats['anonymous']) }}</strong>
                                <span class="gform-bar-pct">({{ $anonPct }}%)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- 4. Question 7: ความคิดเห็นและข้อเสนอแนะเพิ่มเติม (Text Responses) --}}
            @php
                $textFeedbacks = $feedbacks->filter(fn($f) => !empty(trim($f->comment ?? '')));
            @endphp
            <div class="gform-card">
                <div class="gform-q-header">
                    <div class="gform-q-number">ข้อที่ 7</div>
                    <div class="gform-q-title-wrap">
                        <h3 class="gform-q-title">ความคิดเห็นและข้อเสนอแนะเพิ่มเติมสำหรับกิจกรรมนี้</h3>
                        <span class="gform-q-count">{{ number_format($textFeedbacks->count()) }} ข้อความความคิดเห็น</span>
                    </div>
                </div>

                <div class="gform-text-answers-list">
                    @forelse($textFeedbacks as $tf)
                        @php
                            $ratingColor = match($tf->rating) {
                                5 => '#10b981',
                                4 => '#84cc16',
                                3 => '#eab308',
                                2 => '#f97316',
                                default => '#ef4444',
                            };
                        @endphp
                        <div class="gform-text-answer-item">
                            <div class="gform-text-answer-top">
                                <div class="gform-text-author">
                                    @if($tf->is_anonymous)
                                        <span class="gform-anon-badge">
                                            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            ไม่เปิดเผยตัวตน
                                        </span>
                                    @else
                                        <span class="gform-user-name">{{ $tf->user->full_name ?? 'ผู้เข้าร่วม' }}</span>
                                    @endif
                                    <span class="gform-rating-pill" style="color: {{ $ratingColor }}; border-color: {{ $ratingColor }}40;">
                                        <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                                        {{ $tf->rating }} / 5 คะแนน
                                    </span>
                                </div>
                                <span class="gform-text-time">{{ $tf->created_at->translatedFormat('d M Y H:i น.') }}</span>
                            </div>
                            <p class="gform-text-body">{{ $tf->comment }}</p>
                        </div>
                    @empty
                        <div style="text-align: center; padding: 2rem; color: #a1a1aa; font-style: italic;">
                            ไม่มีผู้ตอบที่กรอกความคิดเห็นเพิ่มเติม
                        </div>
                    @endforelse
                </div>
            </div>

        @endif

    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 2: แยกตามคำถาม (QUESTIONS TAB)                                   --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div id="tab-questions" class="gform-tab-pane">
        <div class="gform-card">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; padding: 16px 20px 0;">
                <label for="gformQuestionSelect" style="font-weight: 700; color: #f4f4f5; font-size: 0.95rem;">
                    เลือกคำถามที่ต้องการดูคำตอบ:
                </label>
                <select id="gformQuestionSelect" class="form-control" onchange="showQuestionView(this.value)" style="max-width: 420px; font-weight: 600;">
                    <option value="q1">ข้อที่ 1: ระดับความพึงพอใจภาพรวม</option>
                    <option value="q2">ข้อที่ 2: เนื้อหากิจกรรมและประโยชน์</option>
                    <option value="q3">ข้อที่ 3: วิทยากร / ผู้บรรยาย</option>
                    <option value="q4">ข้อที่ 4: สถานที่และโสตทัศนูปกรณ์</option>
                    <option value="q5">ข้อที่ 5: การบริหารจัดการและประสานงาน</option>
                    <option value="q6">ข้อที่ 6: สถานะการเปิดเผยตัวตน</option>
                    <option value="q7">ข้อที่ 7: ความคิดเห็นและข้อเสนอแนะเพิ่มเติม</option>
                </select>
            </div>

            <div id="questionViewContainer">
                <div class="gform-table-container">
                    <table class="gform-table">
                        <thead>
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th style="width: 220px;">ผู้ตอบ</th>
                                <th>คำตอบ / ผลการประเมิน</th>
                                <th style="width: 160px;">เวลาที่ส่ง</th>
                            </tr>
                        </thead>
                        <tbody id="questionTbody">
                            {{-- Populated dynamically via Javascript --}}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB 3: แยกตามบุคคล (INDIVIDUAL RESPONSES TAB)                        --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div id="tab-individual" class="gform-tab-pane">
        @if($feedbacks->count() > 0)
            {{-- Individual Navigator Bar --}}
            <div class="gform-card gform-individual-nav">
                <div class="gform-ind-pager">
                    <button class="gform-pager-btn" id="prevIndBtn" onclick="navigateIndividual(-1)" title="ก่อนหน้า">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>

                    <div class="gform-pager-info">
                        ผู้ตอบคนที่ <span id="currentIndIndex" class="gform-ind-num">1</span> จาก {{ $feedbacks->count() }}
                    </div>

                    <button class="gform-pager-btn" id="nextIndBtn" onclick="navigateIndividual(1)" title="ถัดไป">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </div>

                <div class="gform-ind-status">
                    <span id="indSubmitTime" class="text-xs text-muted"></span>
                </div>
            </div>

            {{-- Form Submission Sheet for Selected Individual --}}
            <div class="gform-card gform-submission-sheet">
                <div class="gform-ind-header">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <div id="indAvatar" class="gform-ind-avatar">A</div>
                        <div>
                            <h3 id="indName" class="gform-ind-name">ชื่อผู้ตอบ</h3>
                            <span id="indRole" class="gform-ind-meta">นักศึกษา</span>
                        </div>
                    </div>
                    <div id="indRatingBadge" class="gform-ind-score-badge">5.0 / 5.0 คะแนน</div>
                </div>

                <div class="gform-ind-body">
                    {{-- Q1 --}}
                    <div class="gform-ind-item">
                        <div class="gform-ind-label">1. ความพึงพอใจภาพรวมต่อกิจกรรม</div>
                        <div class="gform-ind-value" id="indQ1">5 คะแนน</div>
                    </div>

                    {{-- Q2 --}}
                    <div class="gform-ind-item">
                        <div class="gform-ind-label">2. ด้านเนื้อหากิจกรรมและประโยชน์ที่ได้รับ</div>
                        <div class="gform-ind-value" id="indQ2">-</div>
                    </div>

                    {{-- Q3 --}}
                    <div class="gform-ind-item">
                        <div class="gform-ind-label">3. ด้านวิทยากร / ผู้บรรยาย / ผู้ดำเนินกิจกรรม</div>
                        <div class="gform-ind-value" id="indQ3">-</div>
                    </div>

                    {{-- Q4 --}}
                    <div class="gform-ind-item">
                        <div class="gform-ind-label">4. ด้านสถานที่ / โสตทัศนูปกรณ์ / ระบบดิจิทัล</div>
                        <div class="gform-ind-value" id="indQ4">-</div>
                    </div>

                    {{-- Q5 --}}
                    <div class="gform-ind-item">
                        <div class="gform-ind-label">5. ด้านการบริหารจัดการและการประสานงาน</div>
                        <div class="gform-ind-value" id="indQ5">-</div>
                    </div>

                    {{-- Q6 --}}
                    <div class="gform-ind-item">
                        <div class="gform-ind-label">6. สถานะการเปิดเผยตัวตน</div>
                        <div class="gform-ind-value" id="indQ6">เปิดเผยตัวตน</div>
                    </div>

                    {{-- Q7 --}}
                    <div class="gform-ind-item">
                        <div class="gform-ind-label">7. ความคิดเห็นและข้อเสนอแนะเพิ่มเติม</div>
                        <div class="gform-ind-value" id="indQ7" style="white-space: pre-wrap; font-style: normal; color: #f4f4f5;">-</div>
                    </div>
                </div>
            </div>
        @else
            <div class="gform-card" style="text-align: center; padding: 3rem;">
                <p style="color: #a1a1aa; margin: 0;">ไม่มีข้อมูลสำหรับแสดงรายบุคคล</p>
            </div>
        @endif
    </div>

</div>

{{-- ── Client-side Feedbacks JSON for Fast Interactive Navigation ───── --}}
<script>
const FEEDBACKS_DATA = {!! json_encode($clientFeedbacks ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};

let currentInd = 0;

function switchGformTab(tabName, btn) {
    document.querySelectorAll('.gform-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.gform-tab-pane').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    const pane = document.getElementById('tab-' + tabName);
    if (pane) pane.classList.add('active');

    if (tabName === 'questions') {
        const select = document.getElementById('gformQuestionSelect');
        if (select) showQuestionView(select.value);
    }
    if (tabName === 'individual') {
        renderIndividual(currentInd);
    }
}

function showQuestionView(qKey) {
    const tbody = document.getElementById('questionTbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (FEEDBACKS_DATA.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;color:#a1a1aa;padding:2rem;">ไม่มีข้อมูล</td></tr>';
        return;
    }

    FEEDBACKS_DATA.forEach((fb, idx) => {
        let answerText = '-';
        if (qKey === 'q1') {
            answerText = getScorePill(fb.rating);
        } else if (qKey === 'q2') {
            const v = fb.ratings?.content || '-';
            answerText = v !== '-' ? getScorePill(v) : '<span class="text-muted">ไม่ได้ระบุ</span>';
        } else if (qKey === 'q3') {
            const v = fb.ratings?.speaker || '-';
            answerText = v !== '-' ? getScorePill(v) : '<span class="text-muted">ไม่ได้ระบุ</span>';
        } else if (qKey === 'q4') {
            const v = fb.ratings?.location || '-';
            answerText = v !== '-' ? getScorePill(v) : '<span class="text-muted">ไม่ได้ระบุ</span>';
        } else if (qKey === 'q5') {
            const v = fb.ratings?.organization || '-';
            answerText = v !== '-' ? getScorePill(v) : '<span class="text-muted">ไม่ได้ระบุ</span>';
        } else if (qKey === 'q6') {
            answerText = fb.is_anonymous 
                ? '<span class="gform-anon-badge"><svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg> ไม่เปิดเผยตัวตน</span>' 
                : '<span class="gform-user-badge"><svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> เปิดเผยตัวตน</span>';
        } else if (qKey === 'q7') {
            answerText = fb.comment 
                ? `<div style="background:#141416;padding:8px 12px;border-radius:6px;border:1px solid #27272a;color:#f4f4f5;">${escapeHtml(fb.comment)}</div>` 
                : '<span class="text-muted" style="font-style:italic;">(ไม่มีข้อความเสนอแนะ)</span>';
        }

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td style="color:#a1a1aa;font-weight:600;">${idx + 1}</td>
            <td>
                <strong>${escapeHtml(fb.user_name)}</strong>
                ${!fb.is_anonymous && fb.student_code !== '-' ? `<br><small class="text-muted">${escapeHtml(fb.student_code)}</small>` : ''}
            </td>
            <td>${answerText}</td>
            <td style="color:#a1a1aa;font-size:0.8rem;">${fb.time_thai}</td>
        `;
        tbody.appendChild(tr);
    });
}

function getScorePill(score) {
    const s = parseInt(score, 10) || 0;
    const color = s >= 4 ? '#10b981' : (s === 3 ? '#eab308' : '#ef4444');
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        const fill = i <= s ? '#fbbf24' : '#3f3f46';
        stars += `<svg width="13" height="13" fill="${fill}" viewBox="0 0 20 20" style="display:inline-block;vertical-align:middle;margin-right:1px;"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>`;
    }
    return `<span style="display:inline-flex;align-items:center;gap:4px;">${stars} <strong style="color:${color};margin-left:4px;">(${s}/5)</strong></span>`;
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str).replace(/[&<>"']/g, function(m) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m];
    });
}

function navigateIndividual(delta) {
    const nextIdx = currentInd + delta;
    if (nextIdx >= 0 && nextIdx < FEEDBACKS_DATA.length) {
        currentInd = nextIdx;
        renderIndividual(currentInd);
    }
}

function renderIndividual(idx) {
    if (FEEDBACKS_DATA.length === 0) return;
    const fb = FEEDBACKS_DATA[idx];

    document.getElementById('currentIndIndex').textContent = idx + 1;
    document.getElementById('prevIndBtn').disabled = (idx === 0);
    document.getElementById('nextIndBtn').disabled = (idx === FEEDBACKS_DATA.length - 1);

    document.getElementById('indSubmitTime').textContent = 'ส่งเมื่อ ' + fb.time_thai;
    document.getElementById('indName').textContent = fb.user_name;
    document.getElementById('indRole').textContent = fb.is_anonymous ? 'นิรนาม (PDPA Safe)' : 'รหัสนักศึกษา/อีเมล: ' + fb.student_code;

    const initial = fb.is_anonymous ? '?' : fb.user_name.charAt(0).toUpperCase();
    document.getElementById('indAvatar').textContent = initial;

    const badge = document.getElementById('indRatingBadge');
    badge.textContent = `${fb.rating}.0 / 5.0 คะแนน`;

    document.getElementById('indQ1').innerHTML = getScorePill(fb.rating);
    
    const q2Val = fb.ratings?.content;
    document.getElementById('indQ2').innerHTML = q2Val ? getScorePill(q2Val) : '<span class="text-muted">ไม่ได้ระบุ</span>';
    
    const q3Val = fb.ratings?.speaker;
    document.getElementById('indQ3').innerHTML = q3Val ? getScorePill(q3Val) : '<span class="text-muted">ไม่ได้ระบุ</span>';
    
    const q4Val = fb.ratings?.location;
    document.getElementById('indQ4').innerHTML = q4Val ? getScorePill(q4Val) : '<span class="text-muted">ไม่ได้ระบุ</span>';
    
    const q5Val = fb.ratings?.organization;
    document.getElementById('indQ5').innerHTML = q5Val ? getScorePill(q5Val) : '<span class="text-muted">ไม่ได้ระบุ</span>';

    document.getElementById('indQ6').innerHTML = fb.is_anonymous 
        ? '<span class="gform-anon-badge"><svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg> ไม่เปิดเผยตัวตน</span>' 
        : '<span class="gform-user-badge"><svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg> เปิดเผยตัวตน</span>';

    document.getElementById('indQ7').textContent = fb.comment || '(ไม่มีข้อความเสนอแนะเพิ่มเติม)';
}

document.addEventListener('DOMContentLoaded', function() {
    if (FEEDBACKS_DATA.length > 0) {
        renderIndividual(0);
    }
});
</script>

{{-- ── 4. Google Forms Theme Styles ─────────────────────────────────── --}}
<style>
.gform-container {
    max-width: 920px;
    margin: 0 auto;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* ── Nav Bar ── */
.gform-nav-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.gform-back-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #a1a1aa;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 8px;
    background: #18181b;
    border: 1px solid #27272a;
    transition: all .2s;
}
.gform-back-btn:hover {
    color: #f4f4f5;
    background: #27272a;
}

.gform-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.gform-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 7px 14px;
    border-radius: 8px;
    font-size: 0.825rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all .2s;
}
.gform-btn-secondary {
    background: #1c1c1f;
    border: 1px solid #27272a;
    color: #d4d4d8;
}
.gform-btn-secondary:hover {
    background: #27272a;
    color: #fff;
}

/* ── Google Forms Card ── */
.gform-card {
    background: #1c1c1f;
    border: 1px solid #27272a;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
    overflow: hidden;
}

/* ── Header Card ── */
.gform-header-card {
    position: relative;
}
.gform-header-accent {
    height: 10px;
    background: linear-gradient(90deg, #ea580c, #f97316, #fb923c);
}
.gform-header-body {
    padding: 24px 28px 20px;
}
.gform-badge-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 12px;
}
.gform-category-badge {
    background: rgba(234, 88, 12, 0.15);
    color: #f97316;
    border: 1px solid rgba(234, 88, 12, 0.3);
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
}
.gform-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(16, 185, 129, 0.12);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.25);
    font-size: 0.75rem;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 999px;
}
.gform-status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 8px #10b981;
}

.gform-title {
    font-size: 1.65rem;
    font-weight: 800;
    color: #f4f4f5;
    margin: 0 0 6px;
    letter-spacing: -0.02em;
    line-height: 1.3;
}
.gform-subtitle {
    font-size: 0.875rem;
    color: #a1a1aa;
    margin: 0 0 20px;
    line-height: 1.5;
}

.gform-meta-strip {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 14px;
    padding: 14px 18px;
    background: #141416;
    border: 1px solid #27272a;
    border-radius: 10px;
}
.gform-meta-item {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.gform-meta-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #71717a;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.gform-meta-val {
    font-size: 1.35rem;
    font-weight: 800;
    color: #f4f4f5;
}
.gform-meta-val.primary { color: #f97316; }
.gform-meta-val.accent { color: #fbbf24; }
.gform-meta-val small { font-size: 0.8rem; font-weight: 600; color: #a1a1aa; }

/* ── Google Forms Tabs ── */
.gform-tabs {
    display: flex;
    border-top: 1px solid #27272a;
    background: #18181b;
}
.gform-tab {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 16px;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    color: #a1a1aa;
    font-size: 0.875rem;
    font-weight: 700;
    cursor: pointer;
    transition: all .2s;
}
.gform-tab:hover {
    color: #f4f4f5;
    background: rgba(255, 255, 255, 0.02);
}
.gform-tab.active {
    color: #ea580c;
    border-bottom-color: #ea580c;
    background: rgba(234, 88, 12, 0.04);
}

/* ── Tab Panes ── */
.gform-tab-pane {
    display: none;
    flex-direction: column;
    gap: 16px;
}
.gform-tab-pane.active {
    display: flex;
}

/* ── Question Card ── */
.gform-q-header {
    padding: 18px 24px 14px;
    border-bottom: 1px solid #27272a;
    background: #18181b;
}
.gform-q-number {
    font-size: 0.725rem;
    font-weight: 800;
    color: #ea580c;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 4px;
}
.gform-q-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #f4f4f5;
    margin: 0 0 4px;
}
.gform-q-count {
    font-size: 0.775rem;
    color: #a1a1aa;
    font-weight: 500;
}

/* ── Chart Area (Google Forms Bar Distribution) ── */
.gform-chart-box {
    padding: 20px 24px;
}
.gform-bar-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.gform-bar-row {
    display: grid;
    grid-template-columns: 200px 1fr 90px;
    align-items: center;
    gap: 14px;
}
@media (max-width: 640px) {
    .gform-bar-row {
        grid-template-columns: 1fr;
        gap: 6px;
    }
}
.gform-bar-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #d4d4d8;
}
.gform-bar-track {
    background: #141416;
    border: 1px solid #27272a;
    border-radius: 999px;
    height: 16px;
    overflow: hidden;
}
.gform-bar-fill {
    height: 100%;
    border-radius: 999px;
    transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
}
.gform-bar-stats {
    text-align: right;
    font-size: 0.825rem;
    color: #f4f4f5;
}
.gform-bar-pct {
    color: #a1a1aa;
    font-weight: 500;
    margin-left: 3px;
}

/* ── Text Answers List ── */
.gform-text-answers-list {
    padding: 16px 24px 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.gform-text-answer-item {
    background: #141416;
    border: 1px solid #27272a;
    border-radius: 10px;
    padding: 14px 16px;
}
.gform-text-answer-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 8px;
}
.gform-text-author {
    display: flex;
    align-items: center;
    gap: 8px;
}
.gform-user-name {
    font-weight: 700;
    color: #f4f4f5;
    font-size: 0.875rem;
}
.gform-anon-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(168, 85, 247, 0.15);
    color: #c084fc;
    border: 1px solid rgba(168, 85, 247, 0.3);
    font-size: 0.725rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
}
.gform-user-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
    font-size: 0.725rem;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 4px;
}
.gform-rating-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 4px;
    border: 1px solid;
}
.gform-text-time {
    font-size: 0.75rem;
    color: #71717a;
}
.gform-text-body {
    margin: 0;
    font-size: 0.9rem;
    color: #d4d4d8;
    line-height: 1.6;
    white-space: pre-wrap;
    word-break: break-word;
}

/* ── Tab 2: Table ── */
.gform-table-container {
    overflow-x: auto;
}
.gform-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}
.gform-table th {
    background: #141416;
    color: #a1a1aa;
    text-align: left;
    padding: 12px 16px;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 1px solid #27272a;
}
.gform-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #27272a;
    color: #f4f4f5;
}
.gform-table tr:hover td {
    background: #242428;
}

/* ── Tab 3: Individual ── */
.gform-individual-nav {
    padding: 14px 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #18181b;
}
.gform-ind-pager {
    display: flex;
    align-items: center;
    gap: 12px;
}
.gform-pager-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #27272a;
    border: 1px solid #3f3f46;
    color: #f4f4f5;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all .2s;
}
.gform-pager-btn:hover:not(:disabled) {
    background: #ea580c;
    border-color: #ea580c;
}
.gform-pager-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
.gform-pager-info {
    font-size: 0.9rem;
    font-weight: 600;
    color: #f4f4f5;
}
.gform-ind-num {
    color: #ea580c;
    font-weight: 800;
}

.gform-submission-sheet {
    padding: 24px;
}
.gform-ind-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 18px;
    border-bottom: 1px solid #27272a;
    margin-bottom: 20px;
}
.gform-ind-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ea580c, #f97316);
    color: #fff;
    font-size: 1.2rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3);
}
.gform-ind-name {
    font-size: 1.1rem;
    font-weight: 800;
    color: #f4f4f5;
    margin: 0 0 2px;
}
.gform-ind-meta {
    font-size: 0.8rem;
    color: #a1a1aa;
}
.gform-ind-score-badge {
    background: rgba(234, 88, 12, 0.15);
    border: 1px solid rgba(234, 88, 12, 0.3);
    color: #f97316;
    font-size: 1.15rem;
    font-weight: 800;
    padding: 6px 14px;
    border-radius: 8px;
}

.gform-ind-body {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.gform-ind-item {
    background: #141416;
    border: 1px solid #27272a;
    border-radius: 8px;
    padding: 14px 18px;
}
.gform-ind-label {
    font-size: 0.825rem;
    font-weight: 700;
    color: #a1a1aa;
    margin-bottom: 6px;
}
.gform-ind-value {
    font-size: 0.95rem;
    font-weight: 600;
    color: #f4f4f5;
}

/* ── Explicit Light Theme Overrides ── */
html[data-theme="light"] .gform-card {
    background: #ffffff;
    border-color: #e2e8f0;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}
html[data-theme="light"] .gform-tabs,
html[data-theme="light"] .gform-q-header,
html[data-theme="light"] .gform-individual-nav {
    background: #ffffff;
    border-color: #f1f5f9;
}
html[data-theme="light"] .gform-title,
html[data-theme="light"] .gform-q-title,
html[data-theme="light"] .gform-meta-val,
html[data-theme="light"] .gform-bar-stats,
html[data-theme="light"] .gform-ind-name,
html[data-theme="light"] .gform-ind-value,
html[data-theme="light"] .gform-pager-info,
html[data-theme="light"] .gform-table td {
    color: #0f172a;
}
html[data-theme="light"] .gform-subtitle,
html[data-theme="light"] .gform-q-count,
html[data-theme="light"] .gform-meta-label,
html[data-theme="light"] .gform-tab,
html[data-theme="light"] .gform-bar-pct,
html[data-theme="light"] .gform-ind-label,
html[data-theme="light"] .gform-ind-meta {
    color: #64748b;
}
html[data-theme="light"] .gform-bar-label,
html[data-theme="light"] .gform-text-body {
    color: #334155;
}
html[data-theme="light"] .gform-user-name {
    color: #0f172a;
}
html[data-theme="light"] .gform-meta-strip,
html[data-theme="light"] .gform-text-answer-item,
html[data-theme="light"] .gform-ind-item {
    background: #f8fafc;
    border-color: #e2e8f0;
}
html[data-theme="light"] .gform-bar-track {
    background: #f1f5f9;
    border-color: #e2e8f0;
}
html[data-theme="light"] .gform-tab:hover {
    color: #0f172a;
    background: #f8fafc;
}
html[data-theme="light"] .gform-tab.active {
    color: #ea580c;
    border-bottom-color: #ea580c;
    background: #fff7ed;
}
html[data-theme="light"] .gform-table th {
    background: #f8fafc;
    color: #475569;
    border-color: #e2e8f0;
}
html[data-theme="light"] .gform-table td {
    border-color: #f1f5f9;
}
html[data-theme="light"] .gform-table tr:hover td {
    background: #f8fafc;
}
html[data-theme="light"] .gform-btn-secondary {
    background: #ffffff;
    border-color: #e2e8f0;
    color: #475569;
}
html[data-theme="light"] .gform-btn-secondary:hover {
    background: #f8fafc;
    color: #0f172a;
}
html[data-theme="light"] .gform-pager-btn {
    background: #ffffff;
    border-color: #e2e8f0;
    color: #334155;
}
html[data-theme="light"] .gform-pager-btn:hover:not(:disabled) {
    background: #ea580c;
    border-color: #ea580c;
    color: #ffffff;
}

/* ── Print Optimization ── */
@media print {
    .admin-topbar, .sb-sidebar, .gform-nav-bar, .gform-tabs {
        display: none !important;
    }
    .sb-shell {
        padding: 0 !important;
        background: #fff !important;
    }
    .sb-content {
        margin: 0 !important;
    }
    .gform-card {
        border: 1px solid #ddd !important;
        background: #fff !important;
        color: #000 !important;
        box-shadow: none !important;
        page-break-inside: avoid;
    }
    .gform-title, .gform-q-title, .gform-bar-label, .gform-bar-stats {
        color: #000 !important;
    }
    .gform-tab-pane {
        display: block !important;
    }
    #tab-questions, #tab-individual {
        display: none !important;
    }
}
</style>
@endsection
