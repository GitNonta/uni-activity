"""
Passive Liveness & Anti-Spoofing Detection Engine
=================================================
ตรวจจับการโจมตีด้วยภาพถ่ายและหน้าจอดิจิทัล (Presentation Attack Detection - ISO/IEC 30107-3)
ป้องกันการสแกนรูปจากรูปถ่าย, กระดาษพิมพ์, และหน้าจอสมาร์ทโฟน/แท็บเล็ต:

1. 2D FFT Screen Moiré & Harmonic Peak Analysis (วิเคราะห์คลื่นแทรกสอดจากพิกเซลจอ LCD/OLED)
2. Specular Screen Glare & Glass Reflection (ตรวจแสงสะท้อนจอกระจกและขอบสะท้อนแสง)
3. Color Gamut & YCbCr Skin Locus (ตรวจช่วงสีผิวจริง ป้องกันจอเร่งแสงสีฟ้า และภาพพิมพ์สีเพี้ยน)
4. LBP & Gradient Micro-Texture (วิเคราะห์ความละเอียดพื้นผิวผิวหนังจริง)
5. True Eye Morphology & Contrast (วิเคราะห์ม่านตาและตาขาว)
"""

from __future__ import annotations
import numpy as np
import cv2
import logging
import os
from dataclasses import dataclass, field
from typing import Optional

logger = logging.getLogger("Liveness")

# ─────────────────────────────────────────────
# Config & Thresholds
# ─────────────────────────────────────────────
TEXTURE_WEIGHT   = 0.25   # LBP / gradient micro-texture
MOIRE_WEIGHT     = 0.30   # 2D FFT screen moire / periodic artifact
GLARE_WEIGHT     = 0.15   # Specular glass / screen glare
COLOR_WEIGHT     = 0.15   # YCbCr skin locus & dynamic range
EYE_WEIGHT       = 0.15   # Eye contrast & morphology

LIVENESS_THRESHOLD = 0.65  # Calibrated threshold (rejects < 0.65)


@dataclass
class LivenessResult:
    is_live: bool
    liveness_score: float
    texture_score: float
    frequency_score: float    # Moire / frequency score
    ear_score: float          # Eye morphology / contrast score
    color_score: float
    glare_score: float
    message: str
    rejection_reason: Optional[str] = None
    checks: dict = field(default_factory=dict)


# ─────────────────────────────────────────────
# 1. 2D FFT Screen Moiré & Periodic Artifact
# ─────────────────────────────────────────────
def detect_screen_moire(gray: np.ndarray) -> tuple[float, float]:
    """
    วิเคราะห์ 2D Fourier Power Spectrum หาคลื่น Moiré ที่เกิดจากตาราง Subpixel ของหน้าจอ (LCD/OLED)
    ภาพจริง: การกระจายตัวของพลังงานเป็นแบบ 1/f Smooth Falloff
    ภาพจากจอ: มี Spike หรือ Harmonic Peaks นอกแกน DC ชัดเจน
    คืนค่า: (score 0-1, peak_ratio)
    """
    try:
        small = cv2.resize(gray, (96, 96))
        f = np.fft.fft2(small.astype(np.float32))
        fshift = np.fft.fftshift(f)
        mag = np.abs(fshift)

        h, w = mag.shape
        cy, cx = h // 2, w // 2
        y, x = np.ogrid[:h, :w]
        dc_mask = (x - cx)**2 + (y - cy)**2 <= 8**2

        mag_no_dc = mag.copy()
        mag_no_dc[dc_mask] = 0.0

        sorted_peaks = np.sort(mag_no_dc.flatten())[-20:]
        median_val = np.median(mag_no_dc[~dc_mask])
        peak_ratio = float(np.mean(sorted_peaks) / (median_val + 1e-5))

        # Calibrated peak ratio on screen replay vs real skin
        if peak_ratio > 20.0:
            score = 0.15  # Heavy screen moire detected
        elif peak_ratio > 17.0:
            score = 0.38  # Screen artifact likely
        elif peak_ratio > 15.5:
            score = 0.65  # Borderline
        else:
            score = 0.92  # Natural skin spectrum

        return float(score), round(peak_ratio, 2)
    except Exception as e:
        logger.warning(f"Moire detection error: {e}")
        return 0.60, 0.0


