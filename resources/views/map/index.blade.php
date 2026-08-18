@extends('layouts.app')
@section('title', 'แผนที่กิจกรรมและงาน (WebGL 3D) - UNI Activity')

@section('content')
<div class="map-explorer-wrapper">
    {{-- ── 1. Floating Top Bar: Google Maps Search & Quick Filters ── --}}
    <div class="gmap-floating-top" id="gmapFloatingTop">
        {{-- Search Pill Bar with Back Button --}}
        <div class="gmap-search-pill">
            <button type="button" class="gmap-back-btn" onclick="goBackOrHome()" title="ย้อนกลับ">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
            </button>
            <input type="text" id="mapSearchInput" class="gmap-search-input" placeholder="ค้นหากิจกรรม, หางาน, อาคารสถานที่..." autocomplete="off">
            <button type="button" id="mapSearchClearBtn" class="gmap-search-clear" onclick="clearMapSearch()" style="display:none;" title="ล้างการค้นหา">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            <div class="gmap-search-divider"></div>
            <button type="button" class="gmap-list-toggle-btn" onclick="toggleNearbyDrawer()" title="เปิดรายการสถานที่">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                </svg>
            </button>
        </div>

        {{-- Horizontal Scrollable Category Chips --}}
        <div class="gmap-chips-scroll">
            <button type="button" class="gmap-chip active" data-type="all" onclick="filterType('all', this)">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                </svg>
                <span>ทั้งหมด</span>
            </button>
            <button type="button" class="gmap-chip" data-type="activity" onclick="filterType('activity', this)">
                <span class="gmap-chip-dot dot-orange"></span>
                <span>กิจกรรม</span>
            </button>
            <button type="button" class="gmap-chip" data-type="job" onclick="filterType('job', this)">
                <span class="gmap-chip-dot dot-blue"></span>
                <span>หางาน / Part-time</span>
            </button>
            <button type="button" class="gmap-chip" data-type="landmark" onclick="filterType('landmark', this)">
                <span class="gmap-chip-dot dot-green"></span>
                <span>อาคาร / จุดสำคัญ</span>
            </button>
            <button type="button" class="gmap-chip" data-radius="2" onclick="toggleQuickRadius(2, this)">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>ใกล้ฉัน &lt; 2 กม.</span>
            </button>
            <button type="button" class="gmap-chip" id="chip-heat" onclick="toggleHeatmapChip(this)">
                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z"/>
                </svg>
                <span>ความหนาแน่น</span>
            </button>
        </div>
    </div>

    {{-- ── 2. Active Turn-by-Turn Navigation Header Banner ── --}}
    <div id="gmapNavBanner" class="gmap-nav-banner" style="display:none;">
        <div class="gmap-nav-banner-top">
            <div class="gmap-nav-banner-icon" id="gmapNavTurnIcon">
                <svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>
                </svg>
            </div>
            <div class="gmap-nav-banner-info">
                <div class="gmap-nav-turn-dist" id="gmapNavTurnDist">กำลังคำนวณ...</div>
                <div class="gmap-nav-instruction" id="gmapNavInstruction">ตรงไปตามเส้นทาง</div>
            </div>
            <div class="gmap-nav-actions">
                <button type="button" class="gmap-nav-voice-btn" id="gmapNavVoiceBtn" onclick="toggleVoiceGuidance()" title="เปิด/ปิดเสียงนำทาง">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                        <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                        <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                    </svg>
                </button>
                <button type="button" class="gmap-nav-exit-btn" onclick="clearNavigationRoute()" title="สิ้นสุดการนำทาง">
                    <span>สิ้นสุด</span>
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
        <div class="gmap-nav-banner-bottom">
            <div class="gmap-nav-total-stat">
                <span class="stat-highlight" id="gmapNavTotalTime">5 นาที</span>
                <span class="stat-bullet">•</span>
                <span id="gmapNavTotalDist">3.3 กม.</span>
                <span class="stat-bullet">•</span>
                <span id="gmapNavEtaClock">ถึงประมาณ --:-- น.</span>
            </div>
            <div class="gmap-nav-dest-label" id="gmapNavDestLabel">มุ่งหน้าไปยัง...</div>
        </div>
    </div>

    {{-- ── 3. Main Map Canvas (WebGL 3D Container) ── --}}
    <div class="map-canvas-container">
        <div id="unifiedMap" style="width:100%;height:100%;"></div>

        {{-- Floating Action Buttons (Right Side) --}}
        <div class="gmap-fab-column">
            {{-- 3D Mode Toggle FAB --}}
            <button type="button" class="gmap-fab" id="btnToggle3D" onclick="toggle3DView()" title="สลับมุมมอง 3 มิติ / 2 มิติ">
                <span style="font-weight:900;font-size:0.8rem;letter-spacing:-0.5px;">3D</span>
            </button>

            {{-- Layer Switcher FAB --}}
            <button type="button" class="gmap-fab" onclick="toggleLayerSheet()" title="เปลี่ยนรูปแบบแผนที่">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </button>

            {{-- GPS Current Location FAB --}}
            <button type="button" class="gmap-fab gmap-fab-gps" id="btnGpsLocate" onclick="locateUserAndCenter()" title="ระบุตำแหน่งของฉัน">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </button>

            {{-- Floating Nearby Places Counter FAB --}}
            <button type="button" class="gmap-fab gmap-fab-badge" onclick="toggleNearbyDrawer()" title="รายการสถานที่ใกล้ฉัน">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                <span id="fabPlaceCountBadge" class="gmap-fab-badge-count">0</span>
            </button>
        </div>

        {{-- Layer Switcher Modal Sheet --}}
        <div id="gmapLayerSheet" class="gmap-layer-sheet" style="display:none;">
            <div class="gmap-layer-header">
                <span>รูปแบบแผนที่ WebGL 3D</span>
                <button type="button" onclick="toggleLayerSheet()" class="gmap-icon-btn" title="ปิด">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="gmap-layer-grid">
                <div class="gmap-layer-card active" id="layerCard-streets" onclick="switchMapLayer('streets')">
                    <div class="gmap-layer-preview preview-streets"></div>
                    <span>ค่าเริ่มต้น</span>
                </div>
                <div class="gmap-layer-card" id="layerCard-satellite" onclick="switchMapLayer('satellite')">
                    <div class="gmap-layer-preview preview-satellite"></div>
                    <span>ดาวเทียม</span>
                </div>
                <div class="gmap-layer-card" id="layerCard-terrain" onclick="switchMapLayer('terrain')">
                    <div class="gmap-layer-preview preview-terrain"></div>
                    <span>ภูมิประเทศ</span>
                </div>
            </div>
        </div>

        {{-- ── 4. Interactive Google Maps Bottom Sheet ── --}}
        <div id="mapBottomSheet" class="gmap-bottom-sheet" style="display:none;">
            {{-- Sheet Handle & Drag Zone --}}
            <div class="gmap-sheet-handle-zone" id="gmapSheetHandleZone" onclick="toggleBottomSheetExpand()">
                <div class="gmap-sheet-handle"></div>
            </div>

            <div class="gmap-sheet-inner">
                {{-- Header: Image + Tags + Title + Subtitle + Close --}}
                <div class="gmap-card-head">
                    <div class="gmap-card-head-left">
                        {{-- Thumbnail --}}
                        <div id="bs-img-wrap" class="gmap-thumb">
                            <img id="bs-img" src="" alt="Thumbnail" style="display:none;">
                            <div id="bs-icon-fallback" class="gmap-thumb-fallback">
                                <svg id="bs-fallback-svg" width="28" height="28" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                </svg>
                            </div>
                        </div>

                        {{-- Metadata & Titles --}}
                        <div class="gmap-head-content">
                            <div class="gmap-tags-row">
                                <span id="bs-badge" class="gmap-badge-tag badge-orange"></span>
                                <span id="bs-distance" class="gmap-distance-chip">
                                    <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                                    </svg>
                                    <span id="bs-dist-val">-</span>
                                </span>
                            </div>
                            <h2 id="bs-title" class="gmap-sheet-title"></h2>
                            <div id="bs-subtitle-row" class="gmap-sheet-subtitle">
                                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                </svg>
                                <span id="bs-subtitle"></span>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="gmap-sheet-close" onclick="closeBottomSheet()" title="ปิด">
                        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Primary Action Row (Start Nav + Choose Route + Native App Detection + Share) --}}
                <div class="gmap-sheet-action-row">
                    <button type="button" id="bs-start-nav-btn" onclick="startNavigationToActive()" class="gmap-btn-start-nav flex-1">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                        <span>เริ่มนำทาง</span>
                    </button>

                    <button type="button" id="bs-route-options-btn" onclick="openRouteSelectorForActive()" class="gmap-btn-route-select" title="เลือกเส้นทาง / โหมดเดินทาง">
                        <svg width="17" height="17" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        <span>เส้นทาง</span>
                    </button>

                    {{-- Native Map Apps Trigger (Google Maps / Apple Maps on iOS) --}}
                    <a id="bs-native-nav-btn" href="#" target="_blank" class="gmap-btn-native-detect" title="เปิดใน Google Maps">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                        </svg>
                        <span id="bs-native-app-label">Google Maps</span>
                    </a>

                    <button type="button" class="gmap-btn-icon" onclick="shareActiveLocation()" title="แชร์สถานที่">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                        </svg>
                    </button>
                </div>

                {{-- ETA Summary Grid --}}
                <div class="gmap-eta-grid">
                    <div class="gmap-eta-card card-walk" onclick="selectModeFromCard('walk')">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-orange-600 dark:text-orange-400">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span>เดินเท้า</span>
                        </div>
                        <div class="gmap-eta-value" id="bs-walk-time">-</div>
                    </div>
                    <div class="gmap-eta-card card-drive" onclick="selectModeFromCard('drive')">
                        <div class="flex items-center gap-1.5 text-xs font-bold text-blue-600 dark:text-blue-400">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9L2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                            <span>รถยนต์ / มอไซค์</span>
                        </div>
                        <div class="gmap-eta-value" id="bs-drive-time">-</div>
                    </div>
                </div>

                {{-- Detailed Section --}}
                <div class="gmap-info-section">
                    <div id="bs-time-wrap" class="gmap-info-row">
                        <div class="gmap-info-icon">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="gmap-info-text">
                            <div class="font-bold text-xs text-muted">วันและเวลาจัดกิจกรรม</div>
                            <div id="bs-time-val" class="font-semibold">-</div>
                        </div>
                    </div>

                    <div id="bs-quota-wrap" class="gmap-info-row">
                        <div class="gmap-info-icon">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <div class="gmap-info-text">
                            <div class="font-bold text-xs text-muted">จำนวนผู้เข้าร่วม / รับสมัคร</div>
                            <div id="bs-quota-val" class="font-semibold">-</div>
                        </div>
                    </div>

                    <div id="bs-desc-wrap" class="gmap-info-desc">
                        <div class="font-bold text-xs text-muted mb-1">รายละเอียด</div>
                        <div id="bs-desc-val" class="text-sm leading-relaxed text-secondary">-</div>
                    </div>
                </div>

                {{-- Footer Button: View Details Link --}}
                <div class="gmap-sheet-footer">
                    <a id="bs-detail-link" href="#" class="gmap-btn-detail">
                        <span>ดูรายละเอียดกิจกรรมเต็ม</span>
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- ── 5. Route Selection & Alternatives Modal Sheet ── --}}
        <div id="gmapRouteSelectorSheet" class="gmap-route-sheet" style="display:none;">
            <div class="gmap-route-sheet-header">
                <div>
                    <div class="gmap-route-subtitle">คำนวณเส้นทางถนนจริง (OSRM)</div>
                    <div class="gmap-route-title" id="gmapRouteDestTitle">เลือกเส้นทาง</div>
                </div>
                <button type="button" class="gmap-icon-btn" onclick="closeRouteSelector()" title="ปิด">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Mode Selector Chips --}}
            <div class="gmap-travel-modes-row">
                <button type="button" class="gmap-mode-chip active" data-mode="drive" onclick="selectTravelMode('drive', this)">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9L2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/></svg>
                    <span>รถยนต์</span>
                </button>
                <button type="button" class="gmap-mode-chip" data-mode="moto" onclick="selectTravelMode('moto', this)">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="6" cy="17" r="3"/><circle cx="18" cy="17" r="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 17h6m-3-6l2 6m-5-8h4l2 3"/></svg>
                    <span>มอไซค์</span>
                </button>
                <button type="button" class="gmap-mode-chip" data-mode="walk" onclick="selectTravelMode('walk', this)">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    <span>เดิน</span>
                </button>
                <button type="button" class="gmap-mode-chip" data-mode="bike" onclick="selectTravelMode('bike', this)">
                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="5.5" cy="17.5" r="3.5"/><circle cx="18.5" cy="17.5" r="3.5"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 6h-3l-3 7h6l2-4h2"/></svg>
                    <span>จักรยาน</span>
                </button>
            </div>

            {{-- Real Road Alternatives List --}}
            <div id="gmapRouteCardsList" class="gmap-route-cards-list">
                {{-- Dynamic route cards inserted via JS --}}
            </div>

            <button type="button" class="gmap-btn-start-nav w-full" onclick="startNavigationWithSelectedRoute()">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
                <span id="btnStartSelectedRouteText">เริ่มนำทางตามเส้นทางนี้</span>
            </button>
        </div>

        {{-- ── 6. Nearby Places Drawer ── --}}
        <div id="gmapNearbyDrawer" class="gmap-nearby-drawer" style="display:none;">
            <div class="gmap-drawer-header">
                <div class="flex items-center gap-2">
                    <div class="gmap-title-icon">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                    </div>
                    <div>
                        <div class="font-bold" style="font-size:0.95rem;">สถานที่ทั้งหมดในแผนที่</div>
                        <div class="text-xs text-muted" id="drawerCountLabel">0 แห่ง</div>
                    </div>
                </div>
                <button type="button" class="gmap-icon-btn" onclick="toggleNearbyDrawer()" title="ปิด">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div id="gmapNearbyList" class="gmap-drawer-body">
                <div style="padding:2rem;text-align:center;color:#94a3b8;">กำลังโหลดรายการสถานที่...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- MapLibre GL JS (WebGL 3D Engine) & Supercluster --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.css" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.7.1/maplibre-gl.js"></script>
