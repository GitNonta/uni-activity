<?php

declare(strict_types=1);

namespace App\Services;

/**
 * CheckInRiskScoringService
 * ประเมินคะแนนความเสี่ยง (Multi-Factor Risk Scoring) สำหรับการเช็คอิน
 * มอง Device Fingerprint และ IP เป็น Risk Signal (ไม่ใช่ Identity) เพื่อป้องกัน False Positive Lockouts
 */
class CheckInRiskScoringService
{
    public const THRESHOLD_LOW    = 15;
    public const THRESHOLD_MEDIUM = 70;

    /**
     * ประเมินความเสี่ยงจากหลายมิติ (Biometrics, Geolocation, Device Network, และ Context)
     *
     * @param array<string, mixed> $factors
     * @return array{
     *     risk_score: int,
     *     risk_level: 'low'|'medium'|'high',
     *     is_suspicious: bool,
     *     decision: 'approved'|'flagged_for_review'|'rejected',
     *     breakdown: array<string, int>,
     *     reasons: array<int, string>,
     *     recommendation: string
     * }
     */
    public function evaluate(array $factors): array
    {
        $breakdown = [
            'biometric_risk'   => 0,
            'geolocation_risk' => 0,
            'device_risk'      => 0,
            'context_risk'     => 0,
        ];
        $reasons = [];

        // ── 1. Biometric AI Factor (Max 40 pts) ──
        $requireFace = (bool) ($factors['require_face_scan'] ?? false);
        if ($requireFace) {
            $facePassed     = (bool) ($factors['face_match_passed'] ?? false);
            $livenessPassed = (bool) ($factors['liveness_passed'] ?? true);
            $score          = (float) ($factors['face_match_score'] ?? 0.0);

            if (!$facePassed) {
                $breakdown['biometric_risk'] = 40;
                $reasons[] = "ใบหน้าไม่ตรงกับข้อมูลในระบบ (คะแนนความคล้ายคลึง: " . round($score, 1) . "%)";
            } elseif (!$livenessPassed) {
                $breakdown['biometric_risk'] = 35;
                $reasons[] = "การตรวจสอบ Liveness ไม่ผ่าน (สงสัยการใช้ภาพถ่าย/หน้าจอ)";
            } elseif ($score < 75.0) {
                $breakdown['biometric_risk'] = 12;
                $reasons[] = "คะแนนความคล้ายคลึงใบหน้าอยู่ในระดับปานกลาง (" . round($score, 1) . "%)";
            } else {
                $breakdown['biometric_risk'] = 0;
            }
        }

        // ── 2. Geolocation / GPS Factor (Max 25 pts) ──
        $hasGeo = (bool) ($factors['has_geolocation'] ?? false);
        if ($hasGeo) {
            $distance = $factors['distance_meters'] ?? null;
            $radius   = (float) ($factors['radius_meters'] ?? 100.0);

            if ($distance === null) {
                $breakdown['geolocation_risk'] = 25;
                $reasons[] = "ไม่พบพิกัด GPS สำหรับกิจกรรมที่กำหนด Geofence";
            } else {
                $dist = (float) $distance;
                if ($dist <= ($radius * 0.8)) {
                    $breakdown['geolocation_risk'] = 0;
                } elseif ($dist <= $radius) {
                    $breakdown['geolocation_risk'] = 5;
                } elseif ($dist <= ($radius * 1.5)) {
                    $breakdown['geolocation_risk'] = 15;
                    $reasons[] = "พิกัด GPS อยู่ใกล้ขอบเขตพื้นที่ (" . round($dist) . " ม. / กำหนด " . round($radius) . " ม.)";
                } else {
                    $breakdown['geolocation_risk'] = 25;
                    $reasons[] = "พิกัด GPS อยู่นอกพื้นที่กิจกรรม (" . round($dist) . " ม. / กำหนด " . round($radius) . " ม.)";
                }
            }
        }

        // ── 3. Device & Network Factor (Max 25 pts) — Probabilistic Risk Signal ──
        $isSharedDevice = (bool) ($factors['is_shared_device'] ?? false);
        $otherAccounts  = (int) ($factors['other_accounts_count'] ?? 0);

        if ($isSharedDevice) {
            if ($otherAccounts <= 2) {
                // กรณีอุปกรณ์แชร์กัน 2-3 คน (เช่น เพื่อนแชร์แท็บเล็ต/ฮอตสปอต) -> Risk signal ปานกลาง
                $breakdown['device_risk'] = 20;
                $reasons[] = "ตรวจพบการเข้าสู่ระบบ/เช็คอินหลายบัญชีบนอุปกรณ์หรือ IP เดียวกัน ({$otherAccounts} บัญชี)";
            } else {
                // กรณีอุปกรณ์มีหลายบัญชีผิดปกติ (4+ บัญชี) -> Risk signal สูง
                $breakdown['device_risk'] = 25;
                $reasons[] = "ตรวจพบการใช้งานอุปกรณ์ร่วมกันจำนวนมากผิดปกติ ({$otherAccounts} บัญชี)";
            }
        }

        // ── 4. QR Replay & Context Factor (Max 10 pts) ──
        $isQrReplay   = (bool) ($factors['is_qr_replay'] ?? false);
        $isWalkin     = (bool) ($factors['is_walkin'] ?? false);
        $isRegistered = (bool) ($factors['is_registered'] ?? true);

        if ($isQrReplay) {
            $breakdown['context_risk'] += 10;
            $reasons[] = "ตรวจพบการใช้งาน QR Code ซ้ำ (Replay attempt)";
        }

        if ($isWalkin && !$isRegistered) {
            $breakdown['context_risk'] = min(10, $breakdown['context_risk'] + 5);
        }

        // คำนวณคะแนนรวม (0 - 100)
        $totalRisk = (int) min(100, max(0, array_sum($breakdown)));

        // จำแนก Tier
        if ($totalRisk < self::THRESHOLD_LOW) {
            $riskLevel    = 'low';
            $isSuspicious = false;
            $decision     = 'approved';
            $recommendation = 'ยืนยันตัวตนถูกต้อง ความเสี่ยงต่ำ';
        } elseif ($totalRisk < self::THRESHOLD_MEDIUM) {
            $riskLevel    = 'medium';
            $isSuspicious = true;
            $decision     = 'flagged_for_review';
            $recommendation = 'อนุญาตให้เช็คอินได้ พร้อมบันทึก flag ตรวจทานเนื่องจากพบสัญญาณความเสี่ยงปานกลาง';
        } else {
            $riskLevel    = 'high';
            $isSuspicious = true;
            $decision     = 'rejected';
            $recommendation = 'ปฏิเสธการเช็คอินเนื่องจากคะแนนความเสี่ยงสูงเกินเกณฑ์';
        }

        return [
            'risk_score'     => $totalRisk,
            'risk_level'     => $riskLevel,
            'is_suspicious'  => $isSuspicious,
            'decision'       => $decision,
            'breakdown'      => $breakdown,
            'reasons'        => $reasons,
            'recommendation' => $recommendation,
        ];
    }
}