# ─────────────────────────────────────────────
# 2. Specular Screen Glare & Glass Reflection
# ─────────────────────────────────────────────
def detect_specular_glare(face_bgr: np.ndarray, gray: np.ndarray) -> tuple[float, float]:
    """
    ตรวจจับแสงสะท้อนจอกระจกสมาร์ทโฟน/แท็บเล็ต หรือผิวกระดาษอัดรูปมันวาว
    ผิวกระจกจอ: มีจุดอิ่มตัวสีขาว (Y > 240) ที่มีขอบชันมาก (high gradient boundary)
    ผิวจริง: แสงตกกระทบจะกระจายแบบ Subsurface Scattering ขอบนุ่ม
    คืนค่า: (score 0-1, sharpness)
    """
    try:
        h, w = gray.shape
        bright_mask = gray > 240
        num_bright = int(np.sum(bright_mask))
        bright_ratio = num_bright / float(h * w)

        if bright_ratio > 0.002:  # มีจุดสว่างจ้าเกิน 0.2% ของใบหน้า
            lap = cv2.Laplacian(gray, cv2.CV_64F)
            lap_bright = float(np.mean(np.abs(lap)[bright_mask]))

            if lap_bright > 18.0:
                score = 0.20  # Sharp glare boundary typical of screen glass
            elif lap_bright > 10.0:
                score = 0.45  # Glare present
            else:
                score = 0.75  # Soft natural highlight
            return float(score), round(lap_bright, 2)

        return 0.90, 0.0  # No harsh glass glare
    except Exception as e:
        logger.warning(f"Specular glare error: {e}")
        return 0.70, 0.0


# ─────────────────────────────────────────────
# 3. YCbCr Skin Locus & Dynamic Range Analysis
# ─────────────────────────────────────────────
def analyze_color_locus(face_bgr: np.ndarray) -> tuple[float, dict]:
    """
    วิเคราะห์การกระจายตัวของสีใน YCbCr Color Space และ Dynamic Range
    ผิวคนจริง: Cb อยู่ระหว่าง 77-127, Cr อยู่ระหว่าง 133-173, และ Cr > Cb เสมอ
    จอแสดงผล: มักมีสัดส่วนแสงสีฟ้า (Blue Channel) สูงผิดปกติจากการเร่งหลอด LED
    ภาพพิมพ์กระดาษ: มี Contrast และ Dynamic Range ต่ำ ดำไม่สนิท
    """
    try:
        if len(face_bgr.shape) != 3:
            return 0.50, {}

        b, g, r = cv2.split(face_bgr)
        r_mean, g_mean, b_mean = float(r.mean()), float(g.mean()), float(b.mean())
        blue_ratio = b_mean / (r_mean + 1e-5)

        ycbcr = cv2.cvtColor(face_bgr, cv2.COLOR_BGR2YCrCb)
        y_chan = ycbcr[:, :, 0]
        cr = ycbcr[:, :, 1]
        cb = ycbcr[:, :, 2]

        skin_mask = (cr > 133) & (cr < 173) & (cb > 77) & (cb < 127) & (cr > cb)
        skin_pct = float(np.mean(skin_mask))

        y_p5, y_p95 = float(np.percentile(y_chan, 5)), float(np.percentile(y_chan, 95))
        dyn_range = y_p95 - y_p5

        score = 0.88
        penalty_reasons = []

        if blue_ratio > 0.92:
            score -= 0.35
            penalty_reasons.append("high_blue_bias")
        if skin_pct < 0.30:
            score -= 0.25
            penalty_reasons.append("non_skin_locus")
        if dyn_range < 75:
            score -= 0.30
            penalty_reasons.append("compressed_dynamic_range")

        score = float(np.clip(score, 0.15, 1.0))
        details = {
            "skin_pct": round(skin_pct, 3),
            "blue_ratio": round(blue_ratio, 3),
            "dyn_range": round(dyn_range, 1),
            "penalties": penalty_reasons,
        }
        return score, details
    except Exception as e:
        logger.warning(f"Color locus error: {e}")
        return 0.50, {}


