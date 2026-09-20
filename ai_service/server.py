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

# ── Node identification ──────────────────────────────────────────────────────
# Multiple AI nodes (GPU PC, Termux, ...) can ship logs to the same UDP sink;
# without a tag, the monitor cannot tell which engine processed a scan.
# Override with AI_NODE_NAME (e.g. AI_NODE_NAME=gpu-pc).
NODE_NAME = os.environ.get("AI_NODE_NAME", socket.gethostname().upper())
NODE_TAG = f"[node:{NODE_NAME}]"

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] " + NODE_TAG + " %(name)s: %(message)s"
)
logger = logging.getLogger("AIServer")

try:
    udp = UDPHandler(
        os.environ.get("AI_LOG_UDP_HOST", "192.168.1.222"),
        int(os.environ.get("AI_LOG_UDP_PORT", "9997")),
    )
    udp.setFormatter(logging.Formatter("%(asctime)s [%(levelname)s] " + NODE_TAG + " %(message)s"))
    logger.addHandler(udp)
except Exception:
    pass

# ── CPU Tuning (Termux / Android) ────────────────────────────────────────────
os.environ.setdefault("OMP_NUM_THREADS", "4")
os.environ.setdefault("OPENBLAS_NUM_THREADS", "4")
os.environ.setdefault("MKL_NUM_THREADS", "4")

# ── FastAPI ───────────────────────────────────────────────────────────────────
from fastapi import FastAPI, File, UploadFile, Form, HTTPException, Query, Request, Security, Depends, status
from fastapi.security.api_key import APIKeyHeader
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel

# ── InsightFace ───────────────────────────────────────────────────────────────
from insightface.app import FaceAnalysis
import onnxruntime as ort
from sklearn.decomposition import PCA
import pickle

# ── Local modules ─────────────────────────────────────────────────────────────
from liveness import LivenessDetector, LivenessResult
from fdx_backend import FdxBackend
from depth_liveness import DepthLivenessAnalyzer, DepthLivenessResult

# ─────────────────────────────────────────────────────────────────────────────
# Global models (loaded once at startup)
# ─────────────────────────────────────────────────────────────────────────────
face_app: Optional[FaceAnalysis] = None
liveness_detector: Optional[LivenessDetector] = None
yolo_model = None        # ultralytics YOLO (optional, lazy-loaded)
pca_reducer: Optional[PCA] = None  # sklearn PCA 512D → 128D
fdx_backend: Optional[FdxBackend] = None  # fdx D3D11 embedder (activity-check decoder)
depth_liveness: Optional[DepthLivenessAnalyzer] = None  # depth-stream signal (optional)

LIVENESS_THRESHOLD = float(os.environ.get("LIVENESS_THRESHOLD", "0.58"))
FACE_MATCH_THRESHOLD = float(os.environ.get("FACE_MATCH_THRESHOLD", "0.65"))
USE_YOLO = os.environ.get("USE_YOLO", "1") == "1"
USE_LIVENESS = os.environ.get("USE_LIVENESS", "1") == "1"
USE_FDX = os.environ.get("USE_FDX", "1") == "1"   # fdx = chosen activity-check decoder
# Depth-stream liveness (weak additional signal; fails OPEN when the depth
# server at DEPTH_LIVENESS_URL is not running). Thresholds live in
# depth_liveness.py (env: DEPTH_FLUX_MIN / DEPTH_SPAN_MIN, UNCALIBRATED).
USE_DEPTH_LIVENESS = os.environ.get("USE_DEPTH_LIVENESS", "1") == "1"
DEPTH_LIVENESS_URL = os.environ.get("DEPTH_LIVENESS_URL", "http://127.0.0.1:8086")
DEPTH_LIVENESS_SAMPLES = int(os.environ.get("DEPTH_LIVENESS_SAMPLES", "8"))

# ── API Key & Security Configuration ──────────────────────────────────────────
AI_SERVER_KEY = os.environ.get("AI_SERVER_KEY", os.environ.get("AI_SERVICE_API_KEY", "uni-activity-ai-secret-key-2026"))
API_KEY_NAME = "X-API-Key"
api_key_header = APIKeyHeader(name=API_KEY_NAME, auto_error=False)

async def verify_api_key(api_key: str = Security(api_key_header)):
    """Verify that incoming request contains the valid secret API Key"""
    if not AI_SERVER_KEY:
        return True
    if not api_key or api_key.strip() != AI_SERVER_KEY.strip():
        logger.warning(f"Unauthorized API request rejected: missing or invalid '{API_KEY_NAME}' header")
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=f"Forbidden: Invalid or missing '{API_KEY_NAME}' header for AI Service.",
        )
    return True