<script src="https://cdn.jsdelivr.net/npm/supercluster@8.0.1/dist/supercluster.min.js"></script>

<style>
/* ── Fullscreen Map App Layout ── */
header.navbar,
.bottom-nav,
#chatFloatWidget,
#notif-banner,
footer {
    display: none !important;
}

html, body {
    overflow: hidden !important;
    height: 100vh !important;
    height: 100dvh !important;
    margin: 0 !important;
    padding: 0 !important;
    width: 100% !important;
}

.container {
    max-width: 100% !important;
    width: 100% !important;
    height: 100vh !important;
    height: 100dvh !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
}

.map-explorer-wrapper {
    position: relative;
    width: 100% !important;
    height: 100vh !important;
    height: 100dvh !important;
    min-height: 100% !important;
    border-radius: 0 !important;
    overflow: hidden;
    background: #0f172a;
    border: none !important;
    box-shadow: none !important;
    display: flex;
    flex-direction: column;
    margin: 0 !important;
}

.map-canvas-container {
    position: relative;
    flex: 1;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

/* MapLibre Canvas Container */
.maplibregl-canvas-container, .maplibregl-canvas {
    width: 100% !important;
    height: 100% !important;
    outline: none !important;
}
.maplibregl-ctrl-attrib {
    font-size: 10px !important;
    background-color: rgba(255, 255, 255, 0.7) !important;
    backdrop-filter: blur(4px);
    border-radius: 6px;
    margin: 6px;
    padding: 2px 6px;
}
html[data-theme="dark"] .maplibregl-ctrl-attrib {
    background-color: rgba(24, 24, 27, 0.8) !important;
    color: #a1a1aa !important;
}

/* ── 1. Floating Top Bar ── */
.gmap-floating-top {
    position: absolute;
    top: 14px;
    left: 14px;
    right: 14px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
}
@media (min-width: 768px) {
    .gmap-floating-top {
        left: 50%;
        transform: translateX(-50%);
        width: 440px;
    }
}

/* Google Maps Style Search Pill */
.gmap-search-pill {
    pointer-events: auto;
    display: flex;
    align-items: center;
    background: #ffffff;
    border-radius: 28px;
    padding: 6px 14px 6px 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.16), 0 1px 4px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.06);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.gmap-search-pill:focus-within {
    box-shadow: 0 6px 24px rgba(0,0,0,0.22);
    border-color: #ea580c;
}
.gmap-back-btn {
    background: none;
    border: none;
    color: #475569;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 6px;
    border-radius: 50%;
    transition: background 0.15s;
}
.gmap-back-btn:hover {
    background: #f1f5f9;
    color: #0f172a;
}
.gmap-search-input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    padding: 6px 10px;
    font-size: 0.95rem;
    font-weight: 500;
    color: #0f172a;
    font-family: inherit;
}
.gmap-search-clear {
    background: #e2e8f0;
    border: none;
    color: #64748b;
    width: 22px;
    height: 22px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    padding: 0;
    margin-right: 4px;
}
.gmap-search-divider {
    width: 1px;
    height: 24px;
    background: #e2e8f0;
}
.gmap-list-toggle-btn {
    background: none;
    border: none;
    color: #ea580c;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
}

/* Category Chips */
.gmap-chips-scroll {
    pointer-events: auto;
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 4px;
    scrollbar-width: none;
    -webkit-overflow-scrolling: touch;
}
.gmap-chips-scroll::-webkit-scrollbar { display: none; }

.gmap-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    white-space: nowrap;
    background: #ffffff;
    color: #334155;
    border: 1px solid rgba(0,0,0,0.08);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.gmap-chip:hover {
    background: #f8fafc;
    transform: translateY(-1px);
}
.gmap-chip.active {
    background: linear-gradient(135deg, #ea580c, #c2410c);
    color: #ffffff;
    border-color: #ea580c;
    box-shadow: 0 3px 12px rgba(234, 88, 12, 0.35);
}
.gmap-chip-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}
.dot-orange { background: #ea580c; }
.dot-blue { background: #0284c7; }
.dot-green { background: #16a34a; }

/* ── 2. Active Navigation Banner ── */
.gmap-nav-banner {
    position: absolute;
    top: 14px;
    left: 14px;
    right: 14px;
    z-index: 1100;
    background: linear-gradient(135deg, #ea580c, #c2410c);
    color: #fff;
    border-radius: 18px;
    padding: 12px 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: 0 8px 24px rgba(234, 88, 12, 0.42);
    backdrop-filter: blur(8px);
    animation: slideDown 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-16px); }
    to { opacity: 1; transform: translateY(0); }
}
.gmap-nav-banner-top {
    display: flex;
    align-items: center;
    gap: 12px;
}
.gmap-nav-banner-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    background: rgba(255,255,255,0.22);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,0.25);
}
.gmap-nav-banner-info {
    flex: 1;
    min-width: 0;
}
.gmap-nav-turn-dist {
    font-size: 1.18rem;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -0.3px;
}
.gmap-nav-instruction {
    font-size: 0.88rem;
    font-weight: 700;
    opacity: 0.95;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-top: 2px;
}
.gmap-nav-banner-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-top: 6px;
    border-top: 1px solid rgba(255,255,255,0.18);
    font-size: 0.76rem;
    font-weight: 600;
    opacity: 0.94;
}
.gmap-nav-total-stat {
    display: flex;
    align-items: center;
    gap: 6px;
}
.gmap-nav-total-stat .stat-highlight {
    font-weight: 800;
    color: #fef08a;
}
.stat-bullet {
    opacity: 0.6;
}
.gmap-nav-dest-label {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 42%;
    opacity: 0.85;
}
.gmap-nav-actions {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-shrink: 0;
}
.gmap-nav-voice-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(0,0,0,0.22);
    border: 1px solid rgba(255,255,255,0.2);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s ease;
}
.gmap-nav-voice-btn:hover {
    background: rgba(0,0,0,0.38);
    transform: scale(1.05);
}
.gmap-nav-exit-btn {
    background: rgba(0,0,0,0.25);
    border: none;
    color: #fff;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 4px;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s;
}
.gmap-nav-exit-btn:hover {
    background: rgba(0,0,0,0.4);
}

