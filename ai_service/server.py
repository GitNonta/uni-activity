"""
Uni-Activity AI Server v2.0
============================
Multi-Pipeline Face Verification:
  YOLOv8-face  → Fast face pre-detection
  SCRFD        → InsightFace precise detection + alignment
  ArcFace 512D → Face embedding + cosine similarity
  Liveness     → Passive liveness detection (texture/FFT/EAR/color)

Endpoints:
  POST /extract  — สร้าง embedding จากรูปโปรไฟล์
  POST /verify   — ยืนยันใบหน้า + liveness check
  POST /liveness — ตรวจ liveness อย่างเดียว
  GET  /health   — ตรวจสอบสถานะ server
"""

import io
import os
import json
import time
import base64
import logging
import socket
from contextlib import asynccontextmanager
from typing import Optional

import numpy as np
import cv2

# ── Logging Setup ────────────────────────────────────────────────────────────
class UDPHandler(logging.Handler):
    def __init__(self, host: str, port: int) -> None:
        super().__init__()
        self.sock = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        self.host, self.port = host, port

    def emit(self, record: logging.LogRecord) -> None:
        try:
            self.sock.sendto(self.format(record).encode(), (self.host, self.port))
        except Exception:
            pass

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s"
)
logger = logging.getLogger("AIServer")

try:
    udp = UDPHandler("192.168.1.222", 9997)
    udp.setFormatter(logging.Formatter("%(asctime)s [%(levelname)s] %(message)s"))
    logger.addHandler(udp)
except Exception:
    pass

# ── CPU Tuning (Termux / Android) ────────────────────────────────────────────
os.environ.setdefault("OMP_NUM_THREADS", "4")
os.environ.setdefault("OPENBLAS_NUM_THREADS", "4")
os.environ.setdefault("MKL_NUM_THREADS", "4")

# ── FastAPI ───────────────────────────────────────────────────────────────────
from fastapi import FastAPI, File, UploadFile, Form, HTTPException, Query
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

# ── InsightFace ───────────────────────────────────────────────────────────────
from insightface.app import FaceAnalysis
import onnxruntime as ort

# ── Local modules ─────────────────────────────────────────────────────────────
from liveness import LivenessDetector, LivenessResult

# ─────────────────────────────────────────────────────────────────────────────
# Global models (loaded once at startup)
# ─────────────────────────────────────────────────────────────────────────────
face_app: Optional[FaceAnalysis] = None
liveness_detector: Optional[LivenessDetector] = None
yolo_model = None  # ultralytics YOLO (optional, lazy-loaded)

