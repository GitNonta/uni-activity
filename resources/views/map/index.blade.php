@extends('layouts.app')
@section('title', 'แผนที่กิจกรรมและงาน - UNI Activity')

@section('content')
<div class="map-explorer-wrapper">
    {{-- ── 1. Floating Top Bar: Google Maps Search & Quick Filters ── --}}
    <div class="gmap-floating-top">
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
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
        <div class="gmap-nav-banner-icon" id="gmapNavTurnIcon">
            <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
            </svg>
        </div>
        <div class="gmap-nav-banner-info">
            <div class="gmap-nav-banner-dist" id="gmapNavNextDist">กำลังคำนวณเส้นทาง...</div>
            <div class="gmap-nav-banner-text" id="gmapNavNextText">กำลังโหลดคำแนะนำเส้นทาง</div>
        </div>
        <button type="button" class="gmap-nav-exit-btn" onclick="clearNavigationRoute()" title="สิ้นสุดการนำทาง">
            <span>สิ้นสุด</span>
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    {{-- ── 3. Main Map Canvas ── --}}
    <div class="map-canvas-container">
        <div id="unifiedMap" style="width:100%;height:100%;"></div>

        {{-- Floating Action Buttons (Right Side) --}}
        <div class="gmap-fab-column">
            {{-- Layer Switcher FAB --}}
            <button type="button" class="gmap-fab" onclick="toggleLayerSheet()" title="เปลี่ยนรูปแบบแผนที่">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
            </button>

            {{-- GPS Current Location FAB --}}
            <button type="button" class="gmap-fab gmap-fab-gps" id="btnGpsLocate" onclick="locateUserAndCenter()" title="ระบุตำแหน่งของฉัน">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
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
                <span>รูปแบบแผนที่</span>
                <button type="button" onclick="toggleLayerSheet()" class="gmap-icon-btn">✕</button>
            </div>
            <div class="gmap-layer-grid">
                <div class="gmap-layer-card active" id="layerCard-streets" onclick="switchMapLayer('streets')">
                    <div class="gmap-layer-preview preview-streets"></div>
                    <span>ปกติ (ค่าเริ่มต้น)</span>
                </div>
                <div class="gmap-layer-card" id="layerCard-satellite" onclick="switchMapLayer('satellite')">
                    <div class="gmap-layer-preview preview-satellite"></div>
                    <span>ภาพถ่ายดาวเทียม</span>
                </div>
                <div class="gmap-layer-card" id="layerCard-dark" onclick="switchMapLayer('dark')">
                    <div class="gmap-layer-preview preview-dark"></div>
                    <span>โหมดมืด (Dark)</span>
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/>
                        </svg>
                        <span>เส้นทาง</span>
                    </button>

                    <button type="button" id="bs-native-btn" onclick="openActiveNativeMap()" class="gmap-btn-native-detect" title="เปิดในแอปแผนที่ของเครื่อง">
                        <span id="bs-native-icon">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg>
                        </span>
                        <span id="bs-native-text">แอป</span>
                    </button>

                    <button type="button" class="gmap-btn-icon" onclick="shareActiveLocation()" title="แชร์สถานที่นี้">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/>
                        </svg>
                    </button>
                </div>

                {{-- Quick ETA Estimation Info --}}
                <div class="gmap-eta-grid">
                    <div class="gmap-eta-card card-walk" onclick="selectModeFromCard('walk')">
                        <div class="gmap-eta-label">
                            <div class="gmap-eta-icon-wrap icon-walk">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                </svg>
                            </div>
                            <span>เดินเท้า</span>
                        </div>
                        <div id="bs-walk-eta" class="gmap-eta-value">-</div>
                    </div>
                    <div class="gmap-eta-card card-drive" onclick="selectModeFromCard('drive')">
                        <div class="gmap-eta-label">
                            <div class="gmap-eta-icon-wrap icon-drive">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                                </svg>
                            </div>
                            <span>ขับขี่ / รถยนต์</span>
                        </div>
                        <div id="bs-drive-eta" class="gmap-eta-value">-</div>
                    </div>
                </div>

                {{-- Expanded Additional Content --}}
                <div class="gmap-sheet-expanded-content">
                    <div class="gmap-info-section">
                        <div class="gmap-info-item">
                            <div class="gmap-info-icon">
                                <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="gmap-info-text-block">
                                <div class="gmap-info-label">ข้อมูลกิจกรรม / ตำแหน่งงาน</div>
                                <div id="bs-meta-info" class="gmap-info-text">-</div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <a id="bs-detail-btn" href="#" class="gmap-btn-detail">
                            <span>ดูหน้าข้อมูลและสมัคร / เช็คชื่อ</span>
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    </div>

                    {{-- Direct Native App Launchers with Device Recommendation --}}
                    <div class="gmap-apps-tray">
                        <div class="gmap-apps-tray-title">เปิดโดยตรงผ่านแอปแผนที่:</div>
                        <div class="gmap-apps-grid">
                            <a id="bs-gmaps-btn" href="#" onclick="launchNativeApp('google', event)" class="gmap-app-pill app-google">
                                <svg class="gmap-brand-icon" viewBox="0 0 24 24" width="16" height="16">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                </svg>
                                <span>Google Maps</span>
                                <span class="app-recom-tag" id="tag-recom-google" style="display:none;">แนะนำ</span>
                            </a>

                            <a id="bs-applemaps-btn" href="#" onclick="launchNativeApp('apple', event)" class="gmap-app-pill app-apple">
                                <svg class="gmap-brand-icon" viewBox="0 0 170 170" width="16" height="16" fill="currentColor">
                                    <path d="M150.37 130.25c-2.45 5.66-5.35 10.87-8.71 15.66-4.58 6.53-8.33 11.05-11.22 13.56-4.48 4.12-9.28 6.23-14.42 6.35-3.69 0-8.14-1.05-13.32-3.18-5.19-2.12-9.97-3.17-14.34-3.17-4.58 0-9.49 1.05-14.75 3.17-5.26 2.13-9.5 3.24-12.74 3.35-4.35.13-9.16-1.9-14.42-6.08-3.69-3.08-7.7-7.91-12.03-14.5-5.64-8.59-10.15-18.49-13.51-29.7-3.37-11.2-5.06-22.02-5.06-32.46 0-14.75 3.8-27.18 11.41-37.3 7.6-10.13 17.2-15.26 28.79-15.41 4.71 0 9.87 1.25 15.48 3.75 5.62 2.5 9.4 3.8 11.35 3.9 1.52 0 5.48-1.39 11.89-4.17 6.41-2.77 12.06-4.04 16.94-3.8 12.52.62 22.56 5.16 30.12 13.62-10.88 6.59-16.2 15.71-15.96 27.36.24 9.17 3.86 16.89 10.87 23.16 7 6.27 15.34 9.82 25.01 10.66-2.07 6.28-4.73 12.77-7.98 19.46zM119.22 31.84c0-7.23 2.65-13.9 7.94-20.02 5.3-6.12 11.78-9.98 19.46-11.58.22 1.09.33 2.18.33 3.27 0 7.02-2.74 13.67-8.23 19.95-5.49 6.28-12.18 10.02-20.07 11.22-.11-.98-.22-1.94-.22-2.84z"/>
                                </svg>
                                <span>Apple Maps</span>
                                <span class="app-recom-tag" id="tag-recom-apple" style="display:none;">แนะนำ</span>
                            </a>

                            <a id="bs-waze-btn" href="#" onclick="launchNativeApp('waze', event)" class="gmap-app-pill app-waze">
                                <svg class="gmap-brand-icon" viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                    <path d="M12.003 2c-5.523 0-10 4.477-10 10 0 2.22.723 4.27 1.948 5.928l-.948 3.072 3.19-.877c1.69.96 3.66 1.877 5.81 1.877 5.523 0 10-4.477 10-10s-4.477-10-10-10zm-3.5 12c-.828 0-1.5-.672-1.5-1.5s.672-1.5 1.5-1.5 1.5.672 1.5 1.5-.672 1.5-1.5 1.5zm7 0c-.828 0-1.5-.672-1.5-1.5s.672-1.5 1.5-1.5 1.5.672 1.5 1.5-.672 1.5-1.5 1.5z"/>
                                </svg>
                                <span>Waze</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── 5. Route Selection & Travel Mode Modal Sheet ── --}}
        <div id="gmapRouteSelectorSheet" class="gmap-route-sheet" style="display:none;">
            {{-- Header: Destination + Close --}}
            <div class="gmap-route-sheet-header">
                <div class="gmap-route-header-info">
                    <span class="gmap-route-subtitle">เลือกเส้นทางไปยัง</span>
                    <h3 id="gmapRouteDestTitle" class="gmap-route-title">จุดหมาย</h3>
                </div>
                <button type="button" class="gmap-sheet-close" onclick="closeRouteSelector()" title="ปิด">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Travel Mode Chips (Driving, Motorbike, Walk, Bike) --}}
            <div class="gmap-travel-modes-row">
                <button type="button" class="gmap-mode-chip active" data-mode="drive" onclick="selectTravelMode('drive', this)">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0"/>
                    </svg>
                    <span>รถยนต์</span>
                </button>

                <button type="button" class="gmap-mode-chip" data-mode="moto" onclick="selectTravelMode('moto', this)">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    <span>มอเตอร์ไซค์</span>
                </button>

                <button type="button" class="gmap-mode-chip" data-mode="walk" onclick="selectTravelMode('walk', this)">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span>เดินเท้า</span>
                </button>

                <button type="button" class="gmap-mode-chip" data-mode="bike" onclick="selectTravelMode('bike', this)">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="5.5" cy="17.5" r="3.5" stroke-width="2"/>
                        <circle cx="18.5" cy="17.5" r="3.5" stroke-width="2"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 6h-3l-3 7h6l2-4h2"/>
                    </svg>
                    <span>จักรยาน</span>
                </button>
            </div>

            {{-- Route Options Cards (Alternatives) --}}
            <div class="gmap-route-cards-list" id="gmapRouteCardsList">
                <div style="padding:1rem;text-align:center;color:#94a3b8;font-size:0.85rem;">กำลังค้นหาเส้นทางที่ดีที่สุด...</div>
            </div>

            {{-- Bottom Start Button --}}
            <div class="gmap-route-sheet-footer">
                <button type="button" id="btnStartSelectedRoute" onclick="startNavigationWithSelectedRoute()" class="gmap-btn-start-nav w-full">
                    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                    </svg>
                    <span id="btnStartSelectedRouteText">เริ่มนำทางตามเส้นทางนี้</span>
                </button>
            </div>
        </div>

        {{-- ── 5. Nearby Places Drawer (Google Maps List Drawer) ── --}}
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
                <button type="button" class="gmap-icon-btn" onclick="toggleNearbyDrawer()">✕</button>
            </div>
            <div id="gmapNearbyList" class="gmap-drawer-body">
                <div style="padding:2rem;text-align:center;color:#94a3b8;">กำลังโหลดรายการสถานที่...</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
{{-- Leaflet, Routing, Heatmap, MarkerCluster --}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />
<link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.css" />

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="https://unpkg.com/leaflet-routing-machine@3.2.12/dist/leaflet-routing-machine.min.js"></script>

<style>
/* ── Fullscreen Map App Layout (Hide Top Navbar, Bottom Nav & Floating Chat Widget) ── */
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
    background: #f8fafc;
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
        max-width: 440px;
    }
}