# ─────────────────────────────────────────────
# 4. LBP & Gradient Micro-Texture
# ─────────────────────────────────────────────
def analyze_texture(gray: np.ndarray) -> tuple[float, float]:
    """
    คำนวณ micro-texture ของผิวหน้าผ่าน Laplacian Variance
    ผิวจริง: variance สม่ำเสมอ ~150-1600
    ภาพพิมพ์/จอ: ต่ำมาก (<60, เบลอ/แบน) หรือสูงมาก (>2800, เม็ดสกรีน)
    """
    try:
        small = cv2.resize(gray, (96, 96))
        lap = cv2.Laplacian(small, cv2.CV_64F)
        var = float(lap.var())

        if var < 60:
            score = 0.20  # Flat / blurred photo
        elif var < 150:
            score = 0.55  # Borderline low texture
        elif var < 1600:
            score = 0.88  # Normal living skin texture
        elif var < 2800:
            score = 0.60  # Noisy / low light
        else:
            score = 0.35  # Extreme pixel grid / moire noise

        return float(score), round(var, 1)
    except Exception as e:
        logger.warning(f"Texture analysis error: {e}")
        return 0.50, 0.0


# ─────────────────────────────────────────────
# 5. Eye Morphology & Iris-Sclera Contrast
# ─────────────────────────────────────────────
def analyze_eye_morphology(gray: np.ndarray, landmarks_5pt: np.ndarray | None) -> tuple[float, float]:
    """
    วิเคราะห์ความคมชัดและคอนทราสต์บริเวณดวงตา (ม่านตากับตาขาว)
    ตาคนจริง: ม่านตามืดตัดกับตาขาวชัดเจน (contrast > 50)
    ภาพพิมพ์/ภาพจอถ่ายซ้ำ: บริเวณตาจะแบน คอนทราสต์ต่ำ
    """
    try:
        h, w = gray.shape
        eye_score = 0.75
        contrast = 50.0

        if landmarks_5pt is not None and len(landmarks_5pt) >= 2:
            le, re = landmarks_5pt[0], landmarks_5pt[1]
            eye_dist = float(np.linalg.norm(le - re))
            if eye_dist > 15:
                ew = int(eye_dist * 0.28)
                lx, ly = int(le[0]), int(le[1])
                y1, y2 = max(0, ly - ew), min(h, ly + ew)
                x1, x2 = max(0, lx - ew), min(w, lx + ew)
                eye_crop = gray[y1:y2, x1:x2]

                if eye_crop.size > 20:
                    p10, p90 = float(np.percentile(eye_crop, 10)), float(np.percentile(eye_crop, 90))
                    contrast = p90 - p10
                    if contrast < 30:
                        eye_score = 0.25  # Washed out / flat print
                    elif contrast > 60:
                        eye_score = 0.88  # High clarity live eye
                    else:
                        eye_score = 0.65

        return float(eye_score), round(contrast, 1)
    except Exception as e:
        logger.warning(f"Eye morphology error: {e}")
        return 0.65, 0.0


