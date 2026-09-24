/**
 * GeoSecurity — Client-Side Anti-Mock Location & Sensor Telemetry Collector
 * ตรวจจับการใช้งาน Fake GPS, Android Mock Location Provider, และ Chrome DevTools Sensor Emulation
 */
(function(window) {
    'use strict';

    var GeoSecurity = {
        /**
         * ตรวจสอบว่าอุปกรณ์มีสัญญาณการจำลองผ่าน DevTools หรือ Emulator หรือไม่
         */
        detectEnvironmentSpoof: function() {
            var ua = navigator.userAgent || '';
            var isMobileUA = /Android|iPhone|iPad|iPod|Mobile/i.test(ua);
            var maxTouch = navigator.maxTouchPoints || 0;
            var isWebDriver = Boolean(navigator.webdriver);

            // 1. ตรวจจับการใช้ User-Agent มือถือบน Desktop โดยไม่มี Touch Points
            var touchMismatch = false;
            if (isMobileUA && maxTouch === 0 && !/Macintosh/i.test(ua)) {
                touchMismatch = true;
            }

            // 2. ตรวจสอบ WebGL GPU Renderer (Desktop GPU บน Mobile UA)
            var renderer = 'unknown';
            try {
                var canvas = document.createElement('canvas');
                var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (gl) {
                    var debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                    if (debugInfo) {
                        renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || '';
                    }
                }
            } catch (e) {}

            var isDesktopGpuOnMobile = false;
            if (isMobileUA && /NVIDIA|GeForce|Radeon|Intel.*HD|Intel.*UHD|Intel.*Iris|Direct3D|SwiftShader|VMware|VirtualBox/i.test(renderer)) {
                isDesktopGpuOnMobile = true;
            }

            return {
                is_webdriver: isWebDriver,
                touch_mismatch: touchMismatch || isDesktopGpuOnMobile,
                renderer: renderer,
                max_touch_points: maxTouch
            };
        },

        /**
         * รับพิกัด GPS แบบ Multi-Sample เพื่อวิเคราะห์ Jitter และตรวจจับ Fake GPS
         * @param {Function} onSuccess (pos, telemetry)
         * @param {Function} onError (error)
         * @param {Object} options
         */
        getSecurePosition: function(onSuccess, onError, options) {
            options = options || {};
            var timeoutMs = options.timeout || 3500;
            var minSamples = options.minSamples || 2;
            var env = this.detectEnvironmentSpoof();

            if (!navigator.geolocation) {
                if (onError) onError(new Error('Geolocation not supported'));
                return;
            }

            var samples = [];
            var completed = false;
            var watchId = null;

            var finish = function(bestPos) {
                if (completed) return;
                completed = true;
                if (watchId !== null) {
                    navigator.geolocation.clearWatch(watchId);
                    watchId = null;
                }

                if (!bestPos && samples.length > 0) {
                    // เลือกตัวอย่างที่มี accuracy ต่ำสุด (แม่นยำสุด)
                    bestPos = samples.reduce(function(prev, curr) {
                        return (prev.coords.accuracy < curr.coords.accuracy) ? prev : curr;
                    });
                }

                if (!bestPos) {
                    if (onError) onError(new Error('Unable to retrieve location'));
                    return;
                }

                // คำนวณ Coordinate Jitter Variance ข้ามตัวอย่าง
                var jitter = 0.0;
                var isStationaryMock = false;
                if (samples.length >= 2) {
                    var avgLat = 0, avgLng = 0;
                    for (var i = 0; i < samples.length; i++) {
                        avgLat += samples[i].coords.latitude;
                        avgLng += samples[i].coords.longitude;
                    }
                    avgLat /= samples.length;
                    avgLng /= samples.length;

                    var varSum = 0;
                    for (var j = 0; j < samples.length; j++) {
                        var dLat = samples[j].coords.latitude - avgLat;
                        var dLng = samples[j].coords.longitude - avgLng;
                        varSum += (dLat * dLat + dLng * dLng);
                    }
                    jitter = Math.sqrt(varSum / samples.length);

                    // ในดาวเทียมจริง สัญญาณจะแกว่งตัวเล็กน้อยเสมอในทศนิยมหลักที่ 6-8
                    // หาก jitter เท่ากับ 0.00000000 เป๊ะและ accuracy เท่าเดิมตลอด -> สัญญาณ Mock
                    if (samples.length >= 3 && jitter === 0.0 && bestPos.coords.accuracy <= 5.0) {
                        isStationaryMock = true;
                    }
                }

                var telemetry = {
                    accuracy: bestPos.coords.accuracy,
                    altitude: bestPos.coords.altitude,
                    altitude_accuracy: bestPos.coords.altitudeAccuracy,
                    heading: bestPos.coords.heading,
                    speed: bestPos.coords.speed,
                    sample_count: samples.length,
                    jitter_variance: jitter,
                    is_stationary_mock: isStationaryMock,
                    is_webdriver: env.is_webdriver,
                    touch_mismatch: env.touch_mismatch,
                    renderer: env.renderer,
                    timestamp: Date.now()
                };

                onSuccess(bestPos, telemetry);
            };

            // กำหนด Timeout ป้องกันผู้ใช้รอนานเกินไป
            var safetyTimer = setTimeout(function() {
                finish(null);
            }, timeoutMs);

            try {
                watchId = navigator.geolocation.watchPosition(
                    function(pos) {
                        samples.push(pos);
                        if (samples.length >= minSamples) {
                            clearTimeout(safetyTimer);
                            finish(pos);
                        }
                    },
                    function(err) {
                        clearTimeout(safetyTimer);
                        // ถ้าเกิด error แต่มีตัวอย่างเก่าอยู่แล้วให้ใช้ตัวอย่างเก่า
                        if (samples.length > 0) {
                            finish(null);
                        } else if (onError) {
                            onError(err);
                        }
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: timeoutMs,
                        maximumAge: 0
                    }
                );
            } catch (e) {
                clearTimeout(safetyTimer);
                if (onError) onError(e);
            }
        }
    };

    window.GeoSecurity = GeoSecurity;
})(window);