.gmap-search-pill {
    pointer-events: auto;
    background: #ffffff;
    border-radius: 28px;
    height: 48px;
    padding: 0 10px 0 6px;
    display: flex;
    align-items: center;
    gap: 8px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.15), 0 1px 3px rgba(0,0,0,0.08);
    border: 1px solid rgba(0,0,0,0.06);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.gmap-search-pill:focus-within {
    box-shadow: 0 6px 24px rgba(234, 88, 12, 0.25), 0 2px 6px rgba(0,0,0,0.1);
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
    width: 38px;
    height: 38px;
    border-radius: 50%;
    transition: background 0.15s, color 0.15s;
    flex-shrink: 0;
}
.gmap-back-btn:hover {
    background: #f1f5f9;
    color: #ea580c;
}
html[data-theme="dark"] .gmap-back-btn {
    color: #f4f4f5;
}
html[data-theme="dark"] .gmap-back-btn:hover {
    background: #27272a;
    color: #ea580c;
}
.gmap-search-icon {
    color: #64748b;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.gmap-search-input {
    flex: 1;
    border: none;
    outline: none;
    background: transparent;
    font-size: 0.92rem;
    color: #0f172a;
    font-family: inherit;
}
.gmap-search-input::placeholder {
    color: #94a3b8;
}
.gmap-search-clear {
    background: #f1f5f9;
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #64748b;
    padding: 0;
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
    background: #ea580c;
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
    background: linear-gradient(135deg, #15803d, #16a34a);
    color: #fff;
    border-radius: 16px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 8px 24px rgba(22, 163, 74, 0.35);
    animation: slideDown 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-16px); }
    to { opacity: 1; transform: translateY(0); }
}
.gmap-nav-banner-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    background: rgba(255,255,255,0.2);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.gmap-nav-banner-info {
    flex: 1;
    min-width: 0;
}
.gmap-nav-banner-dist {
    font-size: 1.1rem;
    font-weight: 800;
    line-height: 1.2;
}
.gmap-nav-banner-text {
    font-size: 0.8rem;
    opacity: 0.9;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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
.gmap-fab-gps {
    color: #0284c7;
}
.gmap-fab-badge-count {
    position: absolute;
    top: -3px;
    right: -3px;
    background: #ea580c;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 800;
    min-width: 18px;
    height: 18px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
}

/* ── 4. Layer Switcher Sheet ── */
.gmap-layer-sheet {
    position: absolute;
    right: 14px;
    bottom: 80px;
    z-index: 1050;
    background: #ffffff;
    border-radius: 16px;
    padding: 14px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
    border: 1px solid #e2e8f0;
    width: 280px;
    animation: fadeIn 0.2s ease;
}
.gmap-layer-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-weight: 700;
    font-size: 0.88rem;
    margin-bottom: 10px;
    color: #0f172a;
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
    gap: 4px;
    cursor: pointer;
    font-size: 0.72rem;
    font-weight: 600;
    color: #64748b;
    text-align: center;
}
.gmap-layer-preview {
    width: 100%;
    height: 52px;
    border-radius: 8px;
    border: 2px solid transparent;
    background-size: cover;
    background-position: center;
    transition: all 0.15s;
}
.preview-streets { background-color: #e2e8f0; background-image: radial-gradient(#cbd5e1 1px, transparent 1px); background-size: 8px 8px; }
.preview-satellite { background-color: #0f172a; background-image: linear-gradient(135deg, #1e293b, #0f172a); }
.preview-dark { background-color: #18181b; }
.gmap-layer-card.active .gmap-layer-preview {
    border-color: #ea580c;
    box-shadow: 0 0 0 2px rgba(234, 88, 12, 0.3);
}
.gmap-layer-card.active { color: #ea580c; font-weight: 700; }

/* ── 5. Google Maps Bottom Sheet Refined ── */
.gmap-bottom-sheet {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-radius: 24px 24px 0 0;
    box-shadow: 0 -10px 40px rgba(0,0,0,0.14);
    transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    max-height: 88%;
    display: flex;
    flex-direction: column;
    border-top: 1px solid rgba(0,0,0,0.06);
}
.gmap-sheet-handle-zone {
    width: 100%;
    padding: 10px 0 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: grab;
}
.gmap-sheet-handle {
    width: 42px;
    height: 4.5px;
    border-radius: 3px;
    background: #cbd5e1;
}
.gmap-sheet-inner {
    padding: 0 16px 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

/* Card Head */
.gmap-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
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
    background: rgba(234, 88, 12, 0.08);
    color: #ea580c;
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
    background: linear-gradient(135deg, #10b981, #059669);
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
    box-shadow: 0 4px 14px rgba(16, 185, 129, 0.38);
    transition: all 0.15s ease;
}
.gmap-btn-start-nav:hover {
    filter: brightness(1.08);
    transform: translateY(-1px);
    box-shadow: 0 6px 18px rgba(16, 185, 129, 0.48);
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
    background: #ecfdf5;
    color: #059669;
    border-color: #10b981;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
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
    background: #f0fdf4;
    border-color: #10b981;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
}
.gmap-route-card-left {
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.gmap-route-card-tag {
    font-size: 0.7rem;
    font-weight: 800;
    color: #059669;
    display: flex;
    align-items: center;
    gap: 4px;
}
.gmap-route-card-time {
    font-size: 1.15rem;
    font-weight: 800;
    color: #0f172a;
}
.gmap-route-card-dist {
    font-size: 0.8rem;
    color: #64748b;
    font-weight: 600;
}
.gmap-route-card-via {
    font-size: 0.72rem;
    color: #94a3b8;
}
.gmap-route-card-radio {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    border: 2px solid #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
}
.gmap-route-card.selected .gmap-route-card-radio {
    border-color: #10b981;
    background: #10b981;
}
.gmap-route-card.selected .gmap-route-card-radio::after {
    content: '';
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #fff;
}
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
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
}
.gmap-eta-card {
    border-radius: 14px;
    padding: 9px 12px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    border: 1px solid transparent;
}
.card-walk {
    background: #fff7ed;
    border-color: #ffedd5;
}
.card-drive {
    background: #f0f9ff;
    border-color: #e0f2fe;
}
.gmap-eta-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
}
.gmap-eta-icon-wrap {
    width: 20px;
    height: 20px;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.icon-walk { background: rgba(234, 88, 12, 0.15); color: #ea580c; }
.icon-drive { background: rgba(2, 132, 199, 0.15); color: #0284c7; }
.gmap-eta-value {
    font-size: 1rem;
    font-weight: 800;
    color: #0f172a;
}

/* Info Section */
.gmap-info-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    padding: 10px 14px;
}
.gmap-info-item {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.gmap-info-icon {
    color: #ea580c;
    margin-top: 2px;
    flex-shrink: 0;
}
.gmap-info-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: #64748b;
    margin-bottom: 2px;
}
.gmap-info-text {
    font-size: 0.85rem;
    color: #1e293b;
    font-weight: 600;
    line-height: 1.4;
}

/* Detail Button */
.gmap-btn-detail {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f1f5f9;
    color: #0f172a;
    font-weight: 700;
    font-size: 0.86rem;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 11px 16px;
    text-decoration: none;
    transition: all 0.15s;
}
.gmap-btn-detail:hover {
    background: #e2e8f0;
    color: #0f172a;
    text-decoration: none;
}

/* App Tray */
.gmap-apps-tray {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-top: 4px;
}
.gmap-apps-tray-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.gmap-apps-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}
.gmap-app-pill {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 8px 6px;
    font-size: 0.74rem;
    font-weight: 700;
    color: #334155;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    text-decoration: none;
    position: relative;
    transition: all 0.15s;
}
.gmap-app-pill:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    transform: translateY(-1px);
}
.gmap-app-pill.recom {
    border-color: #ea580c;
    background: #fff7ed;
    color: #ea580c;
}
.app-recom-tag {
    font-size: 0.55rem;
    font-weight: 800;
    background: #ea580c;
    color: #fff;
    padding: 1px 4px;
    border-radius: 4px;
    line-height: 1;
}

/* ── 6. Nearby Places Drawer ── */
.gmap-nearby-drawer {
    position: absolute;
    top: 0;
    left: 0;
    bottom: 0;
    width: 360px;
    max-width: 85%;
    z-index: 1200;
    background: #ffffff;
    box-shadow: 6px 0 24px rgba(0,0,0,0.18);
    display: flex;
    flex-direction: column;
    animation: slideRight 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
@keyframes slideRight {
    from { transform: translateX(-100%); }
    to { transform: translateX(0); }
}
.gmap-drawer-header {
    padding: 14px 16px;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
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
    padding: 10px;
    border-radius: 12px;
    border: 1px solid #f1f5f9;
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    transition: all 0.15s;
    background: #ffffff;
}
.gmap-place-card:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
    transform: translateY(-1px);
}
.gmap-icon-btn {
    background: #f1f5f9;
    border: none;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #64748b;
}

/* ── 7. Custom Markers & Pulse ── */
.custom-pin-marker {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    border-radius: 50% 50% 50% 0;
    transform: rotate(-45deg);
    color: #fff;
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
    border: 2px solid #fff;
    cursor: pointer;
    transition: transform 0.2s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.custom-pin-marker svg {
    transform: rotate(45deg);
}
.custom-pin-marker:hover, .custom-pin-marker.selected {
    transform: rotate(-45deg) scale(1.25);
    box-shadow: 0 6px 18px rgba(0,0,0,0.4);
    z-index: 1000 !important;
}
.pin-activity { background: linear-gradient(135deg, #ea580c, #f97316); }
.pin-job { background: linear-gradient(135deg, #0284c7, #38bdf8); }
.pin-landmark { background: linear-gradient(135deg, #16a34a, #4ade80); }

/* ── 7. Real-time GPS Navigation Puck & Direction Arrow (Google Maps Style) ── */
.user-gps-puck-container {
    position: relative;
    width: 52px;
    height: 52px;
    display: flex;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    will-change: transform;
}

/* Directional Heading Cone / Light Beam */
.user-gps-heading-cone {
    position: absolute;
    width: 52px;
    height: 52px;
    top: 0;
    left: 0;
    transform-origin: center center;
    transition: transform 0.35s cubic-bezier(0.25, 1, 0.5, 1);
    pointer-events: none;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Center Blue Navigation Puck Dot */
.user-gps-dot-core {
    position: relative;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: #0284c7;
    border: 3.5px solid #ffffff;
    box-shadow: 0 2px 10px rgba(2, 132, 199, 0.5), 0 0 0 1px rgba(0, 0, 0, 0.12);
    z-index: 3;
    transition: all 0.3s ease;
}

/* Pulsing Radar Ring */
.user-gps-radar-ring {
    position: absolute;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(2, 132, 199, 0.22);
    animation: gps-radar-pulse 2.2s infinite cubic-bezier(0.215, 0.61, 0.355, 1);
    z-index: 2;
    pointer-events: none;
}

@keyframes gps-radar-pulse {
    0% { transform: scale(0.5); opacity: 0.9; }
    70% { transform: scale(1.6); opacity: 0; }
    100% { transform: scale(1.6); opacity: 0; }
}

/* Live Peer / Team Member Pins */
.live-peer-marker {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    transition: transform 0.45s cubic-bezier(0.25, 1, 0.5, 1);
    cursor: pointer;
}
.live-peer-avatar {
    width: 30px;
    height: 30px;
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
html[data-theme="dark"] .gmap-app-pill,
html[data-theme="dark"] .gmap-app-badge,
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
html[data-theme="dark"] .gmap-app-pill.recom {
    background: rgba(234, 88, 12, 0.18) !important;
    border-color: #ea580c !important;
    color: #fb923c !important;
}
html[data-theme="dark"] .gmap-sheet-handle {
    background: #3f3f46;
}

/* Hide default leaflet zoom controls */
.leaflet-control-zoom {
    display: none !important;
}
</style>

<script>
(function() {
    const authUserId = '{{ auth()->id() }}';
    let map = null;
    let markersCluster = null;
    let heatLayer = null;
    let routingControl = null;
    let userMarker = null;
    let radiusCircle = null;
    let userCoords = null; // [lat, lng]

    // Real-time GPS & Compass Tracking State
    let watchGpsId = null;
    let currentHeading = 0;
    let lastBroadcastTime = 0;
    let lastBroadcastCoords = null;
    let peerMarkers = {}; // { [userId]: L.Marker }
    let isTrackingLive = false;

    let allLocations = [];
    let currentFilterType = 'all';
    let currentRadiusKm = 0;
    let searchQuery = '';
    let activeLocation = null;
    let activeMapLayerType = 'streets';

    const defaultCenter = [16.4745, 102.8235]; // Campus center

    // Tile Layers
    const streetTile = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors',
        maxZoom: 19
    });
    const satelliteTile = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        attribution: '&copy; Esri, Earthstar Geographics',
        maxZoom: 19
    });
    const darkTile = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; CartoDB',
        maxZoom: 19
    });

    document.addEventListener('DOMContentLoaded', function() {
        initMap();
        initSearchInput();
        initBottomSheetSwipe();
        fetchLocations();
        startRealtimeLocationTracking();
        initReverbMapTracking();

        // Auto-select dark tile if user is currently in dark theme
        const currentTheme = document.documentElement.getAttribute('data-theme') || (localStorage.getItem('app-theme') || 'light');
        if (currentTheme === 'dark') {
            switchMapLayer('dark');
        }
    });

    function initMap() {
        map = L.map('unifiedMap', {
            center: defaultCenter,
            zoom: 14,
            zoomControl: false,
            layers: [streetTile]
        });

        markersCluster = L.markerClusterGroup({
            showCoverageOnHover: false,
            maxClusterRadius: 40,
            iconCreateFunction: function(cluster) {
                const count = cluster.getChildCount();
                return L.divIcon({
                    html: '<div style="background:#ea580c;color:#fff;width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:0.85rem;border:2.5px solid #fff;box-shadow:0 3px 8px rgba(0,0,0,0.3);">' + count + '</div>',
                    className: 'custom-cluster-icon',
                    iconSize: [34, 34]
                });
            }
        });
        map.addLayer(markersCluster);

        map.on('click', function(e) {
            if (e.originalEvent.target.id === 'unifiedMap' || e.originalEvent.target.classList.contains('leaflet-container')) {
                closeBottomSheet();
                closeLayerSheet();
            }
        });
    }

    // Search Input Binding
    function initSearchInput() {
        const input = document.getElementById('mapSearchInput');
        const clearBtn = document.getElementById('mapSearchClearBtn');

        input.addEventListener('input', function() {
            searchQuery = this.value.trim().toLowerCase();
            clearBtn.style.display = searchQuery ? 'flex' : 'none';
            renderMarkers();
            updateNearbyList();
        });
    }

    window.goBackOrHome = function() {
        if (window.history.length > 1 && document.referrer && document.referrer.includes(window.location.host)) {
            window.history.back();
        } else {
            window.location.href = '{{ route("activities.index") }}';
        }
    };

    window.clearMapSearch = function() {
        const input = document.getElementById('mapSearchInput');
        input.value = '';
        searchQuery = '';
        document.getElementById('mapSearchClearBtn').style.display = 'none';
        renderMarkers();
        updateNearbyList();
    };

    // Layer Switcher
    window.toggleLayerSheet = function() {
        const sheet = document.getElementById('gmapLayerSheet');
        sheet.style.display = sheet.style.display === 'none' ? 'block' : 'none';
    };

    window.closeLayerSheet = function() {
        const sheet = document.getElementById('gmapLayerSheet');
        if (sheet) sheet.style.display = 'none';
    };

    window.switchMapLayer = function(layerType) {
        activeMapLayerType = layerType;
        document.querySelectorAll('.gmap-layer-card').forEach(c => c.classList.remove('active'));
        const card = document.getElementById('layerCard-' + layerType);
        if (card) card.classList.add('active');

        map.removeLayer(streetTile);
        map.removeLayer(satelliteTile);
        map.removeLayer(darkTile);

        if (layerType === 'satellite') {
            map.addLayer(satelliteTile);
        } else if (layerType === 'dark') {
            map.addLayer(darkTile);
        } else {
            map.addLayer(streetTile);
        }

        closeLayerSheet();
    };

    // Toggle Density Heatmap Chip
    window.toggleHeatmapChip = function(btn) {
        const isActive = btn.classList.toggle('active');

        if (isActive) {
            const heatPoints = allLocations.map(loc => [loc.lat, loc.lng, loc.type === 'activity' ? 0.9 : 0.6]);
            if (!heatLayer) {
                heatLayer = L.heatLayer(heatPoints, { radius: 28, blur: 16, maxZoom: 17 });
            }
            map.addLayer(heatLayer);
            map.removeLayer(markersCluster);
        } else {
            if (heatLayer) map.removeLayer(heatLayer);
            map.addLayer(markersCluster);
        }
    };

    // Fetch All Locations from Backend
    function fetchLocations() {
        fetch('{{ route("api.map.locations") }}')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    allLocations = [
                        ...(data.activities || []),
                        ...(data.jobs || []),
                        ...(data.landmarks || [])
                    ];

                    document.getElementById('fabPlaceCountBadge').textContent = allLocations.length;
                    document.getElementById('drawerCountLabel').textContent = allLocations.length + ' แห่ง';

                    // Parse URL Query Parameters for Focus & Filters
                    const urlParams = new URLSearchParams(window.location.search);
                    const targetType = urlParams.get('type');
                    const targetId = urlParams.get('id');
                    const autoNav = urlParams.get('nav');

                    if (targetType && !targetId) {
                        currentFilterType = targetType;
                        document.querySelectorAll('.gmap-chip').forEach(b => {
                            b.classList.toggle('active', b.getAttribute('data-type') === targetType);
                        });
                    }

                    renderMarkers();
                    updateNearbyList();

                    // If a specific target ID is requested, center, zoom, and open bottom sheet
                    if (targetId) {
                        const target = allLocations.find(loc => {
                            const matchId = String(loc.id) === String(targetId);
                            if (targetType) return loc.type === targetType && matchId;
                            return matchId;
                        });

                        if (target) {
                            setTimeout(() => {
                                map.setView([target.lat, target.lng], 17, { animate: true });
                                showBottomSheet(target);
                                if (autoNav === '1') {
                                    setTimeout(() => startNavigationToActive(), 400);
                                }
                            }, 300);
                        }
                    }
                }
            })
            .catch(err => {
                console.error('Failed to load map locations:', err);
            });
    }

    // Render Filtered Markers
    function renderMarkers() {
        markersCluster.clearLayers();

        const filtered = getFilteredLocations();

        filtered.forEach(loc => {
            let pinClass = 'pin-activity';
            let iconSvg = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>';

            if (loc.type === 'job') {
                pinClass = 'pin-job';
                iconSvg = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>';
            } else if (loc.type === 'landmark') {
                pinClass = 'pin-landmark';
                iconSvg = '<svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>';
            }

            const customIcon = L.divIcon({
                html: `<div class="custom-pin-marker ${pinClass}">${iconSvg}</div>`,
                className: 'leaflet-pin-wrap',
                iconSize: [38, 38],
                iconAnchor: [19, 38]
            });

            const marker = L.marker([loc.lat, loc.lng], { icon: customIcon });
            marker.on('click', () => {
                showBottomSheet(loc);
                map.panTo([loc.lat, loc.lng]);
            });
            markersCluster.addLayer(marker);
        });

        // Update badge count
        document.getElementById('fabPlaceCountBadge').textContent = filtered.length;
    }

    function getFilteredLocations() {
        return allLocations.filter(item => {
            // Type Filter
            if (currentFilterType !== 'all' && item.type !== currentFilterType) {
                return false;
            }
            // Search Query Filter
            if (searchQuery) {
                const titleMatch = (item.title || '').toLowerCase().includes(searchQuery);
                const locMatch = (item.location_name || '').toLowerCase().includes(searchQuery);
                const subtitleMatch = (item.subtitle || '').toLowerCase().includes(searchQuery);
                if (!titleMatch && !locMatch && !subtitleMatch) return false;
            }
            // Radius Filter
            if (currentRadiusKm > 0 && userCoords) {
                const distKm = calculateDistance(userCoords[0], userCoords[1], item.lat, item.lng);
                if (distKm > currentRadiusKm) return false;
            }
            return true;
        });
    }

    // Filter by Type
    window.filterType = function(type, btn) {
        currentFilterType = type;
        document.querySelectorAll('.gmap-chip').forEach(b => {
            if (b.getAttribute('data-type')) b.classList.remove('active');
        });
        btn.classList.add('active');
        renderMarkers();
        updateNearbyList();
    };

    // Toggle Quick Radius (< 2km)
    window.toggleQuickRadius = function(radiusKm, btn) {
        const isActive = btn.classList.toggle('active');
        currentRadiusKm = isActive ? radiusKm : 0;

        if (currentRadiusKm > 0) {
            if (!userCoords) {
                getUserLocation(() => updateRadiusCircle(currentRadiusKm));
            } else {
                updateRadiusCircle(currentRadiusKm);
            }
        } else {
            if (radiusCircle) map.removeLayer(radiusCircle);
            renderMarkers();
            updateNearbyList();
        }
    };

    function updateRadiusCircle(radiusKm) {
        if (!userCoords) return;
        if (radiusCircle) map.removeLayer(radiusCircle);

        radiusCircle = L.circle(userCoords, {
            radius: radiusKm * 1000,
            color: '#0284c7',
            fillColor: '#0284c7',
            fillOpacity: 0.06,
            weight: 2,
            dashArray: '6, 6'
        }).addTo(map);

        map.fitBounds(radiusCircle.getBounds());
        renderMarkers();
        updateNearbyList();
    }

    // Build the SVG Puck with Direction Cone
    function getGpsPuckHtml(heading) {
        return `
            <div class="user-gps-puck-container">
                <div class="user-gps-heading-cone" id="userGpsHeadingCone" style="transform: rotate(${heading || 0}deg);">
                    <svg viewBox="0 0 100 100" width="100%" height="100%">
                        <defs>
                            <linearGradient id="headingGradient" x1="50%" y1="100%" x2="50%" y2="0%">
                                <stop offset="0%" stop-color="#0284c7" stop-opacity="0.6"/>
                                <stop offset="100%" stop-color="#38bdf8" stop-opacity="0"/>
                            </linearGradient>
                        </defs>
                        <path d="M 50 50 L 20 8 A 50 50 0 0 1 80 8 Z" fill="url(#headingGradient)" />
                        <polygon points="50,20 44,32 56,32" fill="#0284c7" opacity="0.95" />
                    </svg>
                </div>
                <div class="user-gps-radar-ring"></div>
                <div class="user-gps-dot-core"></div>
            </div>
        `;
    }

    // Set / Update User GPS Marker with Smooth Transitions
    function setUserGpsMarker() {
        if (!userCoords) return;

        if (!userMarker) {
            const gpsIcon = L.divIcon({
                html: getGpsPuckHtml(currentHeading),
                className: 'user-gps-puck-wrap',
                iconSize: [52, 52],
                iconAnchor: [26, 26]
            });
            userMarker = L.marker(userCoords, { icon: gpsIcon, zIndexOffset: 1000 }).addTo(map);
        } else {
            userMarker.setLatLng(userCoords);
            updateHeadingCone(currentHeading);
        }
    }

    // Smoothly rotate the heading cone
    function updateHeadingCone(heading) {
        currentHeading = heading;
        const cone = document.getElementById('userGpsHeadingCone');
        if (cone) {
            cone.style.transform = `rotate(${heading}deg)`;
        }
    }

    // Continuous Watch Position & Compass Engine
    function startRealtimeLocationTracking() {
        if (!navigator.geolocation) return;
        if (isTrackingLive) return;
        isTrackingLive = true;

        // 1. Compass / Device Orientation
        initCompassOrientation();

        // 2. High Accuracy Geolocation Watch
        watchGpsId = navigator.geolocation.watchPosition(
            function(pos) {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                const accuracy = pos.coords.accuracy || 0;
                const speed = pos.coords.speed || 0;
                const gpsHeading = pos.coords.heading;

                userCoords = [lat, lng];

                if (gpsHeading !== null && !isNaN(gpsHeading) && gpsHeading >= 0) {
                    currentHeading = Math.round(gpsHeading);
                }

                setUserGpsMarker();
                updateNearbyList();

                // Throttled Sender to Swoole / Reverb (Every 3.5s or > 5 meters movement)
                throttleBroadcastLocation(lat, lng, currentHeading, speed, accuracy);

                // If turn-by-turn navigation is active, update origin waypoint
                if (routingControl && routingControl.getWaypoints) {
                    const wps = routingControl.getWaypoints();
                    if (wps && wps.length > 1 && wps[1].latLng) {
                        routingControl.spliceWaypoints(0, 1, L.latLng(lat, lng));
                    }
                }
            },
            function(err) {
                console.warn('Real-time GPS watch notice:', err.message);
            },
            {
                enableHighAccuracy: true,
                maximumAge: 1500,
                timeout: 10000
            }
        );
    }

    // Compass & Device Orientation Handling (iOS + Android)
    function initCompassOrientation() {
        if (window.DeviceOrientationEvent) {
            // iOS 13+ permission request
            if (typeof DeviceOrientationEvent.requestPermission === 'function') {
                document.addEventListener('click', function requestIosCompass() {
                    DeviceOrientationEvent.requestPermission().then(response => {
                        if (response === 'granted') {
                            bindOrientationEvents();
                        }
                    }).catch(() => {});
                    document.removeEventListener('click', requestIosCompass);
                }, { once: true });
            } else {
                bindOrientationEvents();
            }
        }
    }

    function bindOrientationEvents() {
        const handleOrientation = function(e) {
            let heading = null;
            if (e.webkitCompassHeading !== undefined && e.webkitCompassHeading !== null) {
                // iOS Safari True Compass Heading
                heading = e.webkitCompassHeading;
            } else if (e.alpha !== null) {
                // Android standard orientation
                heading = e.absolute ? (360 - e.alpha) : (360 - e.alpha);
            }

            if (heading !== null && !isNaN(heading)) {
                updateHeadingCone(Math.round(heading));
            }
        };

        if ('ondeviceorientationabsolute' in window) {
            window.addEventListener('deviceorientationabsolute', handleOrientation, true);
        } else {
            window.addEventListener('deviceorientation', handleOrientation, true);
        }
    }

    // Throttle Sender (Mobile / GPS Tracker to Server)
    function throttleBroadcastLocation(lat, lng, heading, speed, accuracy) {
        if (!authUserId) return; // Only broadcast live location for authenticated users

        const now = Date.now();
        const minTimeInterval = 3500; // 3.5 seconds
        const minDistanceMeters = 5;  // 5 meters

        let shouldBroadcast = false;

        if (!lastBroadcastCoords || (now - lastBroadcastTime) > 10000) {
            shouldBroadcast = true;
        } else {
            const distKm = calculateDistance(lastBroadcastCoords[0], lastBroadcastCoords[1], lat, lng);
            const distMeters = distKm * 1000;
            if (distMeters >= minDistanceMeters && (now - lastBroadcastTime) >= minTimeInterval) {
                shouldBroadcast = true;
            }
        }

        if (!shouldBroadcast) return;

        lastBroadcastTime = now;
        lastBroadcastCoords = [lat, lng];

        // Send via AJAX to Server (which broadcasts over Reverb / Swoole)
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        if (!csrf) return;

        fetch('{{ route("api.map.update_location") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                latitude: lat,
                longitude: lng,
                heading: heading || 0,
                speed: speed || 0,
                accuracy: accuracy || 0
            })
        }).catch(() => {});
    }

    // Reverb WebSocket Map Tracking (Peer Markers)
    function initReverbMapTracking() {
        if (!window.Echo || !authUserId) return; // Only connect presence channel for authenticated users

        try {
            window.Echo.join('map.tracking')
                .here((users) => {
                    // Current online peers on the map
                })
                .joining((user) => {
                    // Peer joined
                })
                .leaving((user) => {
                    removePeerLocation(user.id);
                })
                .listen('.UserLocationUpdated', (e) => {
                    updatePeerLocation(e);
                });
        } catch (err) {
            console.warn('Reverb map tracking initialization skipped:', err);
        }
    }

    function updatePeerLocation(data) {
        // Don't draw self as a peer
        if (authUserId && String(data.user_id) === String(authUserId)) {
            return;
        }

        const id = data.user_id;
        const latLng = [data.latitude, data.longitude];

        if (peerMarkers[id]) {
            peerMarkers[id].setLatLng(latLng);
        } else {
            const avatarHtml = data.avatar 
                ? `<img src="${data.avatar}" alt="${data.name}">` 
                : `${(data.name || 'U').charAt(0).toUpperCase()}`;

            const icon = L.divIcon({
                html: `
                    <div class="live-peer-marker">
                        <div class="live-peer-avatar">${avatarHtml}</div>
                        <div class="live-peer-badge">${data.name || 'สมาชิก'}</div>
                    </div>
                `,
                className: 'live-peer-wrap',
                iconSize: [60, 48],
                iconAnchor: [30, 24]
            });

            const marker = L.marker(latLng, { icon: icon, zIndexOffset: 800 }).addTo(map);
            peerMarkers[id] = marker;
        }
    }

    function removePeerLocation(userId) {
        if (peerMarkers[userId]) {
            map.removeLayer(peerMarkers[userId]);
            delete peerMarkers[userId];
        }
    }

    window.locateUserAndCenter = function() {
        if (userCoords) {
            map.setView(userCoords, 16, { animate: true });
        } else {
            startRealtimeLocationTracking();
        }
    };

    // Device Detection Helper
    function getDeviceInfo() {
        const ua = navigator.userAgent || navigator.vendor || window.opera || '';
        const isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        const isAndroid = /Android/i.test(ua);
        const isMac = !isIOS && /Macintosh|MacIntel|MacPPC|Mac68K/i.test(ua);
        return {
            isIOS: isIOS,
            isAndroid: isAndroid,
            isApple: isIOS || isMac,
            isMobile: isIOS || isAndroid
        };
    }

    // Format Duration into Thai Readable string (Minutes, Hours, Days)
    function formatDurationThai(totalMinutes) {
        if (!totalMinutes || totalMinutes <= 0) return '< 1 นาที';
        const mins = Math.round(totalMinutes);
        if (mins < 1) return '< 1 นาที';
        if (mins < 60) return `${mins} นาที`;

        const hours = Math.floor(mins / 60);
        const remainMins = mins % 60;

        if (hours < 24) {
            if (remainMins === 0) {
                return `${hours} ชม.`;
            }
            return `${hours} ชม. ${remainMins} นาที`;
        }

        const days = Math.floor(hours / 24);
        const remainHours = hours % 24;
        if (remainHours === 0) {
            return `${days} วัน`;
        }
        return `${days} วัน ${remainHours} ชม.`;
    }

    // BottomSheet Handlers
    function showBottomSheet(loc) {
        activeLocation = loc;
        const sheet = document.getElementById('mapBottomSheet');

        // Thumbnail
        const img = document.getElementById('bs-img');
        const fallback = document.getElementById('bs-icon-fallback');
        if (loc.image) {
            img.src = loc.image;
            img.style.display = 'block';
            fallback.style.display = 'none';
        } else {
            img.style.display = 'none';
            fallback.style.display = 'flex';
        }

        // Title & Badges
        document.getElementById('bs-title').textContent = loc.title;
        document.getElementById('bs-subtitle').textContent = (loc.subtitle ? loc.subtitle + ' • ' : '') + (loc.location_name || 'พิกัดในมหาวิทยาลัย');

        const badge = document.getElementById('bs-badge');
        badge.textContent = loc.badge;
        let badgeColorClass = 'badge-orange';
        if (loc.type === 'job') badgeColorClass = 'badge-blue';
        if (loc.type === 'landmark') badgeColorClass = 'badge-green';
        badge.className = 'gmap-badge-tag ' + badgeColorClass;

        // Distance & Smart ETAs (Minutes / Hours formatted)
        let distText = '-';
        let walkText = '-';
        let driveText = '-';

        if (userCoords) {
            const distKm = calculateDistance(userCoords[0], userCoords[1], loc.lat, loc.lng);
            if (distKm < 1) {
                distText = Math.round(distKm * 1000) + ' ม.';
            } else {
                distText = distKm.toFixed(1) + ' กม.';
            }

            if (distKm < 0.06) {
                walkText = '< 1 นาที';
            } else {
                const walkMins = Math.max(1, Math.round((distKm / 4.8) * 60));
                walkText = '~' + formatDurationThai(walkMins);
            }

            const driveMins = Math.max(1, Math.round((distKm / 35) * 60));
            driveText = '~' + formatDurationThai(driveMins);
        }

        document.getElementById('bs-dist-val').textContent = distText;
        document.getElementById('bs-walk-eta').textContent = walkText;
        document.getElementById('bs-drive-eta').textContent = driveText;
        document.getElementById('bs-meta-info').textContent = loc.meta_info || loc.location_name || 'สถานที่ในแผนที่';

        // Detail Button
        const detailBtn = document.getElementById('bs-detail-btn');
        if (loc.detail_url) {
            detailBtn.href = loc.detail_url;
            detailBtn.style.display = 'flex';
        } else {
            detailBtn.style.display = 'none';
        }

        // Configure Native App Detection and Recommend Button
        const dev = getDeviceInfo();
        const nativeIcon = document.getElementById('bs-native-icon');
        const nativeText = document.getElementById('bs-native-text');
        const tagApple = document.getElementById('tag-recom-apple');
        const tagGoogle = document.getElementById('tag-recom-google');
        const pillApple = document.getElementById('bs-applemaps-btn');
        const pillGoogle = document.getElementById('bs-gmaps-btn');

        if (tagApple) tagApple.style.display = 'none';
        if (tagGoogle) tagGoogle.style.display = 'none';
        if (pillApple) pillApple.classList.remove('recom');
        if (pillGoogle) pillGoogle.classList.remove('recom');

        if (dev.isIOS) {
            nativeIcon.innerHTML = `<svg viewBox="0 0 170 170" width="16" height="16" fill="currentColor"><path d="M150.37 130.25c-2.45 5.66-5.35 10.87-8.71 15.66-4.58 6.53-8.33 11.05-11.22 13.56-4.48 4.12-9.28 6.23-14.42 6.35-3.69 0-8.14-1.05-13.32-3.18-5.19-2.12-9.97-3.17-14.34-3.17-4.58 0-9.49 1.05-14.75 3.17-5.26 2.13-9.5 3.24-12.74 3.35-4.35.13-9.16-1.9-14.42-6.08-3.69-3.08-7.7-7.91-12.03-14.5-5.64-8.59-10.15-18.49-13.51-29.7-3.37-11.2-5.06-22.02-5.06-32.46 0-14.75 3.8-27.18 11.41-37.3 7.6-10.13 17.2-15.26 28.79-15.41 4.71 0 9.87 1.25 15.48 3.75 5.62 2.5 9.4 3.8 11.35 3.9 1.52 0 5.48-1.39 11.89-4.17 6.41-2.77 12.06-4.04 16.94-3.8 12.52.62 22.56 5.16 30.12 13.62-10.88 6.59-16.2 15.71-15.96 27.36.24 9.17 3.86 16.89 10.87 23.16 7 6.27 15.34 9.82 25.01 10.66-2.07 6.28-4.73 12.77-7.98 19.46zM119.22 31.84c0-7.23 2.65-13.9 7.94-20.02 5.3-6.12 11.78-9.98 19.46-11.58.22 1.09.33 2.18.33 3.27 0 7.02-2.74 13.67-8.23 19.95-5.49 6.28-12.18 10.02-20.07 11.22-.11-.98-.22-1.94-.22-2.84z"/></svg>`;
            nativeText.textContent = 'Apple Maps';
            if (tagApple) tagApple.style.display = 'inline-block';
            if (pillApple) pillApple.classList.add('recom');
        } else if (dev.isAndroid) {
            nativeIcon.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>`;
            nativeText.textContent = 'Google Maps';
            if (tagGoogle) tagGoogle.style.display = 'inline-block';
            if (pillGoogle) pillGoogle.classList.add('recom');
        } else {
            nativeIcon.innerHTML = `<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"></polygon><line x1="9" y1="3" x2="9" y2="18"></line><line x1="15" y1="6" x2="15" y2="21"></line></svg>`;
            nativeText.textContent = 'Google Maps';
            if (tagGoogle) tagGoogle.style.display = 'inline-block';
            if (pillGoogle) pillGoogle.classList.add('recom');
        }

        sheet.style.display = 'flex';
    }

    // Touch Swipe Gesture for BottomSheet
    function initBottomSheetSwipe() {
        const sheet = document.getElementById('mapBottomSheet');
        const handle = document.getElementById('gmapSheetHandleZone');
        let startY = 0;
        let currentY = 0;

        handle.addEventListener('touchstart', function(e) {
            startY = e.touches[0].clientY;
        }, { passive: true });

        handle.addEventListener('touchmove', function(e) {
            currentY = e.touches[0].clientY;
            const diff = currentY - startY;
            if (diff > 0) {
                sheet.style.transform = `translateY(${diff}px)`;
            }
        }, { passive: true });

        handle.addEventListener('touchend', function() {
            const diff = currentY - startY;
            sheet.style.transform = '';
            if (diff > 60) {
                closeBottomSheet();
            }
            startY = 0;
            currentY = 0;
        });
    }

    window.toggleBottomSheetExpand = function() {
        const sheet = document.getElementById('mapBottomSheet');
        sheet.classList.toggle('expanded');
    };

    window.closeBottomSheet = function() {
        document.getElementById('mapBottomSheet').style.display = 'none';
        activeLocation = null;
    };

    // Launch Specific App with Native Scheme
    window.launchNativeApp = function(app, event) {
        if (event) event.preventDefault();
        if (!activeLocation) return;

        const loc = activeLocation;
        const encTitle = encodeURIComponent(loc.title || loc.location_name || 'จุดหมาย');
        const dev = getDeviceInfo();

        if (app === 'apple') {
            const appleScheme = `maps://?q=${encTitle}&ll=${loc.lat},${loc.lng}&daddr=${loc.lat},${loc.lng}`;
            const appleWeb = `https://maps.apple.com/?daddr=${loc.lat},${loc.lng}&q=${encTitle}`;

            if (dev.isIOS || dev.isApple) {
                window.location.href = appleScheme;
                setTimeout(() => { window.open(appleWeb, '_blank'); }, 600);
            } else {
                window.open(appleWeb, '_blank');
            }
        } else if (app === 'google') {
            const gNavScheme = `google.navigation:q=${loc.lat},${loc.lng}`;
            const gGeoScheme = `geo:${loc.lat},${loc.lng}?q=${loc.lat},${loc.lng}(${encTitle})`;
            const gWeb = `https://www.google.com/maps/dir/?api=1&destination=${loc.lat},${loc.lng}`;

            if (dev.isAndroid) {
                window.location.href = gNavScheme;
                setTimeout(() => {
                    window.location.href = gGeoScheme;
                    setTimeout(() => { window.open(gWeb, '_blank'); }, 500);
                }, 400);
            } else if (dev.isIOS) {
                window.location.href = `comgooglemaps://?daddr=${loc.lat},${loc.lng}&directionsmode=driving`;
                setTimeout(() => { window.open(gWeb, '_blank'); }, 500);
            } else {
                window.open(gWeb, '_blank');
            }
        } else if (app === 'waze') {
            const wazeScheme = `waze://?ll=${loc.lat},${loc.lng}&navigate=yes`;
            const wazeWeb = `https://waze.com/ul?ll=${loc.lat},${loc.lng}&navigate=yes`;

            if (dev.isMobile) {
                window.location.href = wazeScheme;
                setTimeout(() => { window.open(wazeWeb, '_blank'); }, 500);
            } else {
                window.open(wazeWeb, '_blank');
            }
        }
    };

    window.openActiveNativeMap = function() {
        if (!activeLocation) return;
        const dev = getDeviceInfo();
        if (dev.isIOS) {
            launchNativeApp('apple');
        } else {
            launchNativeApp('google');
        }
    };

    // Share Location
    window.shareActiveLocation = function() {
        if (!activeLocation) return;
        const shareData = {
            title: activeLocation.title || 'สถานที่ UNI Activity',
            text: `ดูสถานที่ ${activeLocation.title} บนแผนที่กิจกรรม`,
            url: `${window.location.origin}/map?type=${activeLocation.type}&id=${activeLocation.id}`
        };

        if (navigator.share) {
            navigator.share(shareData).catch(() => {});
        } else {
            navigator.clipboard.writeText(shareData.url).then(() => {
                alert('คัดลอกลิงก์สถานที่เรียบร้อยแล้ว');
            });
        }
    };

    // ── Multi-Mode & Alternative Route Selection System ──
    let currentTravelMode = 'drive'; // 'drive', 'moto', 'walk', 'bike'
    let currentRouteAlternatives = []; // [{ index, name, tag, distKm, timeMins, timeText, via, isMain }]
    let selectedRouteIndex = 0;
    let alternativePolylines = [];

    // Open Route Selector Sheet for Active Location
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
        activeLocation = loc;
        closeBottomSheet();

        const sheet = document.getElementById('gmapRouteSelectorSheet');
        document.getElementById('gmapRouteDestTitle').textContent = loc.title;

        // Set active mode chip
        document.querySelectorAll('.gmap-mode-chip').forEach(btn => {
            btn.classList.toggle('active', btn.getAttribute('data-mode') === currentTravelMode);
        });

        sheet.style.display = 'flex';

        // Center map to bounds between user and target
        if (userCoords) {
            const bounds = L.latLngBounds([userCoords, [loc.lat, loc.lng]]);
            map.fitBounds(bounds, { padding: [80, 80] });
        }

        calculateAndRenderRouteOptions();
    };

    window.closeRouteSelector = function() {
        document.getElementById('gmapRouteSelectorSheet').style.display = 'none';
        clearAlternativePolylines();
    };

    window.selectTravelMode = function(mode, btn) {
        currentTravelMode = mode;
        document.querySelectorAll('.gmap-mode-chip').forEach(b => b.classList.remove('active'));
        if (btn) btn.classList.add('active');
        calculateAndRenderRouteOptions();
    };

    function clearAlternativePolylines() {
        alternativePolylines.forEach(layer => map.removeLayer(layer));
        alternativePolylines = [];
    }

    // Calculate alternative routes & render comparison cards
    function calculateAndRenderRouteOptions() {
        const target = activeLocation;
        if (!target) return;

        const cardsContainer = document.getElementById('gmapRouteCardsList');
        cardsContainer.innerHTML = '<div style="padding:1rem;text-align:center;color:#94a3b8;font-size:0.85rem;">กำลังคำนวณเส้นทางทางเลือก...</div>';

        if (!userCoords) {
            cardsContainer.innerHTML = '<div style="padding:1rem;text-align:center;color:#ef4444;font-size:0.85rem;">กรุณาเปิด GPS หรือแตะปุ่มระบุตำแหน่งเพื่อดูเส้นทาง</div>';
            return;
        }

        const baseDistKm = calculateDistance(userCoords[0], userCoords[1], target.lat, target.lng);

        // Speed coefficients per mode
        let speedKmH = 38;
        let modeLabel = 'ทางรถยนต์';
        if (currentTravelMode === 'moto') {
            speedKmH = 35;
            modeLabel = 'ทางมอเตอร์ไซค์';
        } else if (currentTravelMode === 'walk') {
            speedKmH = 4.8;
            modeLabel = 'ทางเดินเท้า';
        } else if (currentTravelMode === 'bike') {
            speedKmH = 16;
            modeLabel = 'ทางจักรยาน';
        }

        // Route 1 (Primary - Direct Recommended)
        const r1DistKm = (baseDistKm * 1.08).toFixed(1);
        let r1TimeMin = Math.round((parseFloat(r1DistKm) / speedKmH) * 60);
        if (currentTravelMode === 'walk' && parseFloat(r1DistKm) < 0.06) {
            r1TimeMin = 0;
        } else {
            r1TimeMin = Math.max(1, r1TimeMin);
        }

        // Route 2 (Alternative - Shortcut / Community Avoidance)
        const r2DistKm = (baseDistKm * 1.25).toFixed(1);
        const r2TimeMin = Math.max(2, Math.round((parseFloat(r2DistKm) / (speedKmH * 0.9)) * 60));

        currentRouteAlternatives = [
            {
                index: 0,
                name: 'เส้นทางหลัก (เร็วที่สุด)',
                tag: '⚡ แนะนำ',
                distKm: r1DistKm,
                timeMins: r1TimeMin,
                timeText: formatDurationThai(r1TimeMin),
                via: `${modeLabel} • การจราจรคล่องตัว`,
                isMain: true
            },
            {
                index: 1,
                name: 'เส้นทางรอง (ทางเลี่ยงชุมชน)',
                tag: '🛡️ ทางเลือก',
                distKm: r2DistKm,
                timeMins: r2TimeMin,
                timeText: formatDurationThai(r2TimeMin),
                via: `${modeLabel} • ผ่านถนนสายในมหาวิทยาลัย`,
                isMain: false
            }
        ];

        selectedRouteIndex = 0;
        renderRouteCards();
        previewRoutesOnMap();
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
            startBtnText.textContent = `เริ่มนำทาง (${sel.timeText})`;
        }
    }

    window.selectRouteCard = function(index) {
        selectedRouteIndex = index;
        renderRouteCards();
        previewRoutesOnMap();
    };

    function previewRoutesOnMap() {
        clearAlternativePolylines();
        if (!userCoords || !activeLocation) return;

        const target = activeLocation;
        const p1 = userCoords;
        const p2 = [target.lat, target.lng];

        const midLat = (p1[0] + p2[0]) / 2;
        const midLng = (p1[1] + p2[1]) / 2;
        const offset = 0.0035;

        const route1Points = [p1, [midLat + offset * 0.4, midLng - offset * 0.6], p2];
        const route2Points = [p1, [midLat - offset * 0.7, midLng + offset * 0.5], p2];

        const isR0Active = selectedRouteIndex === 0;

        // Line 1 (Main)
        const line1 = L.polyline(route1Points, {
            color: isR0Active ? '#10b981' : '#94a3b8',
            weight: isR0Active ? 7 : 5,
            opacity: isR0Active ? 0.95 : 0.6,
            dashArray: isR0Active ? null : '6, 6'
        }).addTo(map);

        line1.on('click', () => selectRouteCard(0));
        alternativePolylines.push(line1);

        // Line 2 (Alternative)
        const line2 = L.polyline(route2Points, {
            color: !isR0Active ? '#10b981' : '#94a3b8',
            weight: !isR0Active ? 7 : 5,
            opacity: !isR0Active ? 0.95 : 0.6,
            dashArray: !isR0Active ? null : '6, 6'
        }).addTo(map);

        line2.on('click', () => selectRouteCard(1));
        alternativePolylines.push(line2);
    }

    // Start Turn-by-Turn Navigation with Selected Route
    window.startNavigationWithSelectedRoute = function() {
        closeRouteSelector();
        startNavigationToActive();
    };

    // Turn-by-Turn Navigation with Top GPS Banner
    window.startNavigationToActive = function() {
        const target = activeLocation;
        if (!target) return;

        if (!userCoords) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(pos) {
                    userCoords = [pos.coords.latitude, pos.coords.longitude];
                    setUserGpsMarker();
                    startNavigationToActive();
                }, function() {
                    alert('กรุณาเปิด GPS หรืออนุญาตการเข้าถึงตำแหน่งในเบราว์เซอร์เพื่อเริ่มนำทาง');
                }, { enableHighAccuracy: true, timeout: 10000 });
            } else {
                alert('เบราว์เซอร์ไม่รองรับ GPS');
            }
            return;
        }

        closeBottomSheet();
        closeRouteSelector();
        clearAlternativePolylines();

        if (routingControl) {
            map.removeControl(routingControl);
            routingControl = null;
        }

        const navBanner = document.getElementById('gmapNavBanner');
        const nextDist = document.getElementById('gmapNavNextDist');
        const nextText = document.getElementById('gmapNavNextText');
        navBanner.style.display = 'flex';
        nextDist.textContent = 'กำลังคำนวณ...';
        nextText.textContent = 'มุ่งหน้าไปยัง ' + target.title;

        routingControl = L.Routing.control({
            waypoints: [
                L.latLng(userCoords[0], userCoords[1]),
                L.latLng(target.lat, target.lng)
            ],
            router: L.Routing.osrmv1({
                serviceUrl: 'https://router.project-osrm.org/route/v1'
            }),
            routeWhileDragging: false,
            showAlternatives: false,
            addWaypoints: false,
            createMarker: function() { return null; },
            lineOptions: {
                styles: [{ color: '#10b981', weight: 7, opacity: 0.95 }]
            }
        }).addTo(map);

        routingControl.on('routesfound', function(e) {
            const routes = e.routes;
            const summary = routes[0].summary;
            const totalDistKm = (summary.totalDistance / 1000).toFixed(1);
            const totalTimeMin = Math.round(summary.totalTime / 60);

            nextDist.textContent = `${totalDistKm} กม. (~${formatDurationThai(totalTimeMin)})`;
            if (routes[0].instructions && routes[0].instructions.length > 0) {
                nextText.textContent = routes[0].instructions[0].text;
            }
        });

        routingControl.on('routingerror', function() {
            const dist = calculateDistance(userCoords[0], userCoords[1], target.lat, target.lng);
            const estDriveMins = Math.max(1, Math.round((dist / 35) * 60));
            nextDist.textContent = `~${dist.toFixed(1)} กม. (~${formatDurationThai(estDriveMins)})`;
            nextText.textContent = `มุ่งหน้าไปยัง ${target.title}`;
        });
    };

    window.clearNavigationRoute = function() {
        if (routingControl) {
            map.removeControl(routingControl);
            routingControl = null;
        }
        clearAlternativePolylines();
        document.getElementById('gmapNavBanner').style.display = 'none';
    };

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

        // Sort by distance if user GPS available
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
            map.setView([target.lat, target.lng], 17, { animate: true });
            showBottomSheet(target);
        }
    };

    // Haversine Distance Formula (km)
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371; // Earth's radius in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    }
})();
</script>
@endsection