LIVENESS_THRESHOLD = float(os.environ.get("LIVENESS_THRESHOLD", "0.58"))
FACE_MATCH_THRESHOLD = float(os.environ.get("FACE_MATCH_THRESHOLD", "0.42"))
USE_YOLO = os.environ.get("USE_YOLO", "1") == "1"
USE_LIVENESS = os.environ.get("USE_LIVENESS", "1") == "1"
YOLO_MODEL_PATH = os.environ.get("YOLO_MODEL_PATH", "yolov8n-face.pt")


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Load all models at startup, release at shutdown"""
    global face_app, liveness_detector, yolo_model

    logger.info("=" * 60)
    logger.info("Starting Uni-Activity AI Server v2.0")
    logger.info("=" * 60)

    # ── 1. InsightFace (SCRFD + ArcFace) ─────────────────────────────
    logger.info("Loading InsightFace buffalo_l (SCRFD + ArcFace)...")
    available = ort.get_available_providers()
    logger.info(f"ONNX providers available: {available}")

    gpu_prov = [p for p in
        ["CUDAExecutionProvider", "DmlExecutionProvider",
         "TensorrtExecutionProvider", "CoreMLExecutionProvider"]
        if p in available]

    if gpu_prov:
        logger.info(f"GPU detected: {gpu_prov}")
        providers = gpu_prov + ["CPUExecutionProvider"]
        ctx_id = 0
    else:
        logger.info("No GPU — using CPU")
        providers = ["CPUExecutionProvider"]
        ctx_id = -1

    face_app = FaceAnalysis(
        name="buffalo_l",
        allowed_modules=["detection", "recognition"],
        providers=providers,
    )
    face_app.prepare(ctx_id=ctx_id, det_size=(640, 640), det_thresh=0.5)
    logger.info("InsightFace loaded ✓")

    # ── 2. Liveness Detector ──────────────────────────────────────────
    if USE_LIVENESS:
        liveness_detector = LivenessDetector(threshold=LIVENESS_THRESHOLD)
        logger.info(f"Liveness detector loaded ✓ (threshold={LIVENESS_THRESHOLD})")
    else:
        logger.info("Liveness detection DISABLED (USE_LIVENESS=0)")

    # ── 3. YOLOv8-face (optional, lazy) ──────────────────────────────
    if USE_YOLO:
        try:
            from ultralytics import YOLO
            if os.path.exists(YOLO_MODEL_PATH):
                yolo_model = YOLO(YOLO_MODEL_PATH)
                logger.info(f"YOLOv8-face loaded ✓ ({YOLO_MODEL_PATH})")
            else:
                logger.warning(
                    f"YOLOv8 model not found at '{YOLO_MODEL_PATH}'. "
                    "Skipping YOLOv8 — using SCRFD only. "
                    "Download: wget https://github.com/akanametov/yolov8-face/releases/download/v0.0.0/yolov8n-face.pt"
                )
        except ImportError:
            logger.warning("ultralytics not installed — YOLOv8 disabled. Run: pip install ultralytics")
    else:
        logger.info("YOLOv8 DISABLED (USE_YOLO=0)")

    logger.info("All models ready. Server is UP.")
    yield

    # Shutdown
    logger.info("Shutting down AI Server...")


app = FastAPI(
    title="Uni-Activity AI Server",
    version="2.0.0",
    description="Face Verification: YOLOv8 + SCRFD + ArcFace + Passive Liveness",
    lifespan=lifespan,
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
    allow_credentials=True,
)


# ─────────────────────────────────────────────────────────────────────────────
# Utility functions
# ─────────────────────────────────────────────────────────────────────────────

def decode_image(file_bytes: bytes) -> np.ndarray:
    """Decode JPEG/PNG bytes → BGR numpy array"""
    arr = np.frombuffer(file_bytes, dtype=np.uint8)
    img = cv2.imdecode(arr, cv2.IMREAD_COLOR)
    if img is None:
        raise ValueError("Invalid or corrupt image file")
    return img


def yolo_detect_face(img: np.ndarray) -> Optional[np.ndarray]:
    """
    ใช้ YOLOv8-face ตรวจจับใบหน้าก่อน แล้ว crop เฉพาะ face ROI
    Returns: cropped face (BGR) หรือ None ถ้าไม่เจอใบหน้า
    """
    if yolo_model is None:
        return img  # ไม่มี YOLOv8 → ใช้ภาพเดิม

    try:
        results = yolo_model(img, verbose=False, conf=0.5)
        if not results or len(results[0].boxes) == 0:
            return None

        # เลือก box ที่ confidence สูงสุด
        boxes = results[0].boxes
        best_idx = boxes.conf.argmax().item()
        x1, y1, x2, y2 = [int(v) for v in boxes.xyxy[best_idx].tolist()]

        # เพิ่ม margin 10%
        h, w = img.shape[:2]
        pad_x = int((x2 - x1) * 0.10)
        pad_y = int((y2 - y1) * 0.10)
        x1 = max(0, x1 - pad_x);  y1 = max(0, y1 - pad_y)
        x2 = min(w, x2 + pad_x);  y2 = min(h, y2 + pad_y)

        return img[y1:y2, x1:x2]

    except Exception as e:
        logger.warning(f"YOLOv8 detection error: {e}")
        return img  # fallback to full image


def insightface_detect(img: np.ndarray):
    """
    ใช้ InsightFace (SCRFD + ArcFace) ตรวจจับและสร้าง embedding
    Returns: (face_object, normed_embedding) หรือ raise Exception
    """
    faces = face_app.get(img)
    if len(faces) == 0:
        return None, None

    # เลือกใบหน้าที่ใหญ่สุด (det_score สูงสุด)
    face = max(faces, key=lambda f: f.det_score)
    embedding = face.normed_embedding  # shape (512,) normalized
    return face, embedding


def crop_aligned_face(img: np.ndarray, face) -> np.ndarray:
    """Crop aligned face จาก bounding box ของ InsightFace"""
    try:
        box = face.bbox.astype(int)
        x1, y1, x2, y2 = box
        h, w = img.shape[:2]
        x1 = max(0, x1); y1 = max(0, y1)
        x2 = min(w, x2); y2 = min(h, y2)
        return img[y1:y2, x1:x2]
    except Exception:
        return img


def get_detector_pipeline() -> str:
    parts = []
    if yolo_model is not None:
        parts.append("yolov8n-face")
    parts.append("scrfd+arcface")
    if liveness_detector is not None:
        parts.append("liveness")
    return "+".join(parts)


# ─────────────────────────────────────────────────────────────────────────────
# Endpoints
# ─────────────────────────────────────────────────────────────────────────────

@app.get("/health")
async def health():
    return {
        "status": "ok",
        "version": "2.0.0",
        "models": {
            "insightface": face_app is not None,
            "yolov8": yolo_model is not None,
            "liveness": liveness_detector is not None,
        },
        "pipeline": get_detector_pipeline(),
        "thresholds": {
            "face_match": FACE_MATCH_THRESHOLD,
            "liveness":   LIVENESS_THRESHOLD,
        },
    }


@app.post("/extract")
async def extract_face(image: UploadFile = File(...)):
    """
    สร้าง 512D face embedding จากรูปโปรไฟล์
    ใช้ตอน upload รูปโปรไฟล์ใหม่เท่านั้น
    """
    t0 = time.time()
    logger.info(f"[extract] file={image.filename}")

    contents = await image.read()
    try:
        img = decode_image(contents)
    except ValueError as e:
        raise HTTPException(400, str(e))

    # YOLOv8 pre-filter (optional)
    roi = yolo_detect_face(img)
    if roi is None:
        raise HTTPException(400, "No face detected in image")

    # InsightFace detect + embed
    face, embedding = insightface_detect(roi if roi is not img else img)
    if embedding is None:
        # Retry with full image if YOLOv8 crop failed
        face, embedding = insightface_detect(img)
    if embedding is None:
        raise HTTPException(400, "No face detected in image. Please ensure the image contains a clear, front-facing face.")

    faces_in_img = face_app.get(img)
    if len(faces_in_img) > 1:
        raise HTTPException(400, "Multiple faces detected. Please upload a photo with only one person.")

    elapsed_ms = int((time.time() - t0) * 1000)
    logger.info(f"[extract] OK in {elapsed_ms}ms")

    return {
        "status": "success",
        "message": "Face embedding extracted successfully",
        "embedding": embedding.tolist(),
        "embedding_dims": len(embedding),
        "processing_ms": elapsed_ms,
        "detector_used": get_detector_pipeline(),
    }


@app.post("/verify")
async def verify_face(
    image: UploadFile = File(...),
    known_embedding: str = Form(...),
    check_liveness: bool = Form(True),
):
    """
    ยืนยันใบหน้าจาก selfie เทียบกับ embedding ที่เก็บไว้
    พร้อม Passive Liveness Check (ป้องกัน photo attack)
    """
    t0 = time.time()
    logger.info(f"[verify] file={image.filename} liveness={check_liveness}")

    # ── Parse stored embedding ─────────────────────────────────────────
    try:
        stored_list = json.loads(known_embedding)
        stored_emb = np.array(stored_list, dtype=np.float32)
    except Exception:
        raise HTTPException(400, "Invalid known_embedding format. Must be a JSON array.")

    if stored_emb.shape != (512,):
        raise HTTPException(400, f"Invalid embedding shape: {stored_emb.shape}. Expected (512,)")

    # ── Decode image ───────────────────────────────────────────────────
    contents = await image.read()
    try:
        img = decode_image(contents)
    except ValueError as e:
        raise HTTPException(400, str(e))

    # ── YOLOv8 pre-filter ──────────────────────────────────────────────
    roi = yolo_detect_face(img)
    if roi is None:
        elapsed_ms = int((time.time() - t0) * 1000)
        return {
            "status": "no_face",
            "is_match": False,
            "score_percentage": 0.0,
            "liveness_passed": False,
            "liveness_score": 0.0,
            "message": "No face detected in frame",
            "processing_ms": elapsed_ms,
            "detector_used": get_detector_pipeline(),
        }

    # ── InsightFace detect & embed ─────────────────────────────────────
    work_img = roi if (roi is not img) else img
    face, selfie_emb = insightface_detect(work_img)

    if selfie_emb is None and roi is not img:
        # Retry with full image
        face, selfie_emb = insightface_detect(img)
        work_img = img

    if selfie_emb is None:
        elapsed_ms = int((time.time() - t0) * 1000)
        return {
            "status": "no_face",
            "is_match": False,
            "score_percentage": 0.0,
            "liveness_passed": False,
            "liveness_score": 0.0,
            "message": "No face detected by SCRFD",
            "processing_ms": elapsed_ms,
            "detector_used": get_detector_pipeline(),
        }

    # ── ArcFace cosine similarity ──────────────────────────────────────
    similarity = float(np.dot(stored_emb, selfie_emb))
    score_pct  = float(similarity * 100)
    is_match   = similarity >= FACE_MATCH_THRESHOLD

    # ── Passive Liveness ───────────────────────────────────────────────
    liveness_passed = True
    liveness_score  = 1.0
    liveness_checks: dict = {}

    if check_liveness and liveness_detector is not None and USE_LIVENESS:
        try:
            # Crop aligned face for liveness analysis
            face_crop = crop_aligned_face(work_img, face) if face is not None else work_img
            landmarks = face.kps if (face is not None and hasattr(face, "kps")) else None

            liv_result: LivenessResult = liveness_detector.check(face_crop, landmarks)
            liveness_passed = liv_result.is_live
            liveness_score  = liv_result.liveness_score
            liveness_checks = liv_result.checks
        except Exception as e:
            logger.warning(f"Liveness check error (non-fatal): {e}")
            # ถ้า liveness error → อย่าบล็อก (fail open for UX, log for audit)
            liveness_passed = True
            liveness_score  = 0.5

    elapsed_ms = int((time.time() - t0) * 1000)

    # ── Final decision ─────────────────────────────────────────────────
    final_pass = is_match and liveness_passed

    if final_pass:
        msg = f"Face verified ✓ ({score_pct:.1f}%) — Liveness confirmed"
    elif not is_match:
        msg = f"Face does not match ({score_pct:.1f}%)"
    else:
        msg = f"Face matches ({score_pct:.1f}%) but liveness check failed"

    logger.info(
        f"[verify] match={is_match}({score_pct:.1f}%) "
        f"live={liveness_passed}({liveness_score:.2f}) "
        f"final={final_pass} in {elapsed_ms}ms"
    )

    return {
        "status": "success",
        "is_match": final_pass,        # ← True เฉพาะผ่านทั้ง face+liveness
        "face_match": is_match,        # ← face similarity เฉยๆ
        "liveness_passed": liveness_passed,
        "similarity": round(similarity, 4),
        "score_percentage": round(score_pct, 2),
        "liveness_score": round(liveness_score, 4),
        "liveness_checks": liveness_checks,
        "detector_used": get_detector_pipeline(),
        "processing_ms": elapsed_ms,
        "message": msg,
    }


@app.post("/liveness")
async def check_liveness_only(image: UploadFile = File(...)):
    """
    ตรวจ Liveness อย่างเดียว ไม่ verify identity
    ใช้เพื่อ pre-screen ก่อน verify จริง (optional)
    """
    if liveness_detector is None:
        raise HTTPException(503, "Liveness detector not initialized")

    t0 = time.time()
    contents = await image.read()

    try:
        img = decode_image(contents)
    except ValueError as e:
        raise HTTPException(400, str(e))

    # ตรวจจับใบหน้าก่อน
    roi = yolo_detect_face(img)
    work_img = roi if (roi is not None and roi is not img) else img

    face, _ = insightface_detect(work_img)
    if face is None and roi is not img:
        face, _ = insightface_detect(img)
        work_img = img

    if face is None:
        return {
            "is_live": False,
            "liveness_score": 0.0,
            "message": "No face detected",
            "checks": {},
        }

    face_crop = crop_aligned_face(work_img, face)
    landmarks = face.kps if hasattr(face, "kps") else None

    result: LivenessResult = liveness_detector.check(face_crop, landmarks)

    elapsed_ms = int((time.time() - t0) * 1000)
    logger.info(f"[liveness] score={result.liveness_score:.3f} live={result.is_live} in {elapsed_ms}ms")

    return {
        "is_live": result.is_live,
        "liveness_score": result.liveness_score,
        "checks": result.checks,
        "message": result.message,
        "processing_ms": elapsed_ms,
    }


if __name__ == "__main__":
    import uvicorn
    uvicorn.run("server:app", host="0.0.0.0", port=8001, reload=False, workers=1)