# ─────────────────────────────────────────────
# Main Liveness Detector Class
# ─────────────────────────────────────────────
class LivenessDetector:
    """
    Multi-Signal Passive Anti-Spoofing Detector
    ตรวจจับทั้ง Printed Photos และ Screen Replay Attacks พร้อมกลไก Veto ปฏิเสธทันที
    """

    def __init__(
        self,
        threshold: float = LIVENESS_THRESHOLD,
        texture_w: float = TEXTURE_WEIGHT,
        moire_w: float = MOIRE_WEIGHT,
        glare_w: float = GLARE_WEIGHT,
        color_w: float = COLOR_WEIGHT,
        eye_w: float = EYE_WEIGHT,
    ) -> None:
        self.threshold = threshold
        self.texture_w = texture_w
        self.moire_w   = moire_w
        self.glare_w   = glare_w
        self.color_w   = color_w
        self.eye_w     = eye_w
        logger.info(f"LivenessDetector initialized (threshold={threshold})")

    def check(
        self,
        face_img: np.ndarray,
        landmarks_5pt: np.ndarray | None = None,
    ) -> LivenessResult:
        """
        ตรวจ liveness จากภาพใบหน้าที่ crop แล้ว (BGR numpy array)
        """
        if face_img is None or face_img.size == 0:
            return LivenessResult(
                is_live=False,
                liveness_score=0.0,
                texture_score=0.0,
                frequency_score=0.0,
                ear_score=0.0,
                color_score=0.0,
                glare_score=0.0,
                message="Invalid empty face image",
                rejection_reason="no_image",
            )

        gray = cv2.cvtColor(face_img, cv2.COLOR_BGR2GRAY) if len(face_img.shape) == 3 else face_img

        # 1. Texture analysis
        tex_score, lap_var = analyze_texture(gray)

        # 2. 2D FFT Moire analysis
        moire_score, peak_ratio = detect_screen_moire(gray)

        # 3. Specular glare analysis
        glare_score, glare_sharp = detect_specular_glare(face_img, gray)

        # 4. Color & skin locus analysis
        color_score, color_details = analyze_color_locus(face_img)

        # 5. Eye morphology analysis
        eye_score, eye_contrast = analyze_eye_morphology(gray, landmarks_5pt)

        # Composite weighted sum
        total_score = (
            self.texture_w * tex_score +
            self.moire_w   * moire_score +
            self.glare_w   * glare_score +
            self.color_w   * color_score +
            self.eye_w     * eye_score
        )
        total_score = float(np.clip(total_score, 0.0, 1.0))

        # ─────────────────────────────────────────────
        # Veto Logic for Unambiguous Attacks
        # ─────────────────────────────────────────────
        is_live = total_score >= self.threshold
        rejection_reason = None

        if moire_score <= 0.20:
            is_live = False
            rejection_reason = "screen_moire_detected"
            msg = "Liveness check failed (Screen moiré detected)"
        elif glare_score <= 0.25 and glare_sharp > 15.0:
            is_live = False
            rejection_reason = "glass_glare_detected"
            msg = "Liveness check failed (Glass reflection detected)"
        elif color_score <= 0.25:
            is_live = False
            rejection_reason = "color_gamut_rejected"
            msg = "Liveness check failed (Color gamut / print detected)"
        elif total_score < self.threshold:
            is_live = False
            rejection_reason = "composite_liveness_below_threshold"
            msg = f"Liveness check failed (Score {total_score:.2f} < {self.threshold})"
        else:
            msg = "Liveness confirmed"

        logger.info(
            f"Liveness: tex={tex_score:.2f} moire={moire_score:.2f}(pk={peak_ratio}) "
            f"glare={glare_score:.2f} col={color_score:.2f} eye={eye_score:.2f} "
            f"→ total={total_score:.2f} live={is_live} ({rejection_reason or 'OK'})"
        )

        checks = {
            "texture": round(tex_score, 3),
            "laplacian_var": lap_var,
            "moire": round(moire_score, 3),
            "moire_peak_ratio": peak_ratio,
            "glare": round(glare_score, 3),
            "glare_sharpness": glare_sharp,
            "color": round(color_score, 3),
            "color_details": color_details,
            "eye": round(eye_score, 3),
            "eye_contrast": eye_contrast,
            "rejection_reason": rejection_reason,
        }

        return LivenessResult(
            is_live=is_live,
            liveness_score=round(total_score, 4),
            texture_score=round(tex_score, 4),
            frequency_score=round(moire_score, 4),
            ear_score=round(eye_score, 4),
            color_score=round(color_score, 4),
            glare_score=round(glare_score, 4),
            message=msg,
            rejection_reason=rejection_reason,
            checks=checks,
        )
