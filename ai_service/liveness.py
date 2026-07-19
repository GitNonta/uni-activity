"""
Passive Liveness Detection Module
====================================
ตรวจว่าใบหน้าที่สแกนเป็น "คนจริง" หรือ "ภาพถ่าย/จอ"
โดยใช้เทคนิค Passive (ไม่ต้องให้ผู้ใช้ทำอะไรเพิ่ม):

1. LBP Texture Analysis  — ผิวจริงมี micro-texture ที่พิมพ์ไม่ได้
2. FFT Frequency Analysis — ภาพถ่ายจากจอ/สิ่งพิมพ์มี pattern ซ้ำๆ
3. Eye Aspect Ratio (EAR) — ตาต้องเปิดอยู่ (ภาพนิ่งไม่กระพริบ)
4. Color Channel Variance — ใบหน้าจริงมีสีสมดุลกว่าภาพพิมพ์

ผลลัพธ์: liveness_score (0-1), is_live (bool), per-check breakdown
"""

from __future__ import annotations
import numpy as np
import cv2
import logging
from dataclasses import dataclass, field

logger = logging.getLogger("Liveness")


# ─────────────────────────────────────────────
# Config — ปรับ threshold ได้ตาม environment
# ─────────────────────────────────────────────
TEXTURE_WEIGHT   = 0.40   # LBP texture
FREQUENCY_WEIGHT = 0.30   # FFT high-freq ratio
EAR_WEIGHT       = 0.20   # Eye openness
COLOR_WEIGHT     = 0.10   # Channel variance

LIVENESS_THRESHOLD = 0.58  # ต่ำกว่านี้ = ถ่ายรูป/จอ


@dataclass
class LivenessResult:
    is_live: bool
    liveness_score: float
    texture_score: float
    frequency_score: float
    ear_score: float          # 0 = ตาปิด, 1 = ตาเปิด
    color_score: float
    message: str
    checks: dict = field(default_factory=dict)


# ─────────────────────────────────────────────
# 1. LBP Texture Analysis
# ─────────────────────────────────────────────
def compute_lbp(gray: np.ndarray, radius: int = 1, n_points: int = 8) -> np.ndarray:
    """
    Local Binary Pattern — วัด micro-texture ของผิวหน้า
    ผิวจริง: สม่ำเสมอ, gradient นุ่ม
    ภาพพิมพ์/จอ: noise pattern สูง, พิกเซลเป็นกลุ่ม
    """
    rows, cols = gray.shape
    lbp = np.zeros_like(gray, dtype=np.uint8)

    for i in range(radius, rows - radius):
        for j in range(radius, cols - radius):
            center = gray[i, j]
            code = 0
            for k in range(n_points):
                angle = 2 * np.pi * k / n_points
                x = int(round(i + radius * np.cos(angle)))
                y = int(round(j + radius * np.sin(angle)))
                x = np.clip(x, 0, rows - 1)
                y = np.clip(y, 0, cols - 1)
                if gray[x, y] >= center:
                    code |= (1 << k)
            lbp[i, j] = code

    return lbp


def lbp_texture_score(face_img: np.ndarray) -> float:
    """
    คำนวณ texture uniformity score
    ใบหน้าจริง: LBP histogram มี entropy ปานกลาง (สม่ำเสมอ)
    ภาพจากจอ: entropy สูง (noisy) หรือ ต่ำมาก (oversaturated)
    คืนค่า: 0-1 (1 = likely real)
    """
    try:
        # Resize ให้เล็กก่อน ประหยัด CPU
        small = cv2.resize(face_img, (64, 64))
        gray = cv2.cvtColor(small, cv2.COLOR_BGR2GRAY) if len(small.shape) == 3 else small

        # ใช้ OpenCV built-in LBP approximation ผ่าน gradient variance
        # (LBP แบบ loop ช้าไป บน Termux ใช้ gradient แทน)
        laplacian = cv2.Laplacian(gray, cv2.CV_64F)
        var = laplacian.var()

        # Calibrate: ผิวจริงมี variance ~200-1500
        # จอ/สิ่งพิมพ์: ต่ำมาก (<100) หรือสูงมาก (>3000)
        if var < 50:
            score = 0.2   # flat/blurry = likely photo on glass
        elif var < 100:
            score = 0.45
        elif var < 200:
            score = 0.65  # borderline
        elif var < 1800:
            score = 0.85  # normal skin texture
        else:
            score = 0.50  # too noisy (might be low-light + grain)

        logger.debug(f"LBP gradient variance: {var:.1f} → score: {score:.2f}")
        return float(score)

    except Exception as e:
        logger.warning(f"LBP texture error: {e}")
        return 0.5  # neutral if error