# ── Resolve model path relative to this file's directory ──────────────────────
_HERE = os.path.dirname(os.path.abspath(__file__))
_DEFAULT_YOLO = os.path.join(_HERE, "yolov8n-face.pt")
YOLO_MODEL_PATH = os.environ.get("YOLO_MODEL_PATH", _DEFAULT_YOLO)


@asynccontextmanager
async def lifespan(app: FastAPI):
    """Load all models at startup, release at shutdown"""
    global face_app, liveness_detector, yolo_model, pca_reducer, depth_liveness, fdx_backend

    logger.info("=" * 60)
    logger.info("Starting Uni-Activity AI Server v2.0 (Secured with API Key & Restricted CORS)")
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

    # ── 2b. Depth-stream liveness (optional, fail-open) ───────────────
    if USE_DEPTH_LIVENESS:
        depth_liveness = DepthLivenessAnalyzer(
            server_url=DEPTH_LIVENESS_URL,
            sample_frames=DEPTH_LIVENESS_SAMPLES)
        logger.info(f"Depth liveness analyzer ready ✓ (server={DEPTH_LIVENESS_URL}, "
                    "fail-open when unreachable)")
    else:
        logger.info("Depth liveness DISABLED (USE_DEPTH_LIVENESS=0)")

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

    # ── 4. PCA Reducer for 512D → 128D ────────────────────────────────
    logger.info("Setting up PCA reducer for 512D \u2192 128D conversion...")
    pca_reducer = PCA(n_components=128, random_state=42)
    dummy_data = np.random.randn(200, 512).astype(np.float32)
    pca_reducer.fit(dummy_data)
    logger.info("PCA reducer initialized \u2713")

    # ── 5. fdx embedder (chosen activity-check face decoder) ─────────────
    if USE_FDX:
        fdx_backend = FdxBackend()
        if fdx_backend.available:
            adapter = fdx_backend.describe().get("adapter", {})
            logger.info(f"fdx engine ready ✓ (adapter={adapter.get('name')})")
        else:
            logger.warning(f"fdx engine unavailable ({fdx_backend.last_error}) "
                           "— /extract & /verify fall back to insightface ArcFace")
    else:
        logger.info("fdx DISABLED (USE_FDX=0)")

    # NOTE: no synthetic warmup here — a zeroed-frame warmup hard-crashes
    # DirectML (no traceback, process dies after "fdx engine ready"). The
    # cold path (~9s on frame 1) is handled by the 20s client abort instead;
    # operators can warm the pipeline with one real verify after deploy.

    logger.info("All models ready. Server is UP.")
    yield

    # Shutdown
    logger.info("Shutting down AI Server...")
    if fdx_backend is not None:
        fdx_backend.close()


app = FastAPI(
    title="Uni-Activity AI Server",
    version="2.1.0",
    description="Face Verification: YOLOv8 + SCRFD + ArcFace + Passive Liveness",
    lifespan=lifespan,
)

# ── Restricted CORS Configuration ─────────────────────────────────────────────
ALLOWED_ORIGINS_RAW = os.environ.get(
    "CORS_ALLOWED_ORIGINS",
    "http://127.0.0.1:8080,http://localhost:8080,http://192.168.1.222:8080,http://127.0.0.1:8000,http://localhost:8000"
)
allowed_origins = [orig.strip() for orig in ALLOWED_ORIGINS_RAW.split(",") if orig.strip()]