/* ── 3. Floating Action Buttons (FAB Column) ── */
.gmap-fab-column {
    position: absolute;
    right: 14px;
    bottom: 24px;
    z-index: 950;
    display: flex;
    flex-direction: column;
    gap: 10px;
    pointer-events: none;
}
.gmap-fab {
    pointer-events: auto;
    width: 46px;
    height: 46px;
    border-radius: 50%;
    background: #ffffff;
    color: #334155;
    border: 1px solid rgba(0,0,0,0.06);
    box-shadow: 0 4px 14px rgba(0,0,0,0.18);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    position: relative;
}
.gmap-fab:hover {
    transform: scale(1.1);
    color: #ea580c;
}
.gmap-fab.active {
    background: linear-gradient(135deg, #ea580c, #c2410c) !important;
    color: #ffffff !important;
    border-color: #ea580c !important;
    box-shadow: 0 4px 14px rgba(234, 88, 12, 0.45) !important;
}
.gmap-fab-gps {
    color: #0284c7;
}
.gmap-fab-badge-count {
    position: absolute;
    top: -3px;
    right: -3px;
    background: #ea580c;
    color: #fff;
    font-size: 0.7rem;
    font-weight: 800;
    border-radius: 10px;
    min-width: 18px;
    height: 18px;
    padding: 0 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
}

/* Layer Switcher Modal Sheet */
.gmap-layer-sheet {
    position: absolute;
    right: 14px;
    bottom: 80px;
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    padding: 14px 16px;
    z-index: 1050;
    width: 280px;
    border: 1px solid rgba(0,0,0,0.08);
}
.gmap-layer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 700;
    font-size: 0.9rem;
    color: #0f172a;
    margin-bottom: 12px;
}
.gmap-layer-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.gmap-layer-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    cursor: pointer;
    padding: 6px;
    border-radius: 12px;
    border: 2px solid transparent;
    font-size: 0.72rem;
    font-weight: 600;
    color: #475569;
    text-align: center;
    transition: all 0.15s;
}
.gmap-layer-card:hover {
    background: #f8fafc;
}
.gmap-layer-card.active {
    border-color: #ea580c;
    background: #fff7ed;
    color: #ea580c;
}
.gmap-layer-preview {
    width: 52px;
    height: 52px;
    border-radius: 10px;
    border: 1px solid rgba(0,0,0,0.1);
}
.preview-streets { background: linear-gradient(135deg, #e2e8f0, #cbd5e1); }
.preview-satellite { background: linear-gradient(135deg, #14532d, #064e3b); }
.preview-terrain { background: linear-gradient(135deg, #d97706, #65a30d); }

/* ── 4. Interactive Bottom Sheet ── */
.gmap-bottom-sheet {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1200;
    background: #ffffff;
    border-top-left-radius: 24px;
    border-top-right-radius: 24px;
    box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.18);
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    transition: max-height 0.3s ease;
}
/* Expanded: full-screen takeover */
.gmap-bottom-sheet.expanded {
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    max-height: 100vh;
    height: 100vh;
    border-radius: 0;
    z-index: 1300;
}
@media (min-width: 768px) {
    .gmap-bottom-sheet {
        left: 20px;
        right: auto;
        bottom: 20px;
        width: 420px;
        max-height: 80vh;
        border-radius: 24px;
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.22);
    }
    .gmap-bottom-sheet.expanded {
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        width: auto;
        max-height: 100vh;
        height: 100vh;
        border-radius: 0;
    }
}
.gmap-sheet-handle-zone {
    width: 100%;
    padding: 10px 0 6px;
    display: flex;
    justify-content: center;
    cursor: grab;
}
.gmap-sheet-handle {
    width: 38px;
    height: 4px;
    border-radius: 4px;
    background: #cbd5e1;
}
.gmap-sheet-inner {
    padding: 0 20px 24px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 14px;
}
.gmap-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.gmap-card-head-left {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex: 1;
    min-width: 0;
}
.gmap-thumb {
    width: 68px;
    height: 68px;
    border-radius: 16px;
    overflow: hidden;
    flex-shrink: 0;
    background: #f1f5f9;
    box-shadow: 0 3px 10px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.06);
}
.gmap-thumb img { width: 100%; height: 100%; object-fit: cover; }
.gmap-thumb-fallback {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}
.gmap-thumb-fallback.bg-orange {
    background: #fff7ed;
    color: #ea580c;
}
.gmap-thumb-fallback.bg-blue {
    background: #f0f9ff;
    color: #0284c7;
}
.gmap-thumb-fallback.bg-green {
    background: #f0fdf4;
    color: #16a34a;
}
html[data-theme="dark"] .gmap-thumb-fallback.bg-orange {
    background: rgba(234, 88, 12, 0.18);
    color: #fb923c;
}
html[data-theme="dark"] .gmap-thumb-fallback.bg-blue {
    background: rgba(2, 132, 199, 0.18);
    color: #38bdf8;
}
html[data-theme="dark"] .gmap-thumb-fallback.bg-green {
    background: rgba(22, 163, 74, 0.18);
    color: #4ade80;
}
.gmap-head-content {
    flex: 1;
    min-width: 0;
}
.gmap-tags-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 3px;
    flex-wrap: wrap;
}
.gmap-badge-tag {
    font-size: 0.72rem;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
}
.badge-orange { background: #ffedd5; color: #ea580c; }
.badge-blue { background: #e0f2fe; color: #0284c7; }
.badge-green { background: #dcfce7; color: #16a34a; }

.gmap-distance-chip {
    font-size: 0.72rem;
    font-weight: 700;
    color: #0284c7;
    background: #f0f9ff;
    border: 1px solid #bae6fd;
    padding: 2px 8px;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 3px;
}
.gmap-sheet-title {
    font-size: 1.08rem;
    font-weight: 800;
    margin: 2px 0 4px;
    line-height: 1.35;
    color: #0f172a;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.gmap-sheet-subtitle {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.gmap-sheet-close {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: #f1f5f9;
    border: none;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s;
}
.gmap-sheet-close:hover {
    background: #e2e8f0;
    color: #0f172a;
}

/* Actions Row */
.gmap-sheet-action-row {
    display: flex;
    gap: 8px;
    align-items: center;
}
.gmap-btn-start-nav {
    background: linear-gradient(135deg, #ea580c, #c2410c);
    color: #ffffff;
    font-weight: 800;
    font-size: 0.92rem;
    border: none;
    border-radius: 14px;
    padding: 11px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(234, 88, 12, 0.38);
    transition: all 0.15s ease;
}
.gmap-btn-start-nav:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(234, 88, 12, 0.48);
}
.gmap-btn-route-select {
    background: #f1f5f9;
    color: #0f172a;
    font-weight: 700;
    font-size: 0.85rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    padding: 10px 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    cursor: pointer;
    transition: all 0.15s ease;
    flex-shrink: 0;
}
.gmap-btn-route-select:hover {
    background: #e2e8f0;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}
.gmap-btn-native-detect {
    background: #ffffff;
    color: #0f172a;
    font-weight: 700;
    font-size: 0.85rem;
    border: 1.5px solid #e2e8f0;
    border-radius: 14px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    transition: all 0.15s;
    flex-shrink: 0;
}
.gmap-btn-native-detect:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}
.gmap-btn-icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    background: #ffffff;
    border: 1.5px solid #e2e8f0;
    color: #475569;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    flex-shrink: 0;
    transition: all 0.15s;
}
.gmap-btn-icon:hover {
    background: #f8fafc;
    color: #ea580c;
    border-color: #cbd5e1;
}

/* ETA Grid */
.gmap-eta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.gmap-eta-card {
    border-radius: 12px;
    padding: 8px 10px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    cursor: pointer;
    transition: all 0.15s;
}
.card-walk {
    background: #fff7ed;
    border: 1px solid #ffedd5;
}
.card-drive {
    background: #f0f9ff;
    border: 1px solid #e0f2fe;
}
.gmap-eta-value {
    font-size: 0.95rem;
    font-weight: 800;
    color: #0f172a;
}

/* Info Section */
.gmap-info-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
    background: #f8fafc;
    border-radius: 16px;
    padding: 12px 14px;
    border: 1px solid #f1f5f9;
}
.gmap-info-row {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.gmap-info-icon {
    color: #64748b;
    margin-top: 2px;
}
.gmap-info-text {
    flex: 1;
    min-width: 0;
}
.gmap-info-desc {
    border-top: 1px solid #e2e8f0;
    padding-top: 8px;
}
#bs-desc-val {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Sheet Footer */
.gmap-btn-detail {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    background: #0f172a;
    color: #ffffff;
    font-weight: 700;
    font-size: 0.88rem;
    border-radius: 14px;
    padding: 12px;
    text-decoration: none;
    transition: all 0.15s;
}
.gmap-btn-detail:hover {
    background: #1e293b;
    color: #fff;
    transform: translateY(-1px);
}

/* ── Route Selection & Travel Mode Modal Sheet ── */
.gmap-route-sheet {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1250;
    background: #ffffff;
    border-top-left-radius: 24px;
    border-top-right-radius: 24px;
    box-shadow: 0 -8px 30px rgba(0, 0, 0, 0.2);
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    animation: slideUpRoute 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 16px 20px 24px;
    gap: 14px;
}
@keyframes slideUpRoute {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
.gmap-route-sheet-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #f1f5f9;
    padding-bottom: 10px;
}
.gmap-route-subtitle {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.gmap-route-title {
    font-size: 1.1rem;
    font-weight: 800;
    color: #0f172a;
    margin: 2px 0 0;
    line-height: 1.3;
}
.gmap-travel-modes-row {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding: 2px 0;
    scrollbar-width: none;
}
.gmap-travel-modes-row::-webkit-scrollbar { display: none; }
.gmap-mode-chip {
    flex: 1;
    min-width: 70px;
    background: #f1f5f9;
    color: #475569;
    border: 1.5px solid transparent;
    border-radius: 12px;
    padding: 8px 6px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
}
.gmap-mode-chip:hover {
    background: #e2e8f0;
}
.gmap-mode-chip.active {
    background: #fff7ed;
    color: #ea580c;
    border-color: #ea580c;
    box-shadow: 0 2px 8px rgba(234, 88, 12, 0.25);
}
.gmap-route-cards-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 220px;
    overflow-y: auto;
}
.gmap-route-card {
    background: #f8fafc;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    padding: 12px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: all 0.15s ease;
}
.gmap-route-card:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.gmap-route-card.selected {
    background: #fff7ed;
    border-color: #ea580c;
    box-shadow: 0 4px 14px rgba(234, 88, 12, 0.15);
}
.gmap-route-card-left {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.gmap-route-card-tag {
    font-size: 0.7rem;
    font-weight: 700;
    color: #ea580c;
    text-transform: uppercase;
}
.gmap-route-card-time {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
}
.gmap-route-card-dist {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 600;
}
.gmap-route-card-via {
    font-size: 0.78rem;
    color: #64748b;
}
.gmap-route-card-radio {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
}
.gmap-route-card.selected .gmap-route-card-radio {
    border-color: #ea580c;
    background: #ea580c;
}
.gmap-route-card.selected .gmap-route-card-radio::after {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #fff;
}

/* ── 6. Nearby Places Drawer ── */
.gmap-nearby-drawer {
    position: absolute;
    top: 74px;
    left: 14px;
    right: 14px;
    bottom: 24px;
    max-width: 440px;
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.25);
    border: 1px solid #e2e8f0;
    z-index: 1200;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    animation: slideDown 0.25s ease-out;
}
.gmap-drawer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 16px;
    border-bottom: 1px solid #f1f5f9;
}
.gmap-title-icon {
    width: 32px;
    height: 32px;
    border-radius: 10px;
    background: #ea580c;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}
.gmap-icon-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: #f1f5f9;
    border: none;
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-weight: 700;
}
.gmap-drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.gmap-place-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border-radius: 14px;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    cursor: pointer;
    transition: all 0.15s;
}
.gmap-place-card:hover {
    background: #f1f5f9;
    transform: translateY(-1px);
}

/* ── WebGL Markers & Pins ── */
.gmap-custom-marker {
    position: relative;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
    transform-origin: bottom center;
}
.gmap-custom-marker:hover {
    transform: scale(1.18);
    z-index: 1000;
}
.gmap-marker-bubble {
    padding: 6px 10px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    gap: 5px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.22);
    border: 2px solid #ffffff;
    font-size: 0.72rem;
    font-weight: 800;
    color: #ffffff;
    white-space: nowrap;
}
.marker-activity .gmap-marker-bubble { background: linear-gradient(135deg, #ea580c, #c2410c); }
.marker-job .gmap-marker-bubble { background: linear-gradient(135deg, #0284c7, #0369a1); }
.marker-landmark .gmap-marker-bubble { background: linear-gradient(135deg, #16a34a, #15803d); }

.gmap-marker-arrow {
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    margin-top: -1px;
}
.marker-activity .gmap-marker-arrow { border-top: 6px solid #c2410c; }
.marker-job .gmap-marker-arrow { border-top: 6px solid #0369a1; }
.marker-landmark .gmap-marker-arrow { border-top: 6px solid #15803d; }

/* User GPS Heading Cone Marker */
.user-gps-heading-marker {
    position: relative;
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.user-gps-cone {
    position: absolute;
    width: 44px;
    height: 44px;
    background: radial-gradient(circle at 50% 100%, rgba(2, 132, 199, 0.4) 0%, rgba(2, 132, 199, 0) 70%);
    clip-path: polygon(50% 50%, 20% 0%, 80% 0%);
    pointer-events: none;
    transition: transform 0.2s ease-out;
}
.user-gps-dot {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #0284c7;
    border: 3px solid #ffffff;
    box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.35), 0 3px 8px rgba(0,0,0,0.3);
    z-index: 2;
}

/* Live Peer Marker */
.live-peer-marker {
    display: flex;
    flex-direction: column;
    align-items: center;
    cursor: pointer;
    transition: transform 0.2s;
}
.live-peer-marker:hover {
    transform: scale(1.15);
}
.live-peer-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid #ffffff;
    box-shadow: 0 3px 8px rgba(0,0,0,0.25);
    background: #ea580c;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 800;
    overflow: hidden;
}
.live-peer-avatar img { width: 100%; height: 100%; object-fit: cover; }
.live-peer-badge {
    background: rgba(15, 23, 42, 0.85);
    color: #fff;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 8px;
    margin-top: 2px;
    white-space: nowrap;
    backdrop-filter: blur(4px);
    border: 1px solid rgba(255,255,255,0.15);
}

/* Destination Marker */
.nav-dest-marker {
    position: relative;
    width: 40px;
    height: 52px;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    animation: nav-dest-bounce 1.5s infinite;
}
@keyframes nav-dest-bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}

/* ── Custom Location Markers (Circular Drop Pin with Icon) ── */
.gmap-pin-wrapper {
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 48px;
    padding-bottom: 8px;
    user-select: none;
}
.custom-pin-marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    color: #ffffff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    border: 2.5px solid #ffffff;
    cursor: pointer;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.2s;
}
.custom-pin-marker svg {
    transform: rotate(45deg);
    width: 19px;
    height: 19px;
    stroke: #ffffff;
}
.custom-pin-marker:hover, .custom-pin-marker.selected {
    transform: rotate(-45deg) scale(1.22);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.45);
    z-index: 1000 !important;
}

.pin-activity { background: linear-gradient(135deg, #ea580c, #f97316); }
.pin-job { background: linear-gradient(135deg, #0284c7, #38bdf8); }
.pin-landmark { background: linear-gradient(135deg, #16a34a, #4ade80); }

/* ── Cluster Markers (Number Bubble when zoomed out) ── */
.gmap-cluster-wrapper {
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    user-select: none;
}
.custom-cluster-marker {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ea580c, #f97316);
    color: #ffffff;
    font-weight: 800;
    font-size: 0.95rem;
    box-shadow: 0 4px 16px rgba(234, 88, 12, 0.45);
    border: 3px solid #ffffff;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.custom-cluster-marker:hover {
    transform: scale(1.18);
    box-shadow: 0 6px 22px rgba(234, 88, 12, 0.6);
}
.cluster-pulse-ring {
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    background: rgba(234, 88, 12, 0.28);
    animation: cluster-pulse 2s infinite ease-out;
    pointer-events: none;
}
@keyframes cluster-pulse {
    0% { transform: scale(0.9); opacity: 0.85; }
    50% { transform: scale(1.25); opacity: 0.25; }
    100% { transform: scale(0.9); opacity: 0.85; }
}

.cluster-sm {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #ea580c, #f97316);
    box-shadow: 0 4px 14px rgba(234, 88, 12, 0.45);
}
.cluster-md {
    width: 46px;
    height: 46px;
    background: linear-gradient(135deg, #0284c7, #38bdf8);
    box-shadow: 0 4px 16px rgba(2, 132, 199, 0.45);
}
.cluster-md .cluster-pulse-ring {
    background: rgba(2, 132, 199, 0.28);
}
.cluster-lg {
    width: 52px;
    height: 52px;
    background: linear-gradient(135deg, #7c3aed, #a855f7);
    box-shadow: 0 4px 18px rgba(124, 58, 237, 0.45);
}
.cluster-lg .cluster-pulse-ring {
    background: rgba(124, 58, 237, 0.28);
}

/* ── Dark Mode Adaptations ── */
html[data-theme="dark"] .map-explorer-wrapper {
    background: #18181b;
    border-color: #27272a;
}
html[data-theme="dark"] .gmap-search-pill,
html[data-theme="dark"] .gmap-chip,
html[data-theme="dark"] .gmap-fab,
html[data-theme="dark"] .gmap-layer-sheet,
html[data-theme="dark"] .gmap-bottom-sheet,
html[data-theme="dark"] .gmap-route-sheet,
html[data-theme="dark"] .gmap-nearby-drawer,
html[data-theme="dark"] .gmap-place-card {
    background: #18181b !important;
    border-color: #27272a !important;
    color: #f4f4f5 !important;
}
html[data-theme="dark"] .gmap-search-input {
    color: #f4f4f5;
}
html[data-theme="dark"] .gmap-sheet-title,
html[data-theme="dark"] .gmap-route-title,
html[data-theme="dark"] .gmap-route-card-time,
html[data-theme="dark"] .gmap-eta-value,
html[data-theme="dark"] .gmap-info-text,
html[data-theme="dark"] .gmap-layer-header {
    color: #f4f4f5 !important;
}
html[data-theme="dark"] .gmap-eta-card,
html[data-theme="dark"] .gmap-info-section,
html[data-theme="dark"] .gmap-btn-secondary,
html[data-theme="dark"] .gmap-btn-route-select,
html[data-theme="dark"] .gmap-btn-native-detect,
html[data-theme="dark"] .gmap-btn-icon,
html[data-theme="dark"] .gmap-btn-detail,
html[data-theme="dark"] .gmap-mode-chip,
html[data-theme="dark"] .gmap-route-card,
html[data-theme="dark"] .gmap-sheet-close,
html[data-theme="dark"] .gmap-icon-btn {
    background: #27272a !important;
    border-color: #3f3f46 !important;
    color: #f4f4f5 !important;
}
html[data-theme="dark"] .gmap-mode-chip.active {
    background: rgba(16, 185, 129, 0.18) !important;
    border-color: #10b981 !important;
    color: #34d399 !important;
}
html[data-theme="dark"] .gmap-route-card.selected {
    background: rgba(16, 185, 129, 0.15) !important;
    border-color: #10b981 !important;
}
html[data-theme="dark"] .card-walk {
    background: rgba(234, 88, 12, 0.12) !important;
    border-color: rgba(234, 88, 12, 0.25) !important;
}
html[data-theme="dark"] .card-drive {
    background: rgba(2, 132, 199, 0.12) !important;
    border-color: rgba(2, 132, 199, 0.25) !important;
}
html[data-theme="dark"] .gmap-sheet-handle {
    background: #3f3f46;
}

</style>

<script>
(function() {
    const authUserId = '{{ auth()->id() }}';
    let map = null;
    let userMarker = null;
    let destMarker = null;
    let userCoords = null; // [lat, lng]
    let currentHeading = 0;
    let watchGpsId = null;
    let lastBroadcastTime = 0;
    let lastBroadcastCoords = null;
    let peerMarkers = {}; // { [userId]: maplibregl.Marker }

    let allLocations = [];
    let locationMarkers = []; // Array of { marker, loc }
    let clusterIndex = null;
    let currentFilterType = 'all';
    let searchQuery = '';
    let currentRadiusKm = 0;
    let isHeatmapActive = false;
    let is3DModeActive = false;
    let activeLocation = null;
    let activeMapLayerType = 'streets';

    const defaultCenter = [16.4745, 102.8235]; // Campus center [lat, lng]

    // WebGL Vector & Raster Styles
    const MAP_STYLES = {
        streets: 'https://basemaps.cartocdn.com/gl/voyager-gl-style/style.json',
        satellite: {
            version: 8,
            sources: {
                'esri-satellite': {
                    type: 'raster',
                    tiles: [
                        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'
                    ],
                    tileSize: 256,
                    attribution: '&copy; Esri, Maxar, Earthstar'
                },
                'esri-labels': {
                    type: 'raster',
                    tiles: [
                        'https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}'
                    ],
                    tileSize: 256
                }
            },
            layers: [
                { id: 'satellite-base', type: 'raster', source: 'esri-satellite', minzoom: 0, maxzoom: 19 },
                { id: 'satellite-labels', type: 'raster', source: 'esri-labels', minzoom: 0, maxzoom: 19 }
            ]
        },
        terrain: {
            version: 8,
            sources: {
                'esri-terrain': {
                    type: 'raster',
                    tiles: [
                        'https://server.arcgisonline.com/ArcGIS/rest/services/World_Topo_Map/MapServer/tile/{z}/{y}/{x}'
                    ],
                    tileSize: 256,
                    attribution: '&copy; Esri, HERE, Garmin, Intermap'
                }
            },
            layers: [
                { id: 'terrain-base', type: 'raster', source: 'esri-terrain', minzoom: 0, maxzoom: 19 }
            ]
        }
    };

    // Navigation and Routing State
    let currentTravelMode = 'drive';
    let currentRouteAlternatives = [];
    let selectedRouteIndex = 0;
    let activeNavTarget = null;
    let activeNavRoute = null;
    let routeFetchAbortCtrl = null;
    let lastRerouteTime = 0;
    let isRerouting = false;

    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        initSearchInput();
        initBottomSheetSwipe();
        fetchLocations();
        startRealtimeLocationTracking();
        initReverbMapTracking();
    });

    function getMapStyle(layerType) {
        const s = MAP_STYLES[layerType] || MAP_STYLES.streets;
        if (typeof s === 'object') {
            return JSON.parse(JSON.stringify(s));
        }
        return s;
    }

    function initMap() {
        map = new maplibregl.Map({
            container: 'unifiedMap',
            style: getMapStyle(activeMapLayerType),
            center: [defaultCenter[1], defaultCenter[0]], // MapLibre takes [lng, lat]
            zoom: 15,
            pitch: 0,
            bearing: 0,
            antialias: true
        });

        // Add standard navigation controls (compass only)
        map.addControl(new maplibregl.NavigationControl({ showCompass: true, showZoom: false }), 'bottom-left');

        map.on('load', function() {
            restoreMapLayersAndState();
        });

        map.on('moveend', renderClusteredMarkers);

        map.on('style.load', restoreMapLayersAndState);

        map.on('click', function(e) {
            // If actively navigating, keep all navigation visuals intact
            if (activeNavRoute) return;

            // Check if clicking on route line layers
            try {
                const features = map.queryRenderedFeatures(e.point, {
                    layers: ['route-primary-core', 'route-primary-casing', 'route-alt-core'].filter(l => map.getLayer(l))
                });
                if (features.length > 0) {
                    const clickedLayer = features[0].layer.id;
                    if (clickedLayer === 'route-alt-core' && currentRouteAlternatives.length > 1) {
                        selectRouteCard(1);
                    }
                    return;
                }
            } catch(err) {}

            // Close bottom sheet if clicking empty map space
            const targetEl = e.originalEvent ? e.originalEvent.target : null;
            if (targetEl && targetEl.tagName === 'CANVAS') {
                const bottomSheet = document.getElementById('mapBottomSheet');
                if (bottomSheet && bottomSheet.style.display !== 'none') {
                    closeBottomSheet();
                }
            }
        });
    }

    function restoreMapLayersAndState() {
        if (!map || !map.getStyle()) return;
        initVectorSourcesAndLayers();
        enable3DBuildings();

        if (activeNavRoute && activeNavRoute.points && activeNavRoute.points.length >= 2) {
            renderRouteGeometryOnMap(activeNavRoute.points, true);
        } else if (currentRouteAlternatives && currentRouteAlternatives.length > 0) {
            previewRoutesOnMap();
        }

        setUserGpsMarker();
        rebuildClusterIndex();
        updateHeatmapData();
    }

    // Initialize WebGL GeoJSON Sources (Routing, Radius, Heatmap)
    function initVectorSourcesAndLayers() {
        if (!map || !map.getStyle()) return;

        // 1. Primary Route Source & Layers
        if (!map.getSource('route-primary-source')) {
            map.addSource('route-primary-source', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] }
            });
        }
        if (!map.getLayer('route-primary-casing')) {
            map.addLayer({
                id: 'route-primary-casing',
                type: 'line',
                source: 'route-primary-source',
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': '#0f172a',
                    'line-width': 10,
                    'line-opacity': 0.85
                }
            });
        }
        if (!map.getLayer('route-primary-core')) {
            map.addLayer({
                id: 'route-primary-core',
                type: 'line',
                source: 'route-primary-source',
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': '#0284c7',
                    'line-width': 6,
                    'line-opacity': 1
                }
            });
        }

        // 2. Alternative Route Source & Layer
        if (!map.getSource('route-alt-source')) {
            map.addSource('route-alt-source', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] }
            });
        }
        if (!map.getLayer('route-alt-core')) {
            map.addLayer({
                id: 'route-alt-core',
                type: 'line',
                source: 'route-alt-source',
                layout: {
                    'line-cap': 'round',
                    'line-join': 'round'
                },
                paint: {
                    'line-color': '#64748b',
                    'line-width': 5,
                    'line-opacity': 0.8,
                    'line-dasharray': [3, 2]
                }
            });
        }

        // 3. Radius Circle Source & Layers
        if (!map.getSource('radius-circle-source')) {
            map.addSource('radius-circle-source', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] }
            });
        }
        if (!map.getLayer('radius-circle-fill')) {
            map.addLayer({
                id: 'radius-circle-fill',
                type: 'fill',
                source: 'radius-circle-source',
                paint: {
                    'fill-color': '#0284c7',
                    'fill-opacity': 0.12
                }
            });
        }
        if (!map.getLayer('radius-circle-line')) {
            map.addLayer({
                id: 'radius-circle-line',
                type: 'line',
                source: 'radius-circle-source',
                paint: {
                    'line-color': '#0284c7',
                    'line-width': 2,
                    'line-dasharray': [3, 3]
                }
            });
        }

        // 4. Heatmap Source & Layer
        if (!map.getSource('heatmap-source')) {
            map.addSource('heatmap-source', {
                type: 'geojson',
                data: { type: 'FeatureCollection', features: [] }
            });
        }
        if (!map.getLayer('heatmap-layer')) {
            map.addLayer({
                id: 'heatmap-layer',
                type: 'heatmap',
                source: 'heatmap-source',
                layout: { 'visibility': 'none' },
                paint: {
                    'heatmap-weight': ['get', 'weight'],
                    'heatmap-intensity': 1.2,
                    'heatmap-color': [
                        'interpolate', ['linear'], ['heatmap-density'],
                        0, 'rgba(0, 255, 255, 0)',
                        0.2, 'rgb(0, 255, 255)',
                        0.4, 'rgb(0, 255, 0)',
                        0.6, 'rgb(255, 255, 0)',
                        1, 'rgb(255, 0, 0)'
                    ],
                    'heatmap-radius': 35,
                    'heatmap-opacity': 0.85
                }
            });
        }
    }

    // 3D Buildings Extrusion
    function enable3DBuildings() {
        if (!map || !map.getStyle()) return;
        const style = map.getStyle();
        if (!style || !style.layers) return;

        // Check if building source layer exists
        let labelLayerId = null;
        for (let i = 0; i < style.layers.length; i++) {
            if (style.layers[i].type === 'symbol' && style.layers[i].layout && style.layers[i].layout['text-field']) {
                labelLayerId = style.layers[i].id;
                break;
            }
        }

        const sourceId = map.getSource('carto') ? 'carto' : (map.getSource('openmaptiles') ? 'openmaptiles' : null);
        if (sourceId && !map.getLayer('3d-buildings')) {
            try {
                map.addLayer({
                    'id': '3d-buildings',
                    'source': sourceId,
                    'source-layer': 'building',
                    'filter': ['==', 'extrude', 'true'],
                    'type': 'fill-extrusion',
                    'minzoom': 14.5,
                    'paint': {
                        'fill-extrusion-color': [
                            'interpolate', ['linear'], ['get', 'height'],
                            0, '#cbd5e1',
                            50, '#94a3b8',
                            100, '#64748b'
                        ],
                        'fill-extrusion-height': ['interpolate', ['linear'], ['zoom'], 14.5, 0, 15, ['get', 'height']],
                        'fill-extrusion-base': ['interpolate', ['linear'], ['zoom'], 14.5, 0, 15, ['get', 'min_height']],
                        'fill-extrusion-opacity': 0.75
                    }
                }, labelLayerId);
            } catch(e) {}
        }
    }

    // Toggle 3D Camera View (Isometric 60° Pitch)
    window.toggle3DView = function() {
        if (!map) return;
        is3DModeActive = !is3DModeActive;
        const btn = document.getElementById('btnToggle3D');
        if (btn) btn.classList.toggle('active', is3DModeActive);

        map.easeTo({
            pitch: is3DModeActive ? 60 : 0,
            bearing: is3DModeActive ? (currentHeading || -20) : 0,
            duration: 1200
        });
    };

    // Layer Switcher
    window.toggleLayerSheet = function() {
        const sheet = document.getElementById('gmapLayerSheet');
        sheet.style.display = sheet.style.display === 'none' ? 'block' : 'none';
    };

    window.switchMapLayer = function(layerType) {
        if (!map || activeMapLayerType === layerType) {
            document.getElementById('gmapLayerSheet').style.display = 'none';
            return;
        }

        activeMapLayerType = layerType;
        document.querySelectorAll('.gmap-layer-card').forEach(c => c.classList.remove('active'));
        const activeCard = document.getElementById(`layerCard-${layerType}`);
        if (activeCard) activeCard.classList.add('active');
        document.getElementById('gmapLayerSheet').style.display = 'none';

        const styleToLoad = getMapStyle(layerType);
        
        map.once('styledata', restoreMapLayersAndState);
        map.once('idle', restoreMapLayersAndState);
        map.setStyle(styleToLoad);
    };

    // Fetch and Load Locations Data
    async function fetchLocations() {
        try {
            const resp = await fetch('{{ route("api.map.locations") }}', {
                headers: { 'Accept': 'application/json' }
            });
            if (!resp.ok) throw new Error(`HTTP error! status: ${resp.status}`);
            const data = await resp.json();

            const rawLocations = data.locations || [
                ...(data.activities || []),
                ...(data.jobs || []),
                ...(data.landmarks || [])
            ];

            allLocations = rawLocations.filter(loc => {
                const lat = parseFloat(loc.lat);
                const lng = parseFloat(loc.lng);
                return !isNaN(lat) && !isNaN(lng) && lat !== 0 && lng !== 0;
            });

            const placeCountEl = document.getElementById('fabPlaceCountBadge');
            if (placeCountEl) placeCountEl.textContent = allLocations.length;
            const drawerCountEl = document.getElementById('drawerCountLabel');
            if (drawerCountEl) drawerCountEl.textContent = `${allLocations.length} แห่ง`;

            rebuildClusterIndex();
            updateNearbyList();
            updateHeatmapData();

            // Auto fit bounds on initial load if locations exist
            if (allLocations.length > 0 && map && (!userCoords || is3DModeActive === false)) {
                const bounds = new maplibregl.LngLatBounds();
                allLocations.forEach(l => bounds.extend([parseFloat(l.lng), parseFloat(l.lat)]));
                map.fitBounds(bounds, { padding: { top: 90, bottom: 90, left: 50, right: 50 }, maxZoom: 16, duration: 1000 });
            }
        } catch (err) {
            console.error('Failed to load locations:', err);
        }
    }

    // Build Supercluster Index
    function rebuildClusterIndex() {
        const filtered = getFilteredLocations();
        if (typeof Supercluster !== 'undefined') {
            clusterIndex = new Supercluster({
                radius: 55,
                maxZoom: 15,
                minPoints: 2
            });
            clusterIndex.load(filtered.map(loc => ({
                type: 'Feature',
                properties: { ...loc },
                geometry: {
                    type: 'Point',
                    coordinates: [parseFloat(loc.lng), parseFloat(loc.lat)]
                }
            })));
        }
        renderClusteredMarkers();
    }

    // Render WebGL HTML Markers (Clusters or Individual Circular Drop Pins)
    function renderClusteredMarkers() {
        if (!map) return;

        // Clear existing markers
        locationMarkers.forEach(item => {
            if (item && item.marker) {
                item.marker.remove();
            }
        });
        locationMarkers = [];

        const filtered = getFilteredLocations();

        if (!clusterIndex || typeof Supercluster === 'undefined') {
            // Fallback if supercluster is not loaded
            filtered.forEach(loc => renderSingleLocationPin(loc));
            return;
        }

        const bounds = map.getBounds();
        const bbox = [bounds.getWest(), bounds.getSouth(), bounds.getEast(), bounds.getNorth()];
        const zoom = Math.floor(map.getZoom());

        let clusters = [];
        try {
            clusters = clusterIndex.getClusters(bbox, zoom);
        } catch(e) {
            clusters = filtered.map(loc => ({
                type: 'Feature',
                properties: { ...loc, cluster: false },
                geometry: { type: 'Point', coordinates: [parseFloat(loc.lng), parseFloat(loc.lat)] }
            }));
        }

        clusters.forEach(feat => {
            const [lng, lat] = feat.geometry.coordinates;
            const isCluster = feat.properties && feat.properties.cluster === true;

            if (isCluster) {
                const count = feat.properties.point_count;
                const clusterId = feat.properties.cluster_id;

                let sizeClass = 'cluster-sm';
                if (count >= 20) sizeClass = 'cluster-lg';
                else if (count >= 6) sizeClass = 'cluster-md';

                const el = document.createElement('div');
                el.className = 'gmap-cluster-wrapper';
                el.innerHTML = `
                    <div class="custom-cluster-marker ${sizeClass}">
                        <div class="cluster-pulse-ring"></div>
                        <span>${count}</span>
                    </div>
                `;

                el.addEventListener('click', function(e) {
                    e.stopPropagation();
                    try {
                        const expansionZoom = Math.min(clusterIndex.getClusterExpansionZoom(clusterId), 17);
                        map.easeTo({
                            center: [lng, lat],
                            zoom: expansionZoom,
                            duration: 600
                        });
                    } catch(err) {
                        map.easeTo({
                            center: [lng, lat],
                            zoom: map.getZoom() + 2,
                            duration: 500
                        });
                    }
                });

                const marker = new maplibregl.Marker({ element: el, anchor: 'center' })
                    .setLngLat([lng, lat])
                    .addTo(map);

                locationMarkers.push({ marker, isCluster: true });
            } else {
                renderSingleLocationPin(feat.properties);
            }
        });
    }

    function renderSingleLocationPin(loc) {
        if (!loc || !loc.lat || !loc.lng) return;
        const el = document.createElement('div');
        el.className = 'gmap-pin-wrapper';

        let pinClass = 'pin-activity';
        let iconSvg = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';

        if (loc.type === 'job') {
            pinClass = 'pin-job';
            iconSvg = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';
        } else if (loc.type === 'landmark') {
            pinClass = 'pin-landmark';
            iconSvg = '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>';
        }

        el.innerHTML = `
            <div class="custom-pin-marker ${pinClass}" title="${escapeHtml(loc.title)}">
                ${iconSvg}
            </div>
        `;

        el.addEventListener('click', function(e) {
            e.stopPropagation();
            focusLocation(loc);
        });

        const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
            .setLngLat([parseFloat(loc.lng), parseFloat(loc.lat)])
            .addTo(map);

        locationMarkers.push({ marker, loc, isCluster: false });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function getFilteredLocations() {
        return allLocations.filter(loc => {
            if (currentFilterType !== 'all' && loc.type !== currentFilterType) {
                return false;
            }
            if (searchQuery.trim() !== '') {
                const q = searchQuery.toLowerCase();
                const titleMatch = (loc.title || '').toLowerCase().includes(q);
                const locMatch = (loc.location_name || '').toLowerCase().includes(q);
                const descMatch = (loc.description || '').toLowerCase().includes(q);
                if (!titleMatch && !locMatch && !descMatch) return false;
            }
            if (currentRadiusKm > 0 && userCoords) {
                const dist = calculateDistance(userCoords[0], userCoords[1], loc.lat, loc.lng);
                if (dist > currentRadiusKm) return false;
            }
            return true;
        });
    }

    // Filter Chips
    window.filterType = function(type, btn) {
        currentFilterType = type;
        document.querySelectorAll('.gmap-chip[data-type]').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        rebuildClusterIndex();
        updateNearbyList();
        updateHeatmapData();
    };

    window.toggleQuickRadius = function(radius, btn) {
        if (currentRadiusKm === radius) {
            currentRadiusKm = 0;
            btn.classList.remove('active');
            renderRadiusCircle(null, 0);
        } else {
            currentRadiusKm = radius;
            document.querySelectorAll('.gmap-chip[data-radius]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            if (userCoords) {
                renderRadiusCircle(userCoords, radius);
                map.flyTo({ center: [userCoords[1], userCoords[0]], zoom: 14.5, duration: 800 });
            } else {
                locateUserAndCenter();
            }
        }
        rebuildClusterIndex();
        updateNearbyList();
    };

    window.toggleHeatmapChip = function(btn) {
        isHeatmapActive = !isHeatmapActive;
        btn.classList.toggle('active', isHeatmapActive);
        if (map && map.getLayer('heatmap-layer')) {
            map.setLayoutProperty('heatmap-layer', 'visibility', isHeatmapActive ? 'visible' : 'none');
        }
    };

    function updateHeatmapData() {
        if (!map || !map.getSource('heatmap-source')) return;
        const filtered = getFilteredLocations();
        const geojson = {
            type: 'FeatureCollection',
            features: filtered.map(l => ({
                type: 'Feature',
                geometry: { type: 'Point', coordinates: [parseFloat(l.lng), parseFloat(l.lat)] },
                properties: { weight: l.type === 'activity' ? 1.0 : (l.type === 'job' ? 0.7 : 0.4) }
            }))
        };
        map.getSource('heatmap-source').setData(geojson);
    }

    function renderRadiusCircle(center, radiusKm) {
        if (!map || !map.getSource('radius-circle-source')) return;
        if (!center || radiusKm <= 0) {
            map.getSource('radius-circle-source').setData({ type: 'FeatureCollection', features: [] });
            return;
        }

        const points = 64;
        const coords = { latitude: center[0], longitude: center[1] };
        const km = radiusKm;
        const ret = [];
        const distanceX = km / (111.320 * Math.cos(coords.latitude * Math.PI / 180));
        const distanceY = km / 110.574;

        for (let i = 0; i < points; i++) {
            const theta = (i / points) * (2 * Math.PI);
            const x = distanceX * Math.cos(theta);
            const y = distanceY * Math.sin(theta);
            ret.push([coords.longitude + x, coords.latitude + y]);
        }
        ret.push(ret[0]);

        map.getSource('radius-circle-source').setData({
            type: 'FeatureCollection',
            features: [{
                type: 'Feature',
                geometry: { type: 'Polygon', coordinates: [ret] }
            }]
        });
    }

    // Search Box
    function initSearchInput() {
        const input = document.getElementById('mapSearchInput');
        const clearBtn = document.getElementById('mapSearchClearBtn');
        if (!input) return;

        input.addEventListener('input', function() {
            searchQuery = this.value;
            clearBtn.style.display = searchQuery ? 'flex' : 'none';
            rebuildClusterIndex();
            updateNearbyList();
        });
    }

    window.clearMapSearch = function() {
        const input = document.getElementById('mapSearchInput');
        if (input) input.value = '';
        searchQuery = '';
        document.getElementById('mapSearchClearBtn').style.display = 'none';
        rebuildClusterIndex();
        updateNearbyList();
    };

    window.goBackOrHome = function() {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            window.location.href = '{{ route("activities.index") }}';
        }
    };

    // Location Focus & Bottom Sheet Details
    function focusLocation(loc) {
        activeLocation = loc;
        showBottomSheet(loc);

        map.flyTo({
            center: [parseFloat(loc.lng), parseFloat(loc.lat)],
            zoom: 16,
            pitch: is3DModeActive ? 55 : 0,
            duration: 1000
        });

        // Automatically calculate and preview OSRM route line immediately
        calculateAndRenderRouteOptions();
    }

    function showBottomSheet(loc) {
        const sheet = document.getElementById('mapBottomSheet');
        if (!sheet) return;

        // Image & Dynamic Category Fallback SVG
        const imgEl = document.getElementById('bs-img');
        const fallbackEl = document.getElementById('bs-icon-fallback');

        function setCategoryFallback() {
            imgEl.style.display = 'none';
            fallbackEl.style.display = 'flex';
            if (loc.type === 'activity') {
                fallbackEl.className = 'gmap-thumb-fallback bg-orange';
                fallbackEl.innerHTML = `
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <rect x="3" y="4" width="18" height="18" rx="3" ry="3"/>
                        <line x1="16" y1="2" x2="16" y2="6"/>
                        <line x1="8" y1="2" x2="8" y2="6"/>
                        <line x1="3" y1="10" x2="21" y2="10"/>
                        <path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01M16 18h.01"/>
                    </svg>
                `;
            } else if (loc.type === 'job') {
                fallbackEl.className = 'gmap-thumb-fallback bg-blue';
                fallbackEl.innerHTML = `
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>
                    </svg>
                `;
            } else {
                fallbackEl.className = 'gmap-thumb-fallback bg-green';
                fallbackEl.innerHTML = `
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v5m-4 0h4"/>
                    </svg>
                `;
            }
        }

        if (loc.image) {
            imgEl.onload = function() {
                imgEl.style.display = 'block';
                fallbackEl.style.display = 'none';
            };
            imgEl.onerror = function() {
                setCategoryFallback();
            };
            imgEl.src = loc.image;
        } else {
            setCategoryFallback();
        }

        // Tag Badge with SVG icons
        const badgeEl = document.getElementById('bs-badge');
        badgeEl.className = 'gmap-badge-tag';
        if (loc.type === 'activity') {
            badgeEl.classList.add('badge-orange');
            badgeEl.innerHTML = `
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <span>กิจกรรม</span>
            `;
        } else if (loc.type === 'job') {
            badgeEl.classList.add('badge-blue');
            badgeEl.innerHTML = `
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="2" y="7" width="20" height="14" rx="2" ry="2"/>
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16 21V5a2 2 0 00-2-2h-4a2 2 0 00-2 2v16"/>
                </svg>
                <span>งานพาร์ทไทม์</span>
            `;
        } else {
            badgeEl.classList.add('badge-green');
            badgeEl.innerHTML = `
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
                <span>อาคาร/สถานที่</span>
            `;
        }

        // Title & Location
        document.getElementById('bs-title').textContent = loc.title;
        document.getElementById('bs-subtitle').textContent = loc.location_name || 'ไม่ระบุสถานที่';

        // Distance & ETA Calculations
        const startPoint = userCoords || defaultCenter;
        const distKm = calculateDistance(startPoint[0], startPoint[1], loc.lat, loc.lng);
        const distText = distKm < 1 ? `${Math.round(distKm * 1000)} ม.` : `${distKm.toFixed(1)} กม.`;
        document.getElementById('bs-dist-val').textContent = distText;

        const walkMins = Math.max(1, Math.round((distKm / 4.5) * 60));
        const driveMins = Math.max(1, Math.round((distKm / 38) * 60));
        document.getElementById('bs-walk-time').textContent = formatDurationThai(walkMins);
        document.getElementById('bs-drive-time').textContent = formatDurationThai(driveMins);

        // Date / Time
        const timeWrap = document.getElementById('bs-time-wrap');
        const timeVal = loc.time_text || loc.start_date || loc.meta_info;
        if (timeVal) {
            timeWrap.style.display = 'flex';
            document.getElementById('bs-time-val').textContent = timeVal;
        } else {
            timeWrap.style.display = 'none';
        }

        // Quota
        const quotaWrap = document.getElementById('bs-quota-wrap');
        const quotaVal = loc.quota_text || (loc.quota ? `${loc.quota} คน` : null);
        if (quotaVal) {
            quotaWrap.style.display = 'flex';
            document.getElementById('bs-quota-val').textContent = quotaVal;
        } else {
            quotaWrap.style.display = 'none';
        }

        // Description — show short preview (first meaningful lines, max ~100 chars)
        const rawDesc = loc.description || loc.subtitle || 'ไม่มีรายละเอียดเพิ่มเติม';
        const firstLines = rawDesc.split('\n').filter(l => l.trim()).slice(0, 3).join(' ');
        const descPreview = firstLines.length > 100 ? firstLines.substring(0, 100).trimEnd() + '…' : firstLines;
        document.getElementById('bs-desc-val').textContent = descPreview;

        // Detail Link & Button Label
        const detailLink = document.getElementById('bs-detail-link');
        const detailUrl = loc.detail_url || loc.url;
        if (detailUrl) {
            detailLink.parentElement.style.display = 'block';
            detailLink.href = detailUrl;
            const linkText = detailLink.querySelector('span');
            if (linkText) {
                linkText.textContent = loc.detail_button_text || (loc.type === 'job' ? 'ดูรายละเอียดงานเต็ม' : 'ดูรายละเอียดกิจกรรมเต็ม');
            }
        } else {
            detailLink.parentElement.style.display = 'none';
        }

        document.getElementById('bs-native-nav-btn').href = `https://www.google.com/maps/dir/?api=1&destination=${loc.lat},${loc.lng}`;

        sheet.style.display = 'flex';
    }

    window.closeBottomSheet = function() {
        const sheet = document.getElementById('mapBottomSheet');
        if (sheet) sheet.style.display = 'none';
    };

    window.toggleBottomSheetExpand = function() {
        const sheet = document.getElementById('mapBottomSheet');
        if (!sheet) return;
        sheet.classList.toggle('expanded');
    };

    function initBottomSheetSwipe() {
        const zone = document.getElementById('gmapSheetHandleZone');
        if (!zone) return;
        let startY = 0;
        zone.addEventListener('touchstart', function(e) {
            startY = e.touches[0].clientY;
        }, { passive: true });
        zone.addEventListener('touchend', function(e) {
            const endY = e.changedTouches[0].clientY;
            if (endY - startY > 50) {
                closeBottomSheet();
            }
        }, { passive: true });
    }

    window.shareActiveLocation = function() {
        if (!activeLocation) return;
        if (navigator.share) {
            navigator.share({
                title: activeLocation.title,
                text: `พิกัด ${activeLocation.title} บนแผนที่ UNI Activity`,
                url: window.location.href
            }).catch(() => {});
        } else {
            navigator.clipboard.writeText(window.location.href);
            window.alert('คัดลอกลิงก์สถานที่เรียบร้อยแล้ว!');
        }
    };

    // ── OSRM Real Road Route Calculation Engine ──
    window.openRouteSelectorForActive = function() {
        if (!activeLocation) return;
        openRouteSelector(activeLocation);
    };

    window.selectModeFromCard = function(mode) {
        if (!activeLocation) return;
        currentTravelMode = mode;
        openRouteSelector(activeLocation);
    };

    window.openRouteSelector = function(loc) {
        if (!loc) return;
        activeLocation = loc;
        closeBottomSheet();

        const sheet = document.getElementById('gmapRouteSelectorSheet');
        if (!sheet) return;
        document.getElementById('gmapRouteDestTitle').textContent = loc.title;

        document.querySelectorAll('.gmap-mode-chip').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-mode') === currentTravelMode);
        });

        sheet.style.display = 'flex';

        const startPoint = userCoords || defaultCenter;
        const bounds = new maplibregl.LngLatBounds([startPoint[1], startPoint[0]], [parseFloat(loc.lng), parseFloat(loc.lat)]);
        map.fitBounds(bounds, { padding: { top: 120, bottom: 260, left: 60, right: 60 }, duration: 1000 });

        selectedRouteIndex = 0;
        calculateAndRenderRouteOptions();
    };

    window.closeRouteSelector = function() {
        const sheet = document.getElementById('gmapRouteSelectorSheet');
        if (sheet) sheet.style.display = 'none';
        if (!activeNavRoute) {
            clearAllRouteVisuals();
            currentRouteAlternatives = [];
        }
    };

    window.selectTravelMode = function(mode, btn) {
        currentTravelMode = mode;
        document.querySelectorAll('.gmap-mode-chip').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        selectedRouteIndex = 0;
        calculateAndRenderRouteOptions();
    };

    function clearAllRouteVisuals() {
        if (!map) return;
        if (map.getSource('route-primary-source')) {
            map.getSource('route-primary-source').setData({ type: 'FeatureCollection', features: [] });
        }
        if (map.getSource('route-alt-source')) {
            map.getSource('route-alt-source').setData({ type: 'FeatureCollection', features: [] });
        }
        if (destMarker) {
            destMarker.remove();
            destMarker = null;
        }
    }

    async function calculateAndRenderRouteOptions() {
        const target = activeNavTarget || activeLocation;
        if (!target) return null;

        const cardsContainer = document.getElementById('gmapRouteCardsList');
        const startPoint = userCoords || defaultCenter;

        if (routeFetchAbortCtrl) {
            try { routeFetchAbortCtrl.abort(); } catch (e) {}
        }
        routeFetchAbortCtrl = new AbortController();
        const requestController = routeFetchAbortCtrl;

        const baseDistKm = calculateDistance(startPoint[0], startPoint[1], parseFloat(target.lat), parseFloat(target.lng));
        let speedKmH = 38;
        let modeName = 'รถยนต์';
        let osrmProfile = 'driving';

        if (currentTravelMode === 'moto') {
            speedKmH = 38;
            modeName = 'มอเตอร์ไซค์';
            osrmProfile = 'driving';
        } else if (currentTravelMode === 'walk') {
            speedKmH = 4.5;
            modeName = 'เดินเท้า';
            osrmProfile = 'walking';
        } else if (currentTravelMode === 'bike') {
            speedKmH = 14;
            modeName = 'จักรยาน';
            osrmProfile = 'cycling';
        }

        const estDistKm = (baseDistKm * 1.08).toFixed(1);
        const estTimeMin = Math.max(1, Math.round((parseFloat(estDistKm) / speedKmH) * 60));

        currentRouteAlternatives = [
            {
                index: 0,
                name: 'เส้นทางหลัก (เร็วที่สุด)',
                tag: 'แนะนำ',
                distKm: estDistKm,
                timeMins: estTimeMin,
                timeText: formatDurationThai(estTimeMin),
                via: `${modeName} • กำลังคำนวณเส้นทางบนถนนจริง...`,
                points: [[startPoint[1], startPoint[0]], [parseFloat(target.lng), parseFloat(target.lat)]],
                isEstimated: true,
                isMain: true
            }
        ];
        renderRouteCards();
        previewRoutesOnMap();

        if (activeNavRoute) {
            activeNavRoute = currentRouteAlternatives[0];
            renderRouteGeometryOnMap(activeNavRoute.points, true);
        }

        try {
            const startLng = startPoint[1];
            const startLat = startPoint[0];
            const targetLng = parseFloat(target.lng);
            const targetLat = parseFloat(target.lat);

            const osrmUrl = `https://router.project-osrm.org/route/v1/${osrmProfile}/${startLng},${startLat};${targetLng},${targetLat}?overview=full&geometries=geojson&alternatives=true&steps=true`;

            const resp = await fetch(osrmUrl, { signal: requestController.signal });
            if (!resp.ok) throw new Error(`Routing service returned ${resp.status}`);
            const data = await resp.json();

            if (requestController !== routeFetchAbortCtrl) return null;

            if (data && data.code === 'Ok' && data.routes && data.routes.length > 0) {
                const newRoutes = [];
                data.routes.forEach((r, idx) => {
                    const roadPoints = r.geometry.coordinates; // [lng, lat]
                    const distNum = r.distance / 1000;
                    const distKm = distNum.toFixed(1);
                    let durMin = 0;

                    if (currentTravelMode === 'walk') {
                        durMin = distNum < 0.05 ? 0 : Math.max(1, Math.round((distNum / 4.5) * 60));
                    } else if (currentTravelMode === 'bike') {
                        durMin = distNum < 0.05 ? 0 : Math.max(1, Math.round((distNum / 14) * 60));
                    } else if (currentTravelMode === 'moto') {
                        durMin = distNum < 0.05 ? 0 : Math.max(1, Math.round((distNum / 38) * 60));
                    } else {
                        // Car (drive)
                        durMin = Math.max(1, Math.round(r.duration / 60));
                    }

                    let roadSummary = r.legs && r.legs[0] && r.legs[0].summary ? `ผ่าน ${r.legs[0].summary}` : (idx === 0 ? 'เส้นทางถนนสายหลัก' : 'เส้นทางสายรอง');
                    const steps = (r.legs && r.legs[0] && r.legs[0].steps) ? r.legs[0].steps : [];

                    newRoutes.push({
                        index: idx,
                        name: idx === 0 ? 'เส้นทางหลัก (เร็วที่สุด)' : `เส้นทางรอง ${idx + 1}`,
                        tag: idx === 0 ? 'แนะนำ' : 'ทางเลือก',
                        distKm: distKm,
                        timeMins: durMin,
                        timeText: formatDurationThai(durMin),
                        via: `${modeName} • ${roadSummary}`,
                        points: roadPoints,
                        steps: steps,
                        isEstimated: false,
                        isMain: idx === 0
                    });
                });

                currentRouteAlternatives = newRoutes;
                if (selectedRouteIndex >= currentRouteAlternatives.length) {
                    selectedRouteIndex = 0;
                }
                renderRouteCards();
                previewRoutesOnMap();

                if (activeNavRoute) {
                    activeNavRoute = currentRouteAlternatives[selectedRouteIndex] || currentRouteAlternatives[0];
                    renderRouteGeometryOnMap(activeNavRoute.points, true);
                    const nextDist = document.getElementById('gmapNavNextDist');
                    if (nextDist) nextDist.textContent = `${activeNavRoute.distKm} กม. (~${activeNavRoute.timeText})`;
                }

                return currentRouteAlternatives[selectedRouteIndex] || currentRouteAlternatives[0];
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                console.warn('OSRM route notice:', err.message);
                currentRouteAlternatives.forEach(route => {
                    route.via = `${modeName} • ไม่สามารถเชื่อมต่อบริการเส้นทาง แสดงระยะโดยประมาณ`;
                    route.isEstimated = true;
                });
                renderRouteCards();
                previewRoutesOnMap();

                if (activeNavRoute) {
                    activeNavRoute = currentRouteAlternatives[selectedRouteIndex] || currentRouteAlternatives[0];
                    renderRouteGeometryOnMap(activeNavRoute.points, true);
                }
            }
        }

        return currentRouteAlternatives[selectedRouteIndex] || currentRouteAlternatives[0] || null;
    }

    function renderRouteCards() {
        const cardsContainer = document.getElementById('gmapRouteCardsList');
        if (!cardsContainer) return;

        let html = '';
        currentRouteAlternatives.forEach((r, idx) => {
            const isSel = idx === selectedRouteIndex;
            html += `
                <div class="gmap-route-card ${isSel ? 'selected' : ''}" onclick="selectRouteCard(${idx})">
                    <div class="gmap-route-card-left">
                        <div class="gmap-route-card-tag">${r.tag} • ${r.name}</div>
                        <div class="flex items-baseline gap-2">
                            <span class="gmap-route-card-time">${r.timeText}</span>
                            <span class="gmap-route-card-dist">(${r.distKm} กม.)</span>
                        </div>
                        <div class="gmap-route-card-via">${r.via}</div>
                    </div>
                    <div class="gmap-route-card-radio"></div>
                </div>
            `;
        });

        cardsContainer.innerHTML = html;

        const startBtnText = document.getElementById('btnStartSelectedRouteText');
        if (startBtnText && currentRouteAlternatives[selectedRouteIndex]) {
            const sel = currentRouteAlternatives[selectedRouteIndex];
            startBtnText.textContent = `เริ่มนำทาง (${sel.name} • ${sel.timeText})`;
        }
    }

    window.selectRouteCard = function(index) {
        selectedRouteIndex = index;
        renderRouteCards();
        previewRoutesOnMap();
    };

    function previewRoutesOnMap() {
        if (!map || currentRouteAlternatives.length === 0) return;
        const isR0Active = selectedRouteIndex === 0;
        const mainRoute = isR0Active ? currentRouteAlternatives[0] : (currentRouteAlternatives[1] || currentRouteAlternatives[0]);
        const altRoute = isR0Active ? (currentRouteAlternatives[1] || null) : currentRouteAlternatives[0];

        renderRouteGeometryOnMap(mainRoute.points, true);

        if (map.getSource('route-alt-source')) {
            if (altRoute && altRoute.points && altRoute.points.length >= 2) {
                map.getSource('route-alt-source').setData({
                    type: 'FeatureCollection',
                    features: [{
                        type: 'Feature',
                        geometry: { type: 'LineString', coordinates: altRoute.points }
                    }]
                });
            } else {
                map.getSource('route-alt-source').setData({
                    type: 'FeatureCollection',
                    features: []
                });
            }
        }
    }

    function renderRouteGeometryOnMap(points, isPrimary) {
        if (!map) return;
        if (!map.getSource('route-primary-source')) {
            initVectorSourcesAndLayers();
        }
        if (!map.getSource('route-primary-source')) return;

        if (points && points.length >= 2) {
            map.getSource('route-primary-source').setData({
                type: 'FeatureCollection',
                features: [{
                    type: 'Feature',
                    geometry: { type: 'LineString', coordinates: points }
                }]
            });
        }

        if (activeLocation) {
            if (!destMarker) {
                const el = document.createElement('div');
                el.className = 'nav-dest-marker';
                el.innerHTML = `
                    <svg width="40" height="52" viewBox="0 0 40 52" fill="none">
                        <path d="M20 2C10.06 2 2 10.06 2 20c0 14 18 30 18 30s18-16 18-30C38 10.06 29.94 2 20 2z" fill="#ef4444"/>
                        <circle cx="20" cy="19" r="8" fill="#fff"/>
                        <circle cx="20" cy="19" r="4" fill="#ef4444"/>
                    </svg>
                `;
                destMarker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
                    .setLngLat([parseFloat(activeLocation.lng), parseFloat(activeLocation.lat)])
                    .addTo(map);
            } else {
                destMarker.setLngLat([parseFloat(activeLocation.lng), parseFloat(activeLocation.lat)]);
            }
        }
    }

    window.startNavigationWithSelectedRoute = async function() {
        const selRoute = currentRouteAlternatives[selectedRouteIndex] || currentRouteAlternatives[0];
        closeRouteSelector();
        await startNavigationToActive(selRoute);
    };

    window.startNavigationToActive = async function(chosenRoute) {
        const target = activeLocation;
        if (!target) return;

        if (!userCoords) {
            try {
                await requestCurrentLocation();
            } catch (error) {
                window.alert('กรุณาอนุญาตการเข้าถึงตำแหน่ง เพื่อเริ่มนำทางจากตำแหน่งปัจจุบัน');
                return;
            }
        }

        closeBottomSheet();
        closeRouteSelector();

        if (!chosenRoute || chosenRoute.isEstimated) {
            chosenRoute = await calculateAndRenderRouteOptions();
        }
        if (!chosenRoute) return;

        activeNavTarget = target;
        activeNavRoute = chosenRoute;

        const topBar = document.getElementById('gmapFloatingTop');
        if (topBar) topBar.style.display = 'none';

        const navBanner = document.getElementById('gmapNavBanner');
        if (navBanner) navBanner.style.display = 'flex';

        renderRouteGeometryOnMap(chosenRoute.points, true);

        // Immediately update HUD & Turn-by-Turn instruction
        updateNavigationPosition(userCoords || defaultCenter);

        // Voice announcement on navigation start
        speakNavigationGuidance(`เริ่มการนำทาง มุ่งหน้าไปยัง ${target.title}`, true);

        // Switch camera to 3D driving perspective
        map.flyTo({
            center: [userCoords[1], userCoords[0]],
            zoom: 17.5,
            pitch: 55,
            bearing: currentHeading || 0,
            duration: 1500
        });
    };

    window.clearNavigationRoute = function() {
        if ('speechSynthesis' in window) {
            window.speechSynthesis.cancel();
        }
        activeNavTarget = null;
        activeNavRoute = null;
        lastSpokenText = '';
        navigationReroutePending = false;
        clearAllRouteVisuals();

        const navBanner = document.getElementById('gmapNavBanner');
        if (navBanner) navBanner.style.display = 'none';

        const topBar = document.getElementById('gmapFloatingTop');
        if (topBar) topBar.style.display = 'flex';

        const startPoint = userCoords || defaultCenter;
        map.easeTo({
            center: [startPoint[1], startPoint[0]],
            zoom: 15,
            pitch: 0,
            bearing: 0,
            duration: 1000
        });
    };

    // ── Real-time GPS & Compass Tracking ──
    function startRealtimeLocationTracking() {
        if (!navigator.geolocation) return;

        watchGpsId = navigator.geolocation.watchPosition(
            (pos) => {
                userCoords = [pos.coords.latitude, pos.coords.longitude];
                if (pos.coords.heading !== null && !isNaN(pos.coords.heading)) {
                    currentHeading = Math.round(pos.coords.heading);
                }
                setUserGpsMarker();
                broadcastUserLocation(userCoords);
                updateNavigationPosition(userCoords);
            },
            (err) => {
                console.warn('GPS Watch Notice:', err.message);
            },
            { enableHighAccuracy: true, maximumAge: 3000, timeout: 8000 }
        );

        if (window.DeviceOrientationEvent) {
            window.addEventListener('deviceorientation', function(e) {
                if (e.webkitCompassHeading) {
                    currentHeading = Math.round(e.webkitCompassHeading);
                } else if (e.alpha !== null) {
                    currentHeading = Math.round(360 - e.alpha);
                }
                setUserGpsMarker();
            }, { passive: true });
        }
    }

    function requestCurrentLocation() {
        if (userCoords) return Promise.resolve(userCoords);
        if (!navigator.geolocation) return Promise.reject(new Error('Geolocation is unavailable'));

        return new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userCoords = [position.coords.latitude, position.coords.longitude];
                    setUserGpsMarker();
                    updateNearbyList();
                    resolve(userCoords);
                },
                reject,
                { enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 }
            );
        });
    }

    window.locateUserAndCenter = async function() {
        try {
            await requestCurrentLocation();
            if (userCoords) {
                map.flyTo({
                    center: [userCoords[1], userCoords[0]],
                    zoom: 17,
                    pitch: is3DModeActive ? 55 : 0,
                    duration: 1000
                });
            }
        } catch (e) {
            window.alert('ไม่สามารถระบุตำแหน่งของคุณได้ กรุณาอนุญาตการเข้าถึง GPS บนอุปกรณ์');
        }
    };

    function setUserGpsMarker() {
        if (!map || !userCoords) return;
        const [lat, lng] = userCoords;

        if (!userMarker) {
            const el = document.createElement('div');
            el.className = 'user-gps-heading-marker';
            el.innerHTML = `
                <div class="user-gps-cone" id="userHeadingCone" style="transform: rotate(${currentHeading}deg);"></div>
                <div class="user-gps-dot"></div>
            `;
            userMarker = new maplibregl.Marker({ element: el, anchor: 'center' })
                .setLngLat([lng, lat])
                .addTo(map);
        } else {
            userMarker.setLngLat([lng, lat]);
            const cone = document.getElementById('userHeadingCone');
            if (cone) cone.style.transform = `rotate(${currentHeading}deg)`;
        }
    }

    const MANEUVER_SVGS = {
        'turn-left': `<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 19v-6a4 4 0 0 0-4-4H5"/><polyline points="10 5 4 9 10 13"/></svg>`,
        'turn-right': `<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M5 19v-6a4 4 0 0 1 4-4h10"/><polyline points="14 5 20 9 14 13"/></svg>`,
        'slight-left': `<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M17 20l-4-9-6-3"/><polyline points="7 14 7 8 13 8"/></svg>`,
        'slight-right': `<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M7 20l4-9 6-3"/><polyline points="17 14 17 8 11 8"/></svg>`,
        'sharp-left': `<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M18 20V8a2 2 0 0 0-2-2H6"/><polyline points="10 2 6 6 10 10"/></svg>`,
        'sharp-right': `<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M6 20V8a2 2 0 0 1 2-2h10"/><polyline points="14 2 18 6 14 10"/></svg>`,
        'uturn': `<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M19 20V9a5 5 0 0 0-10 0v11"/><polyline points="13 16 9 20 5 16"/></svg>`,
        'straight': `<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>`,
        'roundabout': `<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4"/><path d="M16 8l4-2-2 4"/><path d="M8 16l-4 2 2-4"/></svg>`,
        'arrive': `<svg width="28" height="28" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>`
    };

    function getStepManeuverInfo(step) {
        if (!step) return { action: 'ตรงไป', name: '', text: 'ตรงไปตามเส้นทาง', icon: 'straight' };
        const m = step.maneuver || {};
        const type = m.type || 'turn';
        const mod = m.modifier || '';
        let name = step.name ? step.name.trim() : (step.ref ? step.ref.trim() : '');

        let action = 'ตรงไป';
        let iconKey = 'straight';

        if (type === 'depart') {
            action = 'มุ่งหน้า';
            iconKey = 'straight';
        } else if (type === 'arrive') {
            action = 'ถึงปลายทาง';
            iconKey = 'arrive';
        } else if (type === 'roundabout' || type === 'rotary') {
            action = 'เข้าสู่วงเวียน';
            iconKey = 'roundabout';
        } else if (mod === 'uturn') {
            action = 'กลับรถ / ยูเทิร์น';
            iconKey = 'uturn';
        } else if (mod === 'sharp left') {
            action = 'เลี้ยวหักศอกซ้าย';
            iconKey = 'sharp-left';
        } else if (mod === 'sharp right') {
            action = 'เลี้ยวหักศอกขวา';
            iconKey = 'sharp-right';
        } else if (mod === 'slight left') {
            action = 'เบี่ยงซ้าย';
            iconKey = 'slight-left';
        } else if (mod === 'slight right') {
            action = 'เบี่ยงขวา';
            iconKey = 'slight-right';
        } else if (mod === 'left') {
            action = 'เลี้ยวซ้าย';
            iconKey = 'turn-left';
        } else if (mod === 'right') {
            action = 'เลี้ยวขวา';
            iconKey = 'turn-right';
        } else if (type === 'fork') {
            action = mod && mod.includes('left') ? 'ชิดซ้ายที่ทางแยก' : 'ชิดขวาที่ทางแยก';
            iconKey = mod && mod.includes('left') ? 'slight-left' : 'slight-right';
        } else if (type === 'end of road') {
            action = mod && mod.includes('left') ? 'สุดทางเลี้ยวซ้าย' : 'สุดทางเลี้ยวขวา';
            iconKey = mod && mod.includes('left') ? 'turn-left' : 'turn-right';
        } else if (type === 'continue' || mod === 'straight') {
            action = 'ตรงไป';
            iconKey = 'straight';
        }

        let text = action;
        if (name) {
            text += ` เข้าสู่ ${name}`;
        } else if (action === 'ตรงไป') {
            text = 'ตรงไปตามเส้นทาง';
        }

        return { action, name, text, icon: iconKey };
    }

    function formatEtaClock(minutesRemaining) {
        const d = new Date(Date.now() + minutesRemaining * 60000);
        const hh = String(d.getHours()).padStart(2, '0');
        const mm = String(d.getMinutes()).padStart(2, '0');
        return `ถึงประมาณ ${hh}:${mm} น.`;
    }

    let isVoiceEnabled = true;
    let lastSpokenText = '';
    let lastSpokenTime = 0;

    function speakNavigationGuidance(text, isPriority = false) {
        if (!isVoiceEnabled || !('speechSynthesis' in window)) return;
        if (!text) return;
        if (text === lastSpokenText && (Date.now() - lastSpokenTime < 12000) && !isPriority) return;

        lastSpokenText = text;
        lastSpokenTime = Date.now();

        try {
            window.speechSynthesis.cancel();
            const utterance = new SpeechSynthesisUtterance(text);
            utterance.lang = 'th-TH';
            utterance.rate = 1.05;
            utterance.pitch = 1.0;

            const voices = window.speechSynthesis.getVoices();
            const thaiVoice = voices.find(v => v.lang.includes('th') || v.name.includes('Thai') || v.lang === 'th-TH');
            if (thaiVoice) {
                utterance.voice = thaiVoice;
            }

            window.speechSynthesis.speak(utterance);
        } catch(e) {
            console.warn('Voice notice:', e);
        }
    }

    window.toggleVoiceGuidance = function() {
        isVoiceEnabled = !isVoiceEnabled;
        const btn = document.getElementById('gmapNavVoiceBtn');
        if (btn) {
            btn.innerHTML = isVoiceEnabled ? `
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                    <path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>
                </svg>
            ` : `
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.3" viewBox="0 0 24 24">
                    <polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon>
                    <line x1="23" y1="9" x2="17" y2="15"></line>
                    <line x1="17" y1="9" x2="23" y2="15"></line>
                </svg>
            `;
            btn.title = isVoiceEnabled ? 'ปิดเสียงนำทาง' : 'เปิดเสียงนำทาง';
        }

        if (isVoiceEnabled) {
            speakNavigationGuidance('เปิดเสียงนำทาง', true);
        } else {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        }
    };

    function updateNavigationPosition(position) {
        if (!activeNavTarget || !activeNavRoute) return;

        const remainingKm = calculateDistance(position[0], position[1], activeNavTarget.lat, activeNavTarget.lng);
        const speedKmH = currentTravelMode === 'walk' ? 4.5 : (currentTravelMode === 'bike' ? 14 : (currentTravelMode === 'moto' ? 38 : 38));
        const remainingMinutes = remainingKm < 0.05 ? 0 : Math.max(1, Math.round((remainingKm / speedKmH) * 60));

        // Find upcoming maneuver step
        let currentStep = null;
        let distToStepMeters = 0;

        if (activeNavRoute && activeNavRoute.steps && activeNavRoute.steps.length > 0) {
            const steps = activeNavRoute.steps;
            for (let i = 0; i < steps.length; i++) {
                const s = steps[i];
                const sLoc = s.maneuver && s.maneuver.location ? s.maneuver.location : null; // [lng, lat]
                if (sLoc) {
                    const dKm = calculateDistance(position[0], position[1], sLoc[1], sLoc[0]);
                    const dM = Math.round(dKm * 1000);
                    if (dM > 18 || i === steps.length - 1) {
                        currentStep = s;
                        distToStepMeters = dM;
                        break;
                    }
                }
            }
        }

        const turnIcon = document.getElementById('gmapNavTurnIcon');
        const turnDistEl = document.getElementById('gmapNavTurnDist');
        const instructionEl = document.getElementById('gmapNavInstruction');
        const totalTimeEl = document.getElementById('gmapNavTotalTime');
        const totalDistEl = document.getElementById('gmapNavTotalDist');
        const etaClockEl = document.getElementById('gmapNavEtaClock');
        const destLabelEl = document.getElementById('gmapNavDestLabel');

        if (remainingKm <= 0.03) {
            if (turnIcon) turnIcon.innerHTML = MANEUVER_SVGS['arrive'];
            if (turnDistEl) turnDistEl.textContent = 'ถึงแล้ว';
            if (instructionEl) instructionEl.textContent = `คุณมาถึง ${activeNavTarget.title} แล้ว`;
            if (totalTimeEl) totalTimeEl.textContent = 'ถึงปลายทาง';
            speakNavigationGuidance(`คุณเดินทางถึง ${activeNavTarget.title} แล้ว`, true);
            return;
        }

        if (currentStep) {
            const info = getStepManeuverInfo(currentStep);
            if (turnIcon) turnIcon.innerHTML = MANEUVER_SVGS[info.icon] || MANEUVER_SVGS['straight'];
            if (turnDistEl) {
                turnDistEl.textContent = distToStepMeters < 1000 ? `อีก ${distToStepMeters} ม.` : `อีก ${(distToStepMeters / 1000).toFixed(1)} กม.`;
            }
            if (instructionEl) instructionEl.textContent = info.text;

            // Voice announcement when approaching a turn
            if (distToStepMeters <= 80 && distToStepMeters > 20) {
                speakNavigationGuidance(`อีก ${distToStepMeters} เมตร ${info.text}`);
            }
        } else {
            if (turnIcon) turnIcon.innerHTML = MANEUVER_SVGS['straight'];
            if (turnDistEl) turnDistEl.textContent = remainingKm < 1 ? `อีก ${Math.round(remainingKm * 1000)} ม.` : `อีก ${remainingKm.toFixed(1)} กม.`;
            if (instructionEl) instructionEl.textContent = `มุ่งหน้าไปยัง ${activeNavTarget.title}`;
        }

        if (totalTimeEl) totalTimeEl.textContent = formatDurationThai(remainingMinutes);
        if (totalDistEl) totalDistEl.textContent = remainingKm < 1 ? `${Math.round(remainingKm * 1000)} ม.` : `${remainingKm.toFixed(1)} กม.`;
        if (etaClockEl) etaClockEl.textContent = formatEtaClock(remainingMinutes);
        if (destLabelEl) destLabelEl.textContent = activeNavTarget.title;

        // Real-time Route Polyline Live-Snapping & Ahead-Trimming
        if (activeNavRoute && activeNavRoute.points && activeNavRoute.points.length > 0) {
            const pts = activeNavRoute.points;
            let minD = Infinity;
            let closestIndex = 0;

            for (let i = 0; i < pts.length; i++) {
                const d = calculateDistance(position[0], position[1], pts[i][1], pts[i][0]);
                if (d < minD) {
                    minD = d;
                    closestIndex = i;
                }
            }

            if (minD <= 0.06) {
                // User is on or near route (within 60m): trim passed segments behind user
                const aheadPoints = pts.slice(closestIndex + 1);
                const livePoints = [[position[1], position[0]], ...aheadPoints];
                if (livePoints.length >= 2) {
                    activeNavRoute.points = livePoints;
                    renderRouteGeometryOnMap(livePoints, true);
                }
            } else if (minD > 0.06 && remainingKm > 0.05) {
                // Off-route (>60m): trigger real-time dynamic rerouting
                triggerRealtimeReroute(position);
            }
        }

        if (is3DModeActive || activeNavRoute) {
            map.easeTo({
                center: [position[1], position[0]],
                bearing: currentHeading || 0,
                duration: 500
            });
        }
    }

    async function triggerRealtimeReroute(position) {
        if (isRerouting || (Date.now() - lastRerouteTime < 4000)) return;
        const target = activeNavTarget;
        if (!target) return;

        isRerouting = true;
        lastRerouteTime = Date.now();
        speakNavigationGuidance('กำลังคำนวณเส้นทางใหม่', true);

        try {
            let osrmProfile = 'driving';
            if (currentTravelMode === 'walk') osrmProfile = 'walking';
            else if (currentTravelMode === 'bike') osrmProfile = 'cycling';
            else if (currentTravelMode === 'moto') osrmProfile = 'driving';

            const osrmUrl = `https://router.project-osrm.org/route/v1/${osrmProfile}/${position[1]},${position[0]};${parseFloat(target.lng)},${parseFloat(target.lat)}?overview=full&geometries=geojson&steps=true`;
            const resp = await fetch(osrmUrl);
            if (resp.ok) {
                const data = await resp.json();
                if (data && data.code === 'Ok' && data.routes && data.routes.length > 0) {
                    const r = data.routes[0];
                    const roadPoints = r.geometry.coordinates; // [lng, lat]
                    const distNum = r.distance / 1000;
                    const speedKmH = currentTravelMode === 'walk' ? 4.5 : (currentTravelMode === 'bike' ? 14 : (currentTravelMode === 'moto' ? 38 : 38));
                    const durMin = currentTravelMode === 'drive' ? Math.max(1, Math.round(r.duration / 60)) : (distNum < 0.05 ? 0 : Math.max(1, Math.round((distNum / speedKmH) * 60)));
                    const steps = (r.legs && r.legs[0] && r.legs[0].steps) ? r.legs[0].steps : [];

                    activeNavRoute = {
                        name: 'เส้นทางปัจจุบัน',
                        distKm: distNum.toFixed(1),
                        timeMins: durMin,
                        timeText: formatDurationThai(durMin),
                        points: roadPoints,
                        steps: steps
                    };

                    renderRouteGeometryOnMap(roadPoints, true);
                    updateNavigationPosition(position);
                }
            }
        } catch (err) {
            console.warn('Real-time reroute notice:', err);
        } finally {
            isRerouting = false;
        }
    }

    // ── Laravel Reverb Realtime Peer Tracking ──
    function initReverbMapTracking() {
        if (!window.Echo || !authUserId) return;

        try {
            window.Echo.join('map.tracking')
                .here((users) => {
                    users.forEach(u => {
                        if (String(u.id) !== String(authUserId) && u.lat && u.lng) {
                            updatePeerMarker(u);
                        }
                    });
                })
                .joining((user) => {
                    if (String(user.id) !== String(authUserId) && user.lat && user.lng) {
                        updatePeerMarker(user);
                    }
                })
                .leaving((user) => {
                    removePeerMarker(user.id);
                })
                .listen('.UserLocationUpdated', (e) => {
                    if (String(e.user_id) !== String(authUserId)) {
                        updatePeerMarker(e);
                    }
                });
        } catch(e) {
            console.warn('Reverb Map Notice:', e);
        }
    }

    function broadcastUserLocation(coords) {
        if (!authUserId) return;
        const now = Date.now();
        if (now - lastBroadcastTime < 4000) return;
        lastBroadcastTime = now;

        fetch('{{ route("api.map.update_location") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({ lat: coords[0], lng: coords[1], heading: currentHeading })
        }).catch(() => {});
    }

    function updatePeerMarker(user) {
        if (!map || !user.lat || !user.lng) return;
        const uid = user.id || user.user_id;

        if (peerMarkers[uid]) {
            peerMarkers[uid].setLngLat([parseFloat(user.lng), parseFloat(user.lat)]);
        } else {
            const el = document.createElement('div');
            el.className = 'live-peer-marker';
            el.innerHTML = `
                <div class="live-peer-avatar">
                    ${user.avatar ? `<img src="${user.avatar}" alt="">` : (user.name ? user.name.charAt(0) : 'U')}
                </div>
                <div class="live-peer-badge">${user.name || 'สมาชิก'}</div>
            `;
            const marker = new maplibregl.Marker({ element: el, anchor: 'bottom' })
                .setLngLat([parseFloat(user.lng), parseFloat(user.lat)])
                .addTo(map);
            peerMarkers[uid] = marker;
        }
    }

    function removePeerMarker(uid) {
        if (peerMarkers[uid]) {
            peerMarkers[uid].remove();
            delete peerMarkers[uid];
        }
    }

    // Nearby Drawer & List
    window.toggleNearbyDrawer = function() {
        const drawer = document.getElementById('gmapNearbyDrawer');
        drawer.style.display = drawer.style.display === 'none' ? 'flex' : 'none';
        if (drawer.style.display === 'flex') {
            updateNearbyList();
        }
    };

    function updateNearbyList() {
        const listEl = document.getElementById('gmapNearbyList');
        if (!listEl) return;

        let filtered = getFilteredLocations();

        if (userCoords) {
            filtered = [...filtered].sort((a, b) => {
                const distA = calculateDistance(userCoords[0], userCoords[1], a.lat, a.lng);
                const distB = calculateDistance(userCoords[0], userCoords[1], b.lat, b.lng);
                return distA - distB;
            });
        }

        if (filtered.length === 0) {
            listEl.innerHTML = '<div style="padding:2rem;text-align:center;color:#94a3b8;font-size:0.88rem;">ไม่พบสถานที่ตามเงื่อนไข</div>';
            return;
        }

        let html = '';
        filtered.forEach(loc => {
            let distText = '';
            if (userCoords) {
                const distKm = calculateDistance(userCoords[0], userCoords[1], loc.lat, loc.lng);
                distText = distKm < 1 ? Math.round(distKm * 1000) + ' ม.' : distKm.toFixed(1) + ' กม.';
            }

            html += `
                <div class="gmap-place-card" onclick="focusLocationFromList(${loc.id}, '${loc.type}')">
                    <div class="gmap-thumb" style="width:48px;height:48px;">
                        ${loc.image ? `<img src="${loc.image}" alt="">` : `<div class="gmap-thumb-fallback"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/></svg></div>`}
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;font-size:0.88rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#0f172a;">${loc.title}</div>
                        <div style="font-size:0.75rem;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${loc.location_name}</div>
                    </div>
                    ${distText ? `<span class="gmap-distance-chip">${distText}</span>` : ''}
                </div>
            `;
        });

        listEl.innerHTML = html;
    }

    window.focusLocationFromList = function(id, type) {
        const target = allLocations.find(l => String(l.id) === String(id) && l.type === type);
        if (target) {
            document.getElementById('gmapNearbyDrawer').style.display = 'none';
            focusLocation(target);
        }
    };

    // Haversine Distance Formula (km)
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const numLat1 = parseFloat(lat1) || 0;
        const numLon1 = parseFloat(lon1) || 0;
        const numLat2 = parseFloat(lat2) || 0;
        const numLon2 = parseFloat(lon2) || 0;
        const dLat = (numLat2 - numLat1) * Math.PI / 180;
        const dLon = (numLon2 - numLon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(numLat1 * Math.PI / 180) * Math.cos(numLat2 * Math.PI / 180) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }

    function formatDurationThai(mins) {
        if (mins <= 0) return 'น้อยกว่า 1 นาที';
        if (mins < 60) return `${mins} นาที`;
        const h = Math.floor(mins / 60);
        const m = mins % 60;
        return m > 0 ? `${h} ชม. ${m} นาที` : `${h} ชม.`;
    }
})();
</script>
@endsection