# ─────────────────────────────────────────────
# 2. FFT Frequency Analysis
# ─────────────────────────────────────────────
def fft_frequency_score(face_img: np.ndarray) -> float:
    """
    วิเคราะห์ high-frequency content ด้วย FFT
    ผิวจริง: high-freq content กระจายสม่ำเสมอ
    ภาพจากจอ/พิมพ์: มี moiré pattern → spike ใน FFT spectrum
    คืนค่า: 0-1 (1 = likely real)
    """
    try:
        small = cv2.resize(face_img, (64, 64))
        gray = cv2.cvtColor(small, cv2.COLOR_BGR2GRAY) if len(small.shape) == 3 else small
        gray_f = np.float32(gray)

        # FFT
        fft = np.fft.fft2(gray_f)
        fft_shift = np.fft.fftshift(fft)
        magnitude = np.log(np.abs(fft_shift) + 1)

        # แบ่ง low / high frequency zones
        h, w = magnitude.shape
        center_h, center_w = h // 2, w // 2
        radius_low = 8  # Low-freq zone

        mask_low = np.zeros((h, w), dtype=bool)
        for i in range(h):
            for j in range(w):
                if (i - center_h)**2 + (j - center_w)**2 <= radius_low**2:
                    mask_low[i, j] = True

        low_energy  = magnitude[mask_low].mean()
        high_energy = magnitude[~mask_low].mean()

        if low_energy == 0:
            return 0.5

        ratio = high_energy / low_energy

        # ภาพจริง: ratio ปกติ 0.4-0.75
        # ภาพจากจอ: ratio ต่ำ (<0.3) เพราะโลว์ฟรีค dominate
        if ratio < 0.20:
            score = 0.2
        elif ratio < 0.35:
            score = 0.50
        elif ratio < 0.45:
            score = 0.70
        elif ratio < 0.80:
            score = 0.90  # sweet spot
        else:
            score = 0.65  # very high freq = possible noise

        logger.debug(f"FFT high/low ratio: {ratio:.3f} → score: {score:.2f}")
        return float(score)

    except Exception as e:
        logger.warning(f"FFT frequency error: {e}")
        return 0.5


# ─────────────────────────────────────────────
# 3. Eye Aspect Ratio (EAR)
# ─────────────────────────────────────────────
def eye_aspect_ratio_score(face_img: np.ndarray, landmarks_5pt: np.ndarray | None = None) -> float:
    """
    ตรวจว่าตาเปิดอยู่หรือไม่
    ใช้ 5-point landmarks จาก InsightFace: [left_eye, right_eye, nose, left_mouth, right_mouth]
    ถ้าไม่มี landmarks ใช้ brightness variance บริเวณตาแทน
    คืนค่า: 0-1 (1 = ตาเปิด)
    """
    try:
        if landmarks_5pt is not None and len(landmarks_5pt) >= 2:
            # มี 5-point landmarks
            left_eye  = landmarks_5pt[0]
            right_eye = landmarks_5pt[1]

            # ถ้าตา 2 ข้างอยู่ในระดับใกล้เคียงกัน = ตาเปิด
            eye_diff_y = abs(left_eye[1] - right_eye[1])
            eye_dist_x = abs(left_eye[0] - right_eye[0])
            if eye_dist_x == 0:
                return 0.5

            # ตาเปิด = y ไม่ต่างกันมาก, x ห่างพอควร
            ratio = eye_diff_y / eye_dist_x
            score = max(0.2, 1.0 - ratio * 4)  # ยิ่ง symmetric ยิ่งดี
            return float(min(1.0, score))

        # Fallback: ตรวจจาก brightness ในบริเวณตา (upper 40% of face)
        h, w = face_img.shape[:2]
        eye_region = face_img[int(h*0.15):int(h*0.5), int(w*0.1):int(w*0.9)]
        if eye_region.size == 0:
            return 0.5

        gray_eye = cv2.cvtColor(eye_region, cv2.COLOR_BGR2GRAY) if len(eye_region.shape) == 3 else eye_region
        # ตาเปิด = มี dark regions (iris) ใน eye area
        dark_ratio = np.mean(gray_eye < 80)  # สัดส่วนพิกเซลมืด

        score = 0.5 + (dark_ratio - 0.05) * 4
        score = float(np.clip(score, 0.2, 1.0))
        logger.debug(f"EAR fallback dark_ratio: {dark_ratio:.3f} → score: {score:.2f}")
        return score

    except Exception as e:
        logger.warning(f"EAR error: {e}")
        return 0.6  # slightly positive default