app.add_middleware(
    CORSMiddleware,
    allow_origins=allowed_origins,
    allow_credentials=True,
    allow_methods=["GET", "POST", "OPTIONS"],
    allow_headers=["Content-Type", "Authorization", "X-API-Key", "X-Requested-With", "Accept", "X-CSRF-TOKEN"],
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


def fdx_embed_crop(crop_bgr: np.ndarray) -> Optional[np.ndarray]:
    """512-d via fdx (the chosen decoder); None if engine unavailable/failed."""
    if fdx_backend is None or not fdx_backend.available:
        return None
    return fdx_backend.embed_bgr(crop_bgr)


def get_full_face_bbox(
    face,
    img_shape: tuple,
    pad_top_ratio: float = 0.28,
    pad_bottom_ratio: float = 0.14,
    pad_side_ratio: float = 0.14,
) -> list[int]:
    """
    คำนวณ Bounding Box ที่ครอบคลุมความยาวใบหน้าเต็มสัดส่วน 100%
    (ตั้งแต่ไรผม/หน้าผากด้านบน จนถึงปลายคางและแนวกรามด้านล่าง)
    """
    box = face.bbox.astype(int)
    x1, y1, x2, y2 = box
    h, w = img_shape[:2]
    face_w = x2 - x1
    face_h = y2 - y1

    pad_t = int(face_h * pad_top_ratio)
    pad_b = int(face_h * pad_bottom_ratio)
    pad_s = int(face_w * pad_side_ratio)

    # int() coercion: bbox values are numpy int32 and FastAPI cannot serialize
    # numpy scalars (500 "object is not iterable" at response encoding)
    return [
        int(max(0, x1 - pad_s)),
        int(max(0, y1 - pad_t)),
        int(min(w, x2 + pad_s)),
        int(min(h, y2 + pad_b)),
    ]


def crop_aligned_face(img: np.ndarray, face, full_length: bool = True) -> np.ndarray:
    """
    Crop face จากภาพ โดยรองรับการขยายสัดส่วนความยาวใบหน้าแบบเต็มกรอบ (Full Face Length)
    full_length=True: ครอบคลุมหน้าผาก ผม ปลายคาง กราม (เหมาะกับ liveness & UI display)
    full_length=False: ครอปเฉพาะ raw bbox แนบชิดโครงหน้า
    """
    try:
        if full_length:
            x1, y1, x2, y2 = get_full_face_bbox(face, img.shape)
        else:
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


def reduce_to_128d(embedding_512d: np.ndarray) -> np.ndarray:
    """
    ลด embedding จาก 512D เป็น 128D โดยใช้ PCA พร้อม L2 Normalization
    สำหรับใช้ใน JavaScript real-time processing
    """
    global pca_reducer
    
    emb = np.array(embedding_512d, dtype=np.float32).reshape(1, -1)

    if pca_reducer is not None:
        try:
            reduced = pca_reducer.transform(emb)[0]
        except Exception as e:
            logger.warning(f"PCA reduction failed: {e}, using orthogonal projection fallback")
            rng = np.random.RandomState(42)
            proj, _ = np.linalg.qr(rng.randn(512, 128).astype(np.float32))
            reduced = emb[0] @ proj[:, :128]
    else:
        rng = np.random.RandomState(42)
        proj, _ = np.linalg.qr(rng.randn(512, 128).astype(np.float32))
        reduced = emb[0] @ proj[:, :128]

    # Ensure exactly 128 dimensions and L2-normalize
    if len(reduced) != 128:
        reduced = reduced[:128]

    norm = np.linalg.norm(reduced)
    if norm > 0:
        reduced = reduced / norm

    return reduced.astype(np.float32)


# ─────────────────────────────────────────────────────────────────────────────
# Endpoints
# ─────────────────────────────────────────────────────────────────────────────

@app.get("/health")
async def health():
    return {
        "status": "ok",
        "node": NODE_NAME,
        "version": "2.1.0",
        "auth_required": bool(AI_SERVER_KEY),
        "models": {
            "insightface": face_app is not None,
            "yolov8": yolo_model is not None,
            "liveness": liveness_detector is not None,
            "depth_liveness": depth_liveness is not None,
            "fdx": fdx_backend.available if fdx_backend else False,
        },
        "embedder": ("fdx-d3d11" if fdx_backend is not None and fdx_backend.available
                     else "insightface-arcface"),
        "fdx": fdx_backend.describe() if fdx_backend else None,
        "pipeline": get_detector_pipeline(),
        "thresholds": {
            "face_match": FACE_MATCH_THRESHOLD,
            "liveness":   LIVENESS_THRESHOLD,
        },
    }


@app.get("/warmup", dependencies=[Depends(verify_api_key)])
async def warmup():
    """
    Pre-warm the GPU pipeline with a real (non-zeroed) noise image so
    that the first live /verify request does not hit the cold-path delay.

    Safe for DirectML: uses random uint8 pixels (not all-zeros).
    DirectML hard-crashes only on zeroed frames — random noise is fine.
    """
    t0 = time.time()
    try:
        rng = np.random.RandomState(seed=int(time.time()) % 65536)
        # 480×640 BGR noise — realistic size, non-zero pixels
        warm_img = rng.randint(50, 200, (480, 640, 3), dtype=np.uint8)

        # Run InsightFace detect (will find no face in noise, but warms ONNX runtime)
        if face_app is not None:
            _ = face_app.get(warm_img)

        # Run YOLOv8 detect (optional)
        if yolo_model is not None:
            _ = yolo_model(warm_img, verbose=False, conf=0.9)

        elapsed_ms = int((time.time() - t0) * 1000)
        logger.info(f"[warmup] GPU pipeline warmed in {elapsed_ms}ms")
        return {"status": "warmed", "elapsed_ms": elapsed_ms, "node": NODE_NAME}
    except Exception as e:
        elapsed_ms = int((time.time() - t0) * 1000)
        logger.warning(f"[warmup] failed in {elapsed_ms}ms: {e}")
        return {"status": "partial", "elapsed_ms": elapsed_ms, "error": str(e)}


@app.post("/extract", dependencies=[Depends(verify_api_key)])
async def extract_face(image: UploadFile = File(...)):
    """
    สร้าง face embedding จากรูปโปรไฟล์ในสองรูปแบบ:
    - 512D ArcFace (สำหรับ verification ความแม่นยำสูง)
    - 128D reduced (สำหรับ JavaScript real-time processing)
    ใช้ตอน upload รูปโปรไฟล์ใหม่เท่านั้น
    """
    t0 = time.time()
    logger.info(f"[extract] file={image.filename} - extracting both 512D and 128D embeddings")

    contents = await image.read()
    try:
        img = decode_image(contents)
    except ValueError as e:
        raise HTTPException(400, str(e))

    # YOLOv8 pre-filter (optional)
    roi = yolo_detect_face(img)
    if roi is None:
        raise HTTPException(400, "No face detected in image")

    # InsightFace detect + embed (512D)
    face, embedding_512d = insightface_detect(roi if roi is not img else img)
    if embedding_512d is None:
        # Retry with full image if YOLOv8 crop failed
        face, embedding_512d = insightface_detect(img)
    if embedding_512d is None:
        raise HTTPException(400, "No face detected in image. Please ensure the image contains a clear, front-facing face.")

    # ── fdx 512-d (chosen activity-check decoder) ──────────────────────
    embedder = "insightface-arcface"
    fdx_512d: Optional[np.ndarray] = None
    if fdx_backend is not None and fdx_backend.available:
        crop = crop_aligned_face(img, face, full_length=False) if face is not None else img
        fdx_512d = fdx_backend.embed_bgr(crop)
        if fdx_512d is not None:
            embedder = "fdx-d3d11"
        else:
            logger.warning(f"[extract] fdx failed ({fdx_backend.last_error}) — falling back to insightface")

    faces_in_img = face_app.get(img)
    if len(faces_in_img) > 1:
        raise HTTPException(400, "Multiple faces detected. Please upload a photo with only one person.")

    # สร้าง 128D embedding โดยใช้ PCA dimensionality reduction
    embedding_128d = reduce_to_128d(embedding_512d)

    elapsed_ms = int((time.time() - t0) * 1000)
    logger.info(f"[extract] OK in {elapsed_ms}ms - 512D + 128D extracted")

    std_bbox = [int(v) for v in face.bbox.tolist()] if face is not None else []
    full_bbox = get_full_face_bbox(face, img.shape) if face is not None else []

    # When fdx is the embedder, embedding_512d IS the fdx vector: enrollments
    # stored from this response are natively in the fdx space. The insightface
    # vector is kept alongside for the migration window; 128-d PCA output is
    # disabled there until the reducer is re-fit on fdx embeddings.
    if fdx_512d is not None:
        primary_512d, legacy_512d = fdx_512d, embedding_512d
        embedding_128d = None
    else:
        primary_512d, legacy_512d = embedding_512d, None

    return {
        "status": "success",
        "message": ("Face embeddings extracted successfully (512D fdx)" if fdx_512d is not None
                    else "Face embeddings extracted successfully (512D + 128D)"),
        "embedding_512d": primary_512d.tolist(),
        "embedding_space": "fdx-w600k-mbf" if fdx_512d is not None else "insightface-arcface",
        "embedding_128d": embedding_128d.tolist() if embedding_128d is not None else None,
        "embedding_dims": {
            "full": len(primary_512d),
            "reduced": len(embedding_128d) if embedding_128d is not None else 0
        },
        "embedding_insightface_512d": legacy_512d.tolist() if legacy_512d is not None else None,
        "embedder": embedder,
        "bbox": std_bbox,
        "full_face_bbox": full_bbox,
        "processing_ms": elapsed_ms,
        "detector_used": get_detector_pipeline(),
    }


@app.post("/verify", dependencies=[Depends(verify_api_key)])
async def verify_face(
    request: Request,
    image: UploadFile = File(...),
    known_embedding: str = Form(...),
    check_liveness: bool = Form(True),
):
    """
    ยืนยันใบหน้าจาก selfie เทียบกับ embedding ที่เก็บไว้
    พร้อม Passive Liveness Check (ป้องกัน photo attack)
    """
    t0 = time.time()
    ui_build = request.headers.get("x-scan-ui", "?")
    logger.info(f"[verify] file={image.filename} liveness={check_liveness} ui={ui_build}")

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
        # frame diagnostics: lets the UI (and logs) tell a dark/occluded frame
        # from a detector failure instead of failing silently
        mean_b = float(img.mean()) if img is not None else -1.0
        std_b = float(img.std()) if img is not None else -1.0
        logger.info(f"[verify] no_face(yolo) in {elapsed_ms}ms "
                    f"diag={{'brightness': {mean_b:.1f}, 'std': {std_b:.1f}, 'size': {img.shape[1]}x{img.shape[0]}}}")
        return {
            "status": "no_face",
            "is_match": False,
            "score_percentage": 0.0,
            "liveness_passed": False,
            "liveness_score": 0.0,
            "message": "No face detected in frame",
            "frame_diag": {
                "brightness": round(mean_b, 1),
                "std": round(std_b, 1),
                "size": f"{img.shape[1]}x{img.shape[0]}",
            },
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
        mean_b = float(img.mean()) if img is not None else -1.0
        std_b = float(img.std()) if img is not None else -1.0
        logger.info(f"[verify] no_face(scrfd) in {elapsed_ms}ms "
                    f"diag={{'brightness': {mean_b:.1f}, 'std': {std_b:.1f}}}")
        return {
            "status": "no_face",
            "is_match": False,
            "score_percentage": 0.0,
            "liveness_passed": False,
            "liveness_score": 0.0,
            "message": "No face detected by SCRFD",
            "frame_diag": {
                "brightness": round(mean_b, 1),
                "std": round(std_b, 1),
            },
            "processing_ms": elapsed_ms,
            "detector_used": get_detector_pipeline(),
        }

    # ── Cosine similarity (fdx preferred, insightface fallback) ─────────
    embedder = "insightface-arcface"
    threshold = FACE_MATCH_THRESHOLD
    fdx_emb: Optional[np.ndarray] = None
    if fdx_backend is not None and fdx_backend.available:
        crop = crop_aligned_face(work_img, face, full_length=False) if face is not None else work_img
        fdx_emb = fdx_backend.embed_bgr(crop)
        if fdx_emb is not None:
            embedder = "fdx-d3d11"
            threshold = fdx_backend.threshold
    if fdx_emb is not None:
        similarity = float(np.dot(stored_emb, fdx_emb))
    else:
        similarity = float(np.dot(stored_emb, selfie_emb))
    score_pct  = float(similarity * 100)
    is_match   = similarity >= threshold

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

    # ── Depth-stream liveness (weak additional signal, fail-open) ──────
    # Only meaningful when the capture client is streaming to the depth
    # server; a still-image /verify simply gets 'unavailable' = no change.
    depth_checks: dict = {}
    if check_liveness and depth_liveness is not None and USE_DEPTH_LIVENESS \
            and liveness_passed:  # skip the ~1 s sampling when texture already failed
        try:
            dres: DepthLivenessResult = depth_liveness.analyze()
            depth_checks = dres.checks
            if dres.available:
                # fusion: require BOTH texture liveness and depth-motion
                # evidence when depth data exists; unavailable = no change
                liveness_passed = liveness_passed and dres.is_live
                liveness_score = round((liveness_score + dres.liveness_score) / 2, 4)
            logger.info(f"[verify] depth liveness: live={dres.is_live} "
                        f"available={dres.available} checks={depth_checks}")
        except Exception as e:
            logger.warning(f"Depth liveness error (non-fatal): {e}")
            depth_checks = {"available": False, "error": str(e)}

    elapsed_ms = int((time.time() - t0) * 1000)

    # ── Final decision ─────────────────────────────────────────────────
    final_pass = is_match and liveness_passed

    if final_pass:
        msg = f"Face verified ✓ ({score_pct:.1f}%) — Liveness confirmed"
    elif not is_match:
        msg = f"Face does not match ({score_pct:.1f}%)"
    elif not depth_checks.get("available", False):
        msg = f"Face matches ({score_pct:.1f}%) but liveness check failed"
    elif depth_checks.get("motion_ok", True):
        msg = f"Face matches ({score_pct:.1f}%) but depth span check failed"
    else:
        msg = (f"Face matches ({score_pct:.1f}%) but depth motion check failed "
               "(possible photo attack)")

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
        "depth_liveness_checks": depth_checks,
        "embedder": embedder,
        "match_threshold": round(float(threshold), 4),
        "detector_used": get_detector_pipeline(),
        "processing_ms": elapsed_ms,
        "message": msg,
    }


@app.post("/liveness", dependencies=[Depends(verify_api_key)])
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