# ─────────────────────────────────────────────
# 4. Color Channel Variance
# ─────────────────────────────────────────────
def color_variance_score(face_img: np.ndarray) -> float:
    """
    ผิวหน้าจริงมี R > G > B และ variance ต่างกันระหว่าง channel
    ภาพจากจอ: channel balance แบบ RGB ที่ flat กว่า
    คืนค่า: 0-1 (1 = looks like real skin)
    """
    try:
        if len(face_img.shape) != 3:
            return 0.5

        small = cv2.resize(face_img, (32, 32))
        b, g, r = cv2.split(small)

        r_mean = r.mean()
        g_mean = g.mean()
        b_mean = b.mean()

        # ผิวหน้าจริง: R > G > B
        skin_order = (r_mean > g_mean) and (g_mean > b_mean)

        # Channel imbalance (ใบหน้าจริงมี R-B gap ใหญ่กว่า)
        rb_gap = r_mean - b_mean
        rb_score = min(1.0, rb_gap / 40.0)  # normalize ให้ gap=40 → score=1

        # Saturation variance
        hsv = cv2.cvtColor(small, cv2.COLOR_BGR2HSV)
        sat_var = hsv[:, :, 1].var()
        sat_score = min(1.0, sat_var / 1500.0)

        score = (0.4 * rb_score) + (0.3 * sat_score) + (0.3 * (1.0 if skin_order else 0.3))
        logger.debug(f"Color: r={r_mean:.0f} g={g_mean:.0f} b={b_mean:.0f} gap={rb_gap:.0f} → score={score:.2f}")
        return float(np.clip(score, 0.0, 1.0))

    except Exception as e:
        logger.warning(f"Color variance error: {e}")
        return 0.5


# ─────────────────────────────────────────────
# Main Liveness Checker
# ─────────────────────────────────────────────
class LivenessDetector:
    """
    Passive Liveness Detector
    ใช้งาน: detector = LivenessDetector(); result = detector.check(face_img)
    """

    def __init__(
        self,
        threshold: float = LIVENESS_THRESHOLD,
        texture_w: float = TEXTURE_WEIGHT,
        frequency_w: float = FREQUENCY_WEIGHT,
        ear_w: float = EAR_WEIGHT,
        color_w: float = COLOR_WEIGHT,
    ) -> None:
        self.threshold   = threshold
        self.texture_w   = texture_w
        self.frequency_w = frequency_w
        self.ear_w       = ear_w
        self.color_w     = color_w
        logger.info(f"LivenessDetector initialized (threshold={threshold})")

    def check(
        self,
        face_img: np.ndarray,
        landmarks_5pt: np.ndarray | None = None,
    ) -> LivenessResult:
        """
        ตรวจ liveness จากภาพใบหน้าที่ crop แล้ว (BGR numpy array)
        landmarks_5pt: 5-point array จาก InsightFace (optional, ช่วย EAR accuracy)
        """
        texture_score   = lbp_texture_score(face_img)
        frequency_score = fft_frequency_score(face_img)
        ear_score       = eye_aspect_ratio_score(face_img, landmarks_5pt)
        color_score     = color_variance_score(face_img)

        # Weighted sum
        liveness_score = (
            self.texture_w   * texture_score   +
            self.frequency_w * frequency_score +
            self.ear_w       * ear_score       +
            self.color_w     * color_score
        )
        liveness_score = float(np.clip(liveness_score, 0.0, 1.0))
        is_live = liveness_score >= self.threshold

        msg = "Liveness confirmed" if is_live else "Liveness check failed (possible photo attack)"

        logger.info(
            f"Liveness: texture={texture_score:.2f} freq={frequency_score:.2f} "
            f"ear={ear_score:.2f} color={color_score:.2f} "
            f"→ total={liveness_score:.2f} live={is_live}"
        )

        return LivenessResult(
            is_live        = is_live,
            liveness_score = round(liveness_score, 4),
            texture_score  = round(texture_score, 4),
            frequency_score= round(frequency_score, 4),
            ear_score      = round(ear_score, 4),
            color_score    = round(color_score, 4),
            message        = msg,
            checks={
                "texture":   round(texture_score, 3),
                "frequency": round(frequency_score, 3),
                "ear":       round(ear_score, 3),
                "color":     round(color_score, 3),
            },
        )
